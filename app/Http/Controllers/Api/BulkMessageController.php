<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BulkMessage;
use App\Models\Contact;
use App\Models\Template;
use App\Models\ActivePackage;
use App\Models\MessageDeliveryStatus;
use App\Models\Settings;
use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Lead;
use App\Services\AutoAiReplyService;
use App\Services\WhatsAppService;
use App\Services\WalletService;
use App\Services\WhatsAppWebhookForwarder;
use App\Services\WhatsAppInboxService;
use App\Services\WhatsAppCatalogService;
use App\Jobs\SendChatMessageNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkMessageController extends Controller
{
    /**
     * Get all bulk messages for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $status = $request->query('status');

            $query = BulkMessage::where('userId', $userId)->with(['template', 'contact']);

            if ($status) {
                $query->where('status', $status);
            }

            $bulkMessages = $query->orderBy('created_at', 'desc')->paginate(15);

            return response()->json([
                'status' => true,
                'data' => $bulkMessages
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new bulk message campaign
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'templateId' => 'required|exists:templates,id',
            'variables' => 'nullable|array',
            'headervariables' => 'nullable|array',
            'contacts' => 'required|array',
            'contacts.*' => 'exists:contacts,id',
            'scheduleAt' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        if (!config('whatsapp.bulk_send_enabled')) {
            return response()->json([
                'status' => false,
                'message' => 'Bulk send is currently disabled. No messages will be sent and no charges will be applied.',
            ], 500);
        }

        try {
            $userId = $request->user()->getCompanyId();

            // 1. Load template
            $template = Template::find($request->templateId);
            if (!$template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found'
                ], 404);
            }

            if ($template->status !== 'APPROVED') {
                return response()->json([
                    'status' => false,
                    'message' => 'Template is not approved. Cannot send campaign.'
                ], 400);
            }

            // 2. Fetch contacts
            $contacts = Contact::whereIn('id', $request->contacts)
                ->where('userId', $userId)
                ->get();

            if ($contacts->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No valid contacts found'
                ], 400);
            }

            // 3. Check user active package
            $activePlan = ActivePackage::where('userId', $userId)
                ->where('status', 1)
                ->with('package')
                ->latest()
                ->first();

            if (!$activePlan) {
                // Fallback to active package if no user specific plan record found
                $activePlan = ActivePackage::where('status', 1)
                    ->with('package')
                    ->latest()
                    ->first();
            }

            if (!$activePlan) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active plan found. Please purchase a plan first.'
                ], 400);
            }

            // Check if package is still active based on dates
            $currentDate = now();
            if ($activePlan->startDate && $currentDate->lt($activePlan->startDate)) {
                $activePlan->update(['status' => ActivePackage::STATUS_UPCOMING]);
                return response()->json([
                    'status' => false,
                    'message' => 'Your package has not started yet. It will become active on ' . $activePlan->startDate->toDateString() . '.'
                ], 400);
            }
            if ($activePlan->endDate && $currentDate->gt($activePlan->endDate)) {
                // Package has expired, update status
                $activePlan->update(['status' => 0]);
                return response()->json([
                    'status' => false,
                    'message' => 'Your package has expired. Please renew your plan.'
                ], 400);
            }

            $activePlan->checkAndPerformMonthlyReset();
            $allowedMsgCount = $activePlan->resolvedMessageLimit();
            // Get user's current period used message count (user-wise)
            $usedMsgCount = $activePlan->is_yearly 
                ? (int) ($activePlan->monthlyUsedMsgCount ?? 0)
                : (int) ($activePlan->usedMsgCount ?? 0);
            $remainingMsg = max(0, $allowedMsgCount - $usedMsgCount);

            // Debug information
            Log::info("Message Limit Check", [
                'userId' => $userId,
                'userName' => $request->user()->name,
                'packageName' => $activePlan->package->packageName,
                'allowedMsgCount' => $allowedMsgCount,
                'usedMsgCount' => $usedMsgCount,
                'remainingMsg' => $remainingMsg,
                'requestedContacts' => $contacts->count()
            ]);

            if ($contacts->count() > $remainingMsg) {
                return response()->json([
                    'status' => false,
                    'message' => "Your plan allows only {$remainingMsg} more messages. You are trying to send {$contacts->count()} messages. Please upgrade your plan."
                ], 500);
            }

            // Wallet-enabled users need minimum balance before starting bulk
            try {
                app(WalletService::class)->assertCanStartBulk($request->user());
            } catch (\RuntimeException $e) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            // 4. Create campaign
            $campaign = BulkMessage::create([
                'userId' => $userId,
                'name' => $request->name,
                'templateId' => $request->templateId,
                'variables' => $request->variables ?? [],
                'headerVariables' => $request->headervariables ?? [],
                'contactIds' => $request->contacts,
                'scheduleAt' => $request->scheduleAt,
                'status' => $request->scheduleAt ? 'scheduled' : 'pending', // Mark as pending for scheduled command to process
            ]);

            // 5. Create delivery status records for each contact
            foreach ($contacts as $contact) {
                MessageDeliveryStatus::create([
                    'bulk_message_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'status' => 'pending',
                ]);
            }

            // 6. Messages will be processed by the scheduled command (bulk:process-pending-messages)
            // This prevents timeout errors by processing in small batches

            return response()->json([
                'status' => true,
                'message' => 'Campaign created successfully',
                'data' => $campaign->load(['template', 'contact'])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a campaign (only scheduled campaigns can be updated)
     */
    public function updateCampaign(Request $request, $id)
    {
        // Handle both JSON and form-data formats
        // If variables/contacts come as JSON strings (from form-data), decode them
        $data = $request->all();

        // Handle variables array - could be JSON string or array
        if (isset($data['variables'])) {
            if (is_string($data['variables'])) {
                $decoded = json_decode($data['variables'], true);
                $data['variables'] = $decoded !== null ? $decoded : [];
            }
        }

        // Handle headervariables array - could be JSON string or array
        if (isset($data['headervariables'])) {
            if (is_string($data['headervariables'])) {
                $decoded = json_decode($data['headervariables'], true);
                $data['headervariables'] = $decoded !== null ? $decoded : [];
            }
        }

        // Handle contacts array - could be JSON string or array
        if (isset($data['contacts'])) {
            if (is_string($data['contacts'])) {
                $decoded = json_decode($data['contacts'], true);
                $data['contacts'] = $decoded !== null ? $decoded : [];
            }
        }

        // Merge decoded data back into request
        $request->merge($data);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'templateId' => 'required|exists:templates,id',
            'variables' => 'nullable|array',
            'headervariables' => 'nullable|array',
            'contacts' => 'required|array',
            'contacts.*' => 'exists:contacts,id',
            'scheduleAt' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $userId = $request->user()->getCompanyId();
            $isAdmin = $request->user()->role === 'admin';

            // Find the campaign - admin can update any campaign, regular users can only update their own
            $query = BulkMessage::where('id', $id);
            if (!$isAdmin) {
                $query->where('userId', $userId);
            }

            $campaign = $query->first();

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            // Check if campaign is scheduled - only scheduled campaigns can be updated
            if ($campaign->status !== 'scheduled') {
                return response()->json([
                    'status' => false,
                    'message' => 'Only scheduled campaigns can be updated'
                ], 403);
            }

            // 1. Load template
            $template = Template::find($request->templateId);
            if (!$template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found'
                ], 404);
            }

            if ($template->status !== 'APPROVED') {
                return response()->json([
                    'status' => false,
                    'message' => 'Template is not approved. Cannot update campaign.'
                ], 400);
            }

            // 2. Fetch contacts
            $contacts = Contact::whereIn('id', $request->contacts)
                ->where('userId', $userId)
                ->get();

            if ($contacts->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No valid contacts found'
                ], 400);
            }

            // 3. Check global active package
            $activePlan = ActivePackage::where('status', 1)
                ->with('package')
                ->latest()
                ->first();

            if (!$activePlan) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active plan found. Please purchase a plan first.'
                ], 400);
            }

            // Check if package is still active based on dates
            $currentDate = now();
            if ($activePlan->startDate && $currentDate->lt($activePlan->startDate)) {
                $activePlan->update(['status' => ActivePackage::STATUS_UPCOMING]);
                return response()->json([
                    'status' => false,
                    'message' => 'Your package has not started yet. It will become active on ' . $activePlan->startDate->toDateString() . '.'
                ], 400);
            }
            if ($activePlan->endDate && $currentDate->gt($activePlan->endDate)) {
                // Package has expired, update status
                $activePlan->update(['status' => 0]);
                return response()->json([
                    'status' => false,
                    'message' => 'Your package has expired. Please renew your plan.'
                ], 400);
            }

            $activePlan->checkAndPerformMonthlyReset();
            $allowedMsgCount = $activePlan->resolvedMessageLimit();
            // User-specific current period used message count
            $usedMsgCount = $activePlan->is_yearly 
                ? (int) ($activePlan->monthlyUsedMsgCount ?? 0)
                : (int) ($activePlan->usedMsgCount ?? 0);
            $remainingMsg = max(0, $allowedMsgCount - $usedMsgCount);

            // Debug information
            Log::info("Message Limit Check (Update Campaign)", [
                'userId' => $userId,
                'userName' => $request->user()->name,
                'packageName' => $activePlan->package->packageName,
                'allowedMsgCount' => $allowedMsgCount,
                'usedMsgCount' => $usedMsgCount,
                'remainingMsg' => $remainingMsg,
                'requestedContacts' => $contacts->count()
            ]);

            if ($contacts->count() > $remainingMsg) {
                return response()->json([
                    'status' => false,
                    'message' => "Your plan allows only {$remainingMsg} more messages. You are trying to send {$contacts->count()} messages. Please upgrade your plan."
                ], 403);
            }

            try {
                app(WalletService::class)->assertCanStartBulk($request->user());
            } catch (\RuntimeException $e) {
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }

            // 4. Update campaign (same data structure as create)
            $campaign->update([
                'name' => $request->name,
                'templateId' => $request->templateId,
                'variables' => $request->variables ?? [],
                'headerVariables' => $request->headervariables ?? [],
                'contactIds' => $request->contacts,
                'scheduleAt' => $request->scheduleAt,
                'status' => $request->scheduleAt ? 'scheduled' : 'sending',
            ]);

            // 5. Delete old delivery status records and create new ones
            MessageDeliveryStatus::where('bulk_message_id', $campaign->id)->delete();

            foreach ($contacts as $contact) {
                MessageDeliveryStatus::create([
                    'bulk_message_id' => $campaign->id,
                    'contact_id' => $contact->id,
                    'status' => 'pending',
                ]);
            }

            // 6. If sending immediately, process messages
            if (!$request->scheduleAt) {
                $this->processBulkMessages($campaign, $contacts, $template, $activePlan);
            }

            return response()->json([
                'status' => true,
                'message' => 'Campaign updated successfully',
                'data' => $campaign->load(['template', 'contact'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific bulk message
     */
    public function show(Request $request, $id)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $bulkMessage = BulkMessage::where('userId', $userId)
                ->with(['template', 'contact'])
                ->find($id);

            if (!$bulkMessage) {
                return response()->json([
                    'status' => false,
                    'message' => 'Bulk message not found'
                ], 404);
            }

            if ($bulkMessage) {
                // Check if campaign has failed delivery statuses with Meta payment issue
                $hasPaymentIssue = $bulkMessage->deliveryStatuses()
                    ->where(function ($q) {
                        $q->where('error_message', 'like', '%payment%')
                          ->orWhere('error_message', 'like', '%unsettled%')
                          ->orWhere('error_message', 'like', '%131042%')
                          ->orWhere('metadata->payment_required', true);
                    })
                    ->exists();

                if ($hasPaymentIssue) {
                    $bulkMessage->payment_alert = [
                        'has_payment_issue' => true,
                        'title' => 'Meta WhatsApp Payment Required',
                        'message' => 'Message delivery failed because your WhatsApp Business Account has unsettled payments on Meta (Facebook). Please visit Meta Billing Hub to clear your bill.',
                        'billing_url' => 'https://business.facebook.com/billing_hub',
                    ];
                }
            }

            return response()->json([
                'status' => true,
                'data' => $bulkMessage
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a bulk message
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'status' => 'sometimes|string|in:scheduled,sending,processing,completed,completed_with_errors,failed',
            'scheduleAt' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $userId = $request->user()->getCompanyId();
            $bulkMessage = BulkMessage::where('userId', $userId)->find($id);

            if (!$bulkMessage) {
                return response()->json([
                    'status' => false,
                    'message' => 'Bulk message not found'
                ], 404);
            }

            $bulkMessage->update($request->only(['name', 'status', 'scheduleAt']));

            return response()->json([
                'status' => true,
                'message' => 'Bulk message updated successfully',
                'data' => $bulkMessage
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a bulk message (only scheduled campaigns can be deleted)
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $isAdmin = $request->user()->role === 'admin';

            // Build query - admin can delete any campaign, regular users can only delete their own
            $query = BulkMessage::where('id', $id);
            if (!$isAdmin) {
                $query->where('userId', $userId);
            }

            $bulkMessage = $query->first();

            if (!$bulkMessage) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            // Check if campaign is scheduled - only scheduled campaigns can be deleted
            if ($bulkMessage->status !== 'scheduled') {
                return response()->json([
                    'status' => false,
                    'message' => 'Only scheduled campaigns can be deleted'
                ], 403);
            }

            // Delete related delivery statuses first
            MessageDeliveryStatus::where('bulk_message_id', $bulkMessage->id)->delete();

            // Delete the campaign
            $bulkMessage->delete();

            return response()->json([
                'status' => true,
                'message' => 'Campaign deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format phone number with country code if not present
     */
    private function formatPhoneNumber($phone)
    {
        // Remove any existing + or spaces
        $phone = preg_replace('/[\s\+]/', '', $phone);

        // If phone doesn't start with country code, add +91
        if (!preg_match('/^91\d{10}$/', $phone) && !preg_match('/^\+\d+/', $phone)) {
            // Remove leading 0 if present
            $phone = ltrim($phone, '0');
            $phone = '+91' . $phone;
        } elseif (preg_match('/^91\d{10}$/', $phone)) {
            // If it starts with 91 but no +, add +
            $phone = '+' . $phone;
        }

        return $phone;
    }

    /**
     * Process bulk messages (send WhatsApp messages) in batches
     */
    private function processBulkMessages($campaign, $contacts, $template, $activePlan)
    {
        if (!config('whatsapp.bulk_send_enabled')) {
            Log::info('Bulk send is disabled. Skipping sending for campaign.', ['campaign_id' => $campaign->id]);
            return;
        }

        // Get batch processing configuration from settings
        $settings = \App\Models\Settings::where('isActive', true)->first();
        $batchSize = $settings->batch_size ?? 50; // Default to 50 if not configured
        $batchDelay = $settings->batch_delay_seconds ?? 2; // Default to 2 seconds if not configured
        $enableBatchProcessing = $settings->enable_batch_processing ?? true; // Default to true
        $totalContacts = $contacts->count();
        $successCount = 0;
        $failCount = 0;
        $whatsappService = new WhatsAppService();
        $wallet = app(WalletService::class);
        $campaignUser = User::find($campaign->userId);
        if ($campaignUser) {
            $campaignUser = User::find($campaignUser->getCompanyId()) ?: $campaignUser->fresh();
        }
        $messagePrice = $wallet->priceForCategory($template->category ?? 'UTILITY');
        $walletEnabled = $campaignUser ? $wallet->isEnabled($campaignUser) : false;

        // Update campaign status to processing
        $campaign->update(['status' => 'processing']);

        // If batch processing is disabled, process all at once
        if (!$enableBatchProcessing) {
            $batchSize = $totalContacts; // Process all at once
        }

        // Process contacts in batches
        $contactChunks = $contacts->chunk($batchSize);
        $totalBatches = $contactChunks->count();

        Log::info("Starting batch processing", [
            'campaign_id' => $campaign->id,
            'total_contacts' => $totalContacts,
            'batch_size' => $batchSize,
            'total_batches' => $totalBatches
        ]);

        $walletStopped = false;

        foreach ($contactChunks as $batchIndex => $batchContacts) {
            if ($walletStopped) {
                break;
            }

            $batchNumber = $batchIndex + 1;
            Log::info("Processing batch {$batchNumber}/{$totalBatches}", [
                'batch_contacts' => $batchContacts->count(),
                'campaign_id' => $campaign->id
            ]);

            // Process each contact in the current batch
            foreach ($batchContacts as $contact) {
                if ($walletEnabled && $campaignUser && !$wallet->canAffordNextSend($campaignUser, $messagePrice, $campaign->id)) {
                    $wallet->failRemainingForInsufficientWallet($campaign->id);
                    Log::warning('Bulk campaign stopped — insufficient wallet', [
                        'campaign_id' => $campaign->id,
                        'userId' => $campaign->userId,
                        'price' => $messagePrice,
                    ]);
                    $walletStopped = true;
                    break;
                }

                try {
                    $result = null;

                    // Format phone number with country code
                    $formattedPhone = $this->formatPhoneNumber($contact->phone);

                    // Check if template is custom or system
                    if ($template->type === 'custom') {
                        $result = $whatsappService->sendCustomTemplate(
                            $formattedPhone,
                            $template->name,
                            $campaign->variables,
                            $contact->toArray(),
                            $campaign->userId
                        );
                    } else {
                        // System template with components
                        $result = $whatsappService->sendWhatsAppMessageSelected(
                            $formattedPhone,
                            $template->name,
                            $template->language ?? 'en', // Use template's actual language
                            $template->components ?? [],
                            $campaign->variables,
                            $campaign->headerVariables,
                            $contact->toArray(),
                            $campaign->userId
                        );
                    }

                    // Update delivery status based on result
                    $deliveryStatus = MessageDeliveryStatus::where('bulk_message_id', $campaign->id)
                        ->where('contact_id', $contact->id)
                        ->first();

                    if ($result && ($result['success'] ?? false)) {
                        $successCount++;
                        $deliveryStatus->updateStatus('sent', [
                            'whatsapp_message_id' => $result['message_id'] ?? null,
                            'sent_at' => now()->toISOString(),
                            'api_response' => $result,
                            'billing_category' => strtoupper((string) ($template->category ?? 'UTILITY')),
                        ]);

                        if ($walletEnabled && $campaignUser) {
                            $charge = $wallet->chargeForSent($deliveryStatus->fresh());
                            if ($charge && ($charge['insufficient'] ?? false)) {
                                $wallet->failRemainingForInsufficientWallet($campaign->id);
                                Log::warning('Bulk campaign stopped after send — wallet insufficient', [
                                    'campaign_id' => $campaign->id,
                                    'userId' => $campaign->userId,
                                    'balance' => $charge['balance'] ?? null,
                                ]);
                                $walletStopped = true;
                                break;
                            }
                        }
                    } else {
                        $failCount++;
                        $deliveryStatus->updateStatus('failed', [
                            'error' => $result['error'] ?? 'Unknown error',
                            'failed_at' => now()->toISOString(),
                            'api_response' => $result
                        ]);
                    }
                } catch (\Exception $e) {
                    $failCount++;
                    $deliveryStatus = MessageDeliveryStatus::where('bulk_message_id', $campaign->id)
                        ->where('contact_id', $contact->id)
                        ->first();
                    $deliveryStatus->updateStatus('failed', [
                        'error' => $e->getMessage(),
                        'failed_at' => now()->toISOString()
                    ]);
                }
            }

            // Update campaign status based on current delivery statuses
            $this->updateCampaignStatus($campaign);

            // Add delay between batches (except for the last batch and if batch processing is enabled)
            if ($batchNumber < $totalBatches && $enableBatchProcessing) {
                Log::info("Waiting {$batchDelay} seconds before next batch...");
                sleep($batchDelay);
            }
        }

        // Final update to campaign status based on delivery statuses
        $deliverySummary = $campaign->getDeliveryStatusSummary();

        // Check if processing is complete (no pending or processing messages)
        $isProcessingComplete = $deliverySummary['pending'] === 0 && $deliverySummary['processing'] === 0;

        if ($isProcessingComplete) {
            // Processing is complete - check if there are failed messages
            if ($deliverySummary['failed'] > 0) {
                // If there are failed messages, mark as failed
                // Only mark as completed if sent messages significantly outnumber failed (e.g., >80% success rate)
                $totalProcessed = $deliverySummary['sent'] + $deliverySummary['failed'];
                if ($totalProcessed > 0) {
                    $successRate = ($deliverySummary['sent'] / $totalProcessed) * 100;
                    // If success rate is less than 80%, mark as failed
                    if ($successRate < 80) {
                        $finalStatus = 'failed';
                    } else {
                        // High success rate, mark as completed
                        $finalStatus = 'completed';
                    }
                } else {
                    // No processed messages, mark as failed
                    $finalStatus = 'failed';
                }
            } else {
                // No failed messages, mark as completed
                $finalStatus = 'completed';
            }
        } else {
            // Still has pending or processing messages
            $finalStatus = 'processing';
        }

        $campaign->update([
            'status' => $finalStatus
        ]);

        // Update used message count
        if ($successCount > 0) {
            $activePlan->increment('usedMsgCount', $successCount);
            $activePlan->increment('monthlyUsedMsgCount', $successCount);
        }

        Log::info("Batch processing completed", [
            'campaign_id' => $campaign->id,
            'total_contacts' => $totalContacts,
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'final_status' => $finalStatus
        ]);
    }

    /**
     * Update campaign status based on delivery statuses
     */
    private function updateCampaignStatus($campaign)
    {
        $deliverySummary = $campaign->getDeliveryStatusSummary();

        // Check if processing is complete (no pending or processing messages)
        $isProcessingComplete = ($deliverySummary['pending'] ?? 0) === 0 && ($deliverySummary['processing'] ?? 0) === 0;

        if ($isProcessingComplete) {
            // Count all successfully sent/delivered/read messages
            $successfulCount = ($deliverySummary['sent'] ?? 0) + ($deliverySummary['delivered'] ?? 0) + ($deliverySummary['read'] ?? 0);
            $failedCount = (int) ($deliverySummary['failed'] ?? 0);

            if ($failedCount > 0) {
                $totalProcessed = $successfulCount + $failedCount;
                if ($totalProcessed > 0) {
                    $successRate = ($successfulCount / $totalProcessed) * 100;
                    // If success rate is less than 80%, mark as failed
                    if ($successRate < 80) {
                        $status = 'failed';
                    } else {
                        // High success rate, mark as completed
                        $status = 'completed';
                    }
                } else {
                    // No processed messages, mark as failed
                    $status = 'failed';
                }
            } else {
                // No failed messages, mark as completed
                $status = 'completed';
            }
        } else {
            // Still has pending or processing messages
            $status = 'processing';
        }

        $campaign->update(['status' => $status]);

        Log::info("Campaign status updated", [
            'campaign_id' => $campaign->id,
            'new_status' => $status,
            'delivery_summary' => $deliverySummary
        ]);
    }

    /**
     * Update batch processing settings
     */
    public function updateBatchSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'batch_size' => 'required|integer|min:1|max:100',
            'batch_delay_seconds' => 'required|integer|min:0|max:60',
            'enable_batch_processing' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $settings = \App\Models\Settings::where('isActive', true)->first();

            if (!$settings) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active settings found'
                ], 404);
            }

            $settings->update([
                'batch_size' => $request->batch_size,
                'batch_delay_seconds' => $request->batch_delay_seconds,
                'enable_batch_processing' => $request->enable_batch_processing,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Batch processing settings updated successfully',
                'data' => [
                    'batch_size' => $settings->batch_size,
                    'batch_delay_seconds' => $settings->batch_delay_seconds,
                    'enable_batch_processing' => $settings->enable_batch_processing,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get batch processing settings
     */
    public function getBatchSettings(Request $request)
    {
        try {
            $settings = \App\Models\Settings::where('isActive', true)->first();

            if (!$settings) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active settings found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'batch_size' => $settings->batch_size ?? 50,
                    'batch_delay_seconds' => $settings->batch_delay_seconds ?? 2,
                    'enable_batch_processing' => $settings->enable_batch_processing ?? true,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign statistics
     */
    public function getStats(Request $request, $id)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $bulkMessage = BulkMessage::where('userId', $userId)->find($id);

            if (!$bulkMessage) {
                return response()->json([
                    'status' => false,
                    'message' => 'Bulk message not found'
                ], 404);
            }

            $sentStatus = $bulkMessage->sentStatus ?? [];
            $sentCount = collect($sentStatus)->where('status', 'SENT')->count();
            $failedCount = collect($sentStatus)->where('status', 'FAILED')->count();
            $totalContacts = count($bulkMessage->contacts ?? []);

            $stats = [
                'total_contacts' => $totalContacts,
                'sent' => $sentCount,
                'failed' => $failedCount,
                'pending' => $totalContacts - $sentCount - $failedCount,
                'success_rate' => $totalContacts > 0 ? round(($sentCount / $totalContacts) * 100, 2) : 0
            ];

            return response()->json([
                'status' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get delivery status for a specific campaign
     */
    public function getDeliveryStatus($id)
    {
        try {
            $userId = request()->user()->getCompanyId();

            $campaign = BulkMessage::where('id', $id)
                ->where('userId', $userId)
                ->with(['template:id,name', 'deliveryStatuses.contact'])
                ->first();

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            $deliveryStatuses = $campaign->deliveryStatuses()
                ->with(['contact', 'bulkMessage.template:id,name'])
                ->get();

            $summary = $campaign->getDeliveryStatusSummary();

            // Detect if any delivery has Meta payment issues
            $paymentIssueStatus = $deliveryStatuses->first(function ($ds) {
                $err = (string) $ds->error_message;
                $rawMeta = json_encode($ds->metadata);
                return ($ds->metadata['payment_required'] ?? false) === true
                    || str_contains($err, '131042')
                    || str_contains($err, 'unsettled payments')
                    || str_contains($err, 'Business eligibility payment issue')
                    || str_contains($rawMeta, '131042')
                    || str_contains($rawMeta, 'unsettled payments');
            });

            $paymentAlert = null;
            if ($paymentIssueStatus) {
                $billingUrl = $paymentIssueStatus->metadata['billing_url'] ?? null;
                if (!$billingUrl && isset($paymentIssueStatus->metadata['error'])) {
                    $errObj = is_array($paymentIssueStatus->metadata['error']) && isset($paymentIssueStatus->metadata['error'][0])
                        ? $paymentIssueStatus->metadata['error'][0]
                        : $paymentIssueStatus->metadata['error'];
                    $billingUrl = $errObj['href'] ?? null;
                    if (!$billingUrl && isset($errObj['error_data']['details'])) {
                        if (preg_match('/https:\/\/[^\s]+/', $errObj['error_data']['details'], $m)) {
                            $billingUrl = rtrim($m[0], '."\'');
                        }
                    }
                }
                if (!$billingUrl && is_string($paymentIssueStatus->error_message)) {
                    if (preg_match('/https:\/\/[^\s]+/', $paymentIssueStatus->error_message, $m)) {
                        $billingUrl = rtrim($m[0], '."\'');
                    }
                }

                $billingUrl = $billingUrl ?: 'https://business.facebook.com/billing_hub';

                $paymentAlert = [
                    'has_payment_issue' => true,
                    'title' => 'Meta WhatsApp Payment Required',
                    'message' => 'Message delivery failed because your WhatsApp Business Account has unsettled payments on Meta (Facebook). Please pay your bill on Meta Billing Hub to resume messaging.',
                    'billing_url' => $billingUrl,
                    'action_text' => 'Pay Now on Facebook / Meta',
                ];
            }

            // Detect Meta ecosystem engagement protection limits (Code 131049)
            $engagementIssueStatus = $deliveryStatuses->first(function ($ds) {
                $err = strtolower((string) ($ds->display_error ?: $ds->error_message));
                return str_contains($err, 'ecosystem engagement') || str_contains($err, '131049');
            });

            $engagementAlert = null;
            if ($engagementIssueStatus) {
                $engagementAlert = [
                    'has_engagement_issue' => true,
                    'title' => 'Meta Ecosystem Engagement Protection',
                    'message' => 'Some messages were not delivered by Meta to maintain ecosystem engagement. Meta applies this when recipients have low recent interaction with WhatsApp marketing or daily marketing frequency thresholds are reached.',
                ];
            }

            $hasWebhookUpdates = ($summary['delivered'] + $summary['read'] + $summary['failed']) > 0;
            $userWebhookUrl = url("/api/whatsapp/webhook/{$userId}");
            $globalWebhookUrl = url('/api/whatsapp/webhook');

            $webhookInfo = [
                'has_webhook_updates' => $hasWebhookUpdates,
                'user_webhook_url' => $userWebhookUrl,
                'global_webhook_url' => $globalWebhookUrl,
                'status_note' => (!$hasWebhookUpdates && $summary['sent'] > 0)
                    ? 'Messages sent to WhatsApp Cloud. If status stays on "Sent" and does not change to Delivered/Read, verify that Meta Developer Portal webhook callback is configured and subscribed to "messages".'
                    : 'Webhook delivery status receipts are active.',
            ];

            return response()->json([
                'status' => true,
                'data' => [
                    'campaign' => $campaign,
                    'delivery_statuses' => $deliveryStatuses,
                    'summary' => $summary,
                    'payment_alert' => $paymentAlert,
                    'engagement_alert' => $engagementAlert,
                    'webhook_info' => $webhookInfo,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get delivery status for a specific contact in a campaign
     */
    public function getContactDeliveryStatus($campaignId, $contactId)
    {
        try {
            $userId = request()->user()->getCompanyId();

            $campaign = BulkMessage::where('id', $campaignId)
                ->where('userId', $userId)
                ->first();

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            $deliveryStatus = MessageDeliveryStatus::where('bulk_message_id', $campaignId)
                ->where('contact_id', $contactId)
                ->with('contact')
                ->first();

            if (!$deliveryStatus) {
                return response()->json([
                    'status' => false,
                    'message' => 'Delivery status not found for this contact'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $deliveryStatus
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get delivery status summary for a campaign
     */
    public function getDeliveryStatusSummary($id)
    {
        try {
            $userId = request()->user()->getCompanyId();

            $campaign = BulkMessage::where('id', $id)
                ->where('userId', $userId)
                ->first();

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            $summary = $campaign->getDeliveryStatusSummary();

            return response()->json([
                'status' => true,
                'data' => [
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'summary' => $summary
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update campaign status manually
     */
    public function updateCampaignStatusManually($id)
    {
        try {
            $userId = request()->user()->getCompanyId();

            $campaign = BulkMessage::where('id', $id)
                ->where('userId', $userId)
                ->first();

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            $this->updateCampaignStatus($campaign);
            $deliverySummary = $campaign->getDeliveryStatusSummary();

            return response()->json([
                'status' => true,
                'message' => 'Campaign status updated successfully',
                'data' => [
                    'campaign_id' => $campaign->id,
                    'status' => $campaign->status,
                    'delivery_summary' => $deliverySummary
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get campaign status with delivery summary
     */
    public function getCampaignStatus($id)
    {
        try {
            $userId = request()->user()->getCompanyId();

            $campaign = BulkMessage::where('id', $id)
                ->where('userId', $userId)
                ->first();

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            $deliverySummary = $campaign->getDeliveryStatusSummary();

            return response()->json([
                'status' => true,
                'data' => [
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'status' => $campaign->status,
                    'delivery_summary' => $deliverySummary,
                    'created_at' => $campaign->created_at,
                    'updated_at' => $campaign->updated_at
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend messages to failed and pending users for a specific campaign
     */
    public function resendFailedAndPending(Request $request, $id)
    {
        try {
            if (!config('whatsapp.bulk_send_enabled')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Bulk send is currently disabled. No messages will be sent.',
                ], 500);
            }

            $userId = $request->user()->getCompanyId();

            // Find the campaign
            $campaign = BulkMessage::where('id', $id)
                ->where('userId', $userId)
                ->with(['template', 'deliveryStatuses.contact'])
                ->first();

            if (!$campaign) {
                return response()->json([
                    'status' => false,
                    'message' => 'Campaign not found'
                ], 404);
            }

            // Get failed and pending delivery statuses
            $failedAndPendingStatuses = $campaign->deliveryStatuses()
                ->whereIn('status', ['failed', 'pending'])
                ->with('contact')
                ->get();

            if ($failedAndPendingStatuses->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No failed or pending messages found for this campaign'
                ], 400);
            }

            // Check if template is still approved
            if ($campaign->template->status !== 'APPROVED') {
                return response()->json([
                    'status' => false,
                    'message' => 'Template is not approved. Cannot resend messages.'
                ], 400);
            }

            // Check global active package
            $activePlan = ActivePackage::where('status', 1)
                ->with('package')
                ->latest()
                ->first();

            if (!$activePlan) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active plan found. Please purchase a plan first.'
                ], 400);
            }

            // Check if package is still active
            $currentDate = now();
            if ($activePlan->startDate && $currentDate->lt($activePlan->startDate)) {
                $activePlan->update(['status' => ActivePackage::STATUS_UPCOMING]);
                return response()->json([
                    'status' => false,
                    'message' => 'Your package has not started yet. It will become active on ' . $activePlan->startDate->toDateString() . '.'
                ], 400);
            }
            if ($activePlan->endDate && $currentDate->gt($activePlan->endDate)) {
                $activePlan->update(['status' => 0]);
                return response()->json([
                    'status' => false,
                    'message' => 'Your package has expired. Please renew your plan.'
                ], 400);
            }

            $activePlan->checkAndPerformMonthlyReset();
            $allowedMsgCount = $activePlan->resolvedMessageLimit();
            $usedMsgCount = $activePlan->is_yearly 
                ? (int) ($activePlan->monthlyUsedMsgCount ?? 0)
                : (int) ($activePlan->usedMsgCount ?? 0);
            $remainingMsg = max(0, $allowedMsgCount - $usedMsgCount);

            if ($failedAndPendingStatuses->count() > $remainingMsg) {
                return response()->json([
                    'status' => false,
                    'message' => "Your plan allows only {$remainingMsg} more messages. You are trying to resend {$failedAndPendingStatuses->count()} messages. Please upgrade your plan."
                ], 403);
            }

            // Reset failed and pending statuses back to pending for resend
            // The ProcessPendingBulkMessages command will process them in small batches to avoid timeouts
            foreach ($failedAndPendingStatuses as $deliveryStatus) {
                $deliveryStatus->updateStatus('pending', [
                    'resend' => true,
                    'resend_at' => now()->toISOString()
                ]);
            }

            // Update campaign status to pending and CLEAR scheduleAt so ProcessPendingBulkMessages picks it up
            // (ProcessPendingBulkMessages excludes campaigns with scheduleAt set)
            $campaign->update([
                'status' => 'pending',
                'scheduleAt' => null  // Clear schedule so it processes immediately via ProcessPendingBulkMessages
            ]);

            Log::info("Resend queued for processing", [
                'campaign_id' => $campaign->id,
                'total_failed_pending' => $failedAndPendingStatuses->count(),
                'message' => 'Messages will be processed by ProcessPendingBulkMessages command in small batches'
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Campaign messages queued for resend. They will be processed in small batches to avoid timeouts.',
                'data' => [
                    'campaign_id' => $campaign->id,
                    'campaign_name' => $campaign->name,
                    'total_queued' => $failedAndPendingStatuses->count(),
                    'status' => 'pending'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Resend process failed", [
                'campaign_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Resend process failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Read Meta hub.* query params (dots or underscores).
     */
    private function hubParams(Request $request): array
    {
        return [
            'mode' => $request->query('hub_mode', $request->query('hub.mode')),
            'challenge' => $request->query('hub_challenge', $request->query('hub.challenge')),
            'verify_token' => $request->query('hub_verify_token', $request->query('hub.verify_token')),
        ];
    }

    /**
     * Global / default WhatsApp webhook verify token.
     */
    private function globalVerifyToken(): string
    {
        return (string) (config('whatsapp.webhook_verify_token') ?: env('WHATSAPP_HOOK_VERIFY_TOKEN', 'test'));
    }

    /**
     * WhatsApp webhook verification
     */
    public function webhookVerification(Request $request)
    {
        $hub = $this->hubParams($request);
        $hubMode = $hub['mode'];
        $hubChallenge = $hub['challenge'];
        $hubVerifyToken = $hub['verify_token'];

        Log::info('Webhook verification attempt:', [
            'hub_mode' => $hubMode,
            'hub_challenge' => $hubChallenge,
            'hub_verify_token' => $hubVerifyToken,
            'all_params' => $request->all()
        ]);

        $fixedToken = $this->globalVerifyToken();

        if ($hubMode === 'subscribe' && ($hubVerifyToken === $fixedToken || $hubVerifyToken === 'test')) {
            Log::info('Webhook verification successful, returning challenge: ' . $hubChallenge);
            return response($hubChallenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Webhook verification failed:', [
            'expected_mode' => 'subscribe',
            'received_mode' => $hubMode,
            'expected_token' => $fixedToken,
            'received_token' => $hubVerifyToken
        ]);

        return response()->json([
            'status' => false,
            'message' => 'Webhook verification failed'
        ], 403);
    }

    /**
     * Handle incoming WhatsApp webhook events (both verification and messages)
     */
    public function handleWebhook(Request $request, WhatsAppWebhookForwarder $webhookForwarder)
    {
        $webhookForwarder->forward($request);

        // Handle webhook verification (GET request)
        if ($request->isMethod('GET')) {
            $hub = $this->hubParams($request);
            $hubMode = $hub['mode'];
            $hubChallenge = $hub['challenge'];
            $hubVerifyToken = $hub['verify_token'];

            Log::info('Webhook verification attempt:', [
                'hub_mode' => $hubMode,
                'hub_challenge' => $hubChallenge,
                'hub_verify_token' => $hubVerifyToken,
                'all_params' => $request->all()
            ]);

            $fixedToken = $this->globalVerifyToken();

            if ($hubMode === 'subscribe' && ($hubVerifyToken === $fixedToken || $hubVerifyToken === 'test')) {
                Log::info('Webhook verification successful, returning challenge: ' . $hubChallenge);
                return response($hubChallenge, 200)->header('Content-Type', 'text/plain');
            }

            Log::warning('Webhook verification failed:', [
                'expected_mode' => 'subscribe',
                'received_mode' => $hubMode,
                'expected_token' => $fixedToken,
                'received_token' => $hubVerifyToken
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Webhook verification failed'
            ], 403);
        }

        // Handle incoming messages (POST request)
        try {
            $body = $request->all();
            Log::info('WhatsApp Webhook received: ' . json_encode($body, JSON_UNESCAPED_SLASHES));

            // Debug: Log the raw request
            Log::info('Raw request data:', [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'headers' => $request->headers->all(),
                'body' => $body
            ]);

            // Process webhook data
            if (isset($body['entry'])) {
                foreach ($body['entry'] as $entry) {
                    if (isset($entry['changes'])) {
                        foreach ($entry['changes'] as $change) {
                            // Process incoming messages
                            if (isset($change['value']['messages'])) {
                                $this->processIncomingMessages($change['value']['messages'], $change['value'], null);
                            }
                            
                            // Process status updates (sent, delivered, read, failed)
                            if (isset($change['value']['statuses'])) {
                                $this->processStatusUpdates($change['value']['statuses']);
                            }
                        }
                    }
                }
            }

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook processing error: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle incoming user-specific WhatsApp webhook events (/whatsapp/webhook/{user_id})
     * Verifies with user-wise set token.
     */
    public function handleWebhookUserWise(Request $request, WhatsAppWebhookForwarder $webhookForwarder, $user_id)
    {
        $webhookForwarder->forward($request);

        // Handle webhook verification (GET request)
        if ($request->isMethod('GET')) {
            $hub = $this->hubParams($request);
            $hubMode = $hub['mode'];
            $hubChallenge = $hub['challenge'];
            $hubVerifyToken = $hub['verify_token'];

            Log::info('User-wise Webhook verification attempt:', [
                'user_id' => $user_id,
                'hub_mode' => $hubMode,
                'hub_challenge' => $hubChallenge,
                'hub_verify_token' => $hubVerifyToken,
                'all_params' => $request->all()
            ]);

            $fixedToken = $this->globalVerifyToken();
            $isValidToken = ($hubVerifyToken === $fixedToken || $hubVerifyToken === 'test');

            // Also accept this user's webhook_secret if set
            if ($user_id) {
                try {
                    $rawId = str_replace('usr_', '', (string) $user_id);
                    $userSettings = Settings::where('userId', $rawId)->first();
                    if ($userSettings && !empty($userSettings->webhook_secret)
                        && $hubVerifyToken === $userSettings->webhook_secret) {
                        $isValidToken = true;
                    }
                } catch (\Throwable $e) {
                    Log::warning('User webhook_secret lookup failed: ' . $e->getMessage());
                }
            }

            if ($hubMode === 'subscribe' && $isValidToken) {
                Log::info('User-wise Webhook verification successful for user [' . $user_id . '], returning challenge: ' . $hubChallenge);
                return response($hubChallenge, 200)->header('Content-Type', 'text/plain');
            }

            Log::warning('User-wise Webhook verification failed:', [
                'user_id' => $user_id,
                'expected_mode' => 'subscribe',
                'received_mode' => $hubMode,
                'received_token' => $hubVerifyToken
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Webhook verification failed for user ' . $user_id
            ], 403);
        }

        // Handle incoming messages (POST request)
        try {
            $body = $request->all();
            Log::info('User-wise WhatsApp Webhook received for user [' . $user_id . ']: ' . json_encode($body, JSON_UNESCAPED_SLASHES));

            Log::info('Raw request data:', [
                'user_id' => $user_id,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'headers' => $request->headers->all(),
                'body' => $body
            ]);

            // Process webhook data
            if (isset($body['entry'])) {
                foreach ($body['entry'] as $entry) {
                    if (isset($entry['changes'])) {
                        foreach ($entry['changes'] as $change) {
                            // Process incoming messages
                            if (isset($change['value']['messages'])) {
                                $this->processIncomingMessages($change['value']['messages'], $change['value'], $user_id);
                            }
                            
                            // Process status updates (sent, delivered, read, failed)
                            if (isset($change['value']['statuses'])) {
                                $this->processStatusUpdates($change['value']['statuses']);
                            }
                        }
                    }
                }
            }

            return response()->json(['status' => 'success', 'userId' => $user_id]);
        } catch (\Exception $e) {
            Log::error('User-wise WhatsApp webhook processing error for user [' . $user_id . ']: ' . $e->getMessage());
            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Resolve which company/admin owns this inbound WhatsApp webhook payload.
     */
    private function resolveWebhookOwnerUserId(array $value, $routeUserId = null): ?int
    {
        if ($routeUserId !== null && $routeUserId !== '') {
            $raw = str_replace('usr_', '', (string) $routeUserId);
            if (is_numeric($raw) && User::where('id', $raw)->exists()) {
                return (int) $raw;
            }
        }

        $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
        if (! empty($phoneNumberId)) {
            $settings = Settings::where('phoneNumberId', (string) $phoneNumberId)->first();
            if ($settings && $settings->userId) {
                return (int) $settings->userId;
            }
        }

        return null;
    }

    /**
     * Process incoming messages
     */
    private function processIncomingMessages($messages, $value, $routeUserId = null)
    {
        $ownerUserId = $this->resolveWebhookOwnerUserId(is_array($value) ? $value : [], $routeUserId);

        if (! $ownerUserId) {
            Log::error('Inbound WhatsApp message ignored: could not resolve owner user from webhook', [
                'route_user_id' => $routeUserId,
                'phone_number_id' => $value['metadata']['phone_number_id'] ?? null,
            ]);
        }

        foreach ($messages as $message) {
            try {
                $phoneNumber = $message['from'];
                $messageId = $message['id'];
                $timestamp = $message['timestamp'];
                $type = $message['type'];

                if ($type === 'reaction') {
                    $targetWamid = $message['reaction']['message_id'] ?? null;
                    $emoji = $message['reaction']['emoji'] ?? null;

                    if ($targetWamid) {
                        app(WhatsAppInboxService::class)->applyIncomingReaction(
                            $phoneNumber,
                            $targetWamid,
                            filled($emoji) ? $emoji : null,
                            $messageId
                        );
                    }

                    continue;
                }

                $content = $this->extractMessageContent($message, $type);

                // Find or create conversation for THIS WhatsApp account owner only
                $conversation = $this->findOrCreateConversation($phoneNumber, $ownerUserId);

                if (!$conversation) {
                    Log::warning("Could not create conversation for phone: {$phoneNumber}", [
                        'owner_user_id' => $ownerUserId,
                    ]);
                    continue;
                }

                // Map to allowed MySQL enum type: ['text', 'image', 'video', 'audio', 'document', 'location', 'contact', 'sticker']
                $dbAllowedTypes = ['text', 'image', 'video', 'audio', 'document', 'location', 'contact', 'sticker'];
                $safeType = in_array($type, $dbAllowedTypes) ? $type : 'text';

                $mediaUrl = $this->extractMediaUrl($message, $type);
                $mediaType = $this->extractMediaType($message, $type);

                // Enrich with catalog product image if mediaUrl is missing
                if (empty($mediaUrl)) {
                    if ($type === 'order' && !empty($message['order'])) {
                        $enrichedOrder = app(\App\Services\WhatsAppCatalogService::class)->enrichOrderData($message['order'], $ownerUserId ?: (int) $conversation->user_id);
                        $message['order'] = $enrichedOrder;
                        if (!empty($enrichedOrder['image_url'])) {
                            $mediaUrl = $enrichedOrder['image_url'];
                            $mediaType = 'image/jpeg';
                        }
                    } elseif (!empty($message['context']['referred_product'])) {
                        $enrichedRef = app(\App\Services\WhatsAppCatalogService::class)->enrichReferredProduct($message['context']['referred_product'], $ownerUserId ?: (int) $conversation->user_id);
                        $message['context']['referred_product'] = $enrichedRef;
                        if (!empty($enrichedRef['image_url'])) {
                            $mediaUrl = $enrichedRef['image_url'];
                            $mediaType = 'image/jpeg';
                        }
                    }
                }

                // Create message record (firstOrCreate prevents duplicate entry SQL errors during Meta webhook retries)
                Message::firstOrCreate(
                    ['whatsapp_message_id' => $messageId],
                    [
                        'conversation_id' => $conversation->id,
                        'direction' => 'inbound',
                        'type' => $safeType,
                        'content' => $content,
                        'media_url' => $mediaUrl,
                        'media_type' => $mediaType,
                        'media_filename' => $this->extractMediaFilename($message, $type),
                        'metadata' => array_merge($message, ['raw_type' => $type]),
                        'status' => 'delivered',
                        'whatsapp_timestamp' => date('Y-m-d H:i:s', $timestamp),
                        'is_read' => false
                    ]
                );

                app(WhatsAppInboxService::class)->recordIncomingWebhookMessage(
                    $phoneNumber,
                    $messageId,
                    $type,
                    $content,
                    $mediaUrl,
                    $mediaType,
                    (int) $timestamp,
                    $message,
                    $ownerUserId ?: (int) $conversation->user_id
                );

                // Update conversation
                $conversation->update([
                    'last_message_at' => now(),
                    'last_message_preview' => substr($content, 0, 100),
                    'is_unread' => true
                ]);

                // Auto AI reply using THIS conversation owner's WhatsApp credentials only
                app(AutoAiReplyService::class)->maybeAutoReply(
                    $conversation,
                    (string) $content,
                    (string) $type,
                    $messageId,
                    $message
                );

                // Send FCM push notification to the WhatsApp account owner
                $senderName = $conversation->contact_name ?? $phoneNumber;
                $preview    = $type === 'text' ? substr((string) $content, 0, 100) : '';
                SendChatMessageNotification::dispatch(
                    (int) $conversation->user_id,
                    (int) $conversation->id,
                    $senderName,
                    (string) $phoneNumber,
                    '0', // default to 0, can be wired to actual AI setting later if needed
                    $preview,
                    $type
                );

                Log::info("Processed inbound message from {$phoneNumber}", [
                    'owner_user_id' => $conversation->user_id,
                    'conversation_id' => $conversation->id,
                ]);

            } catch (\Exception $e) {
                Log::error("Error processing message: " . $e->getMessage());
            }
        }
    }

    /**
     * Process status updates from WhatsApp webhook
     * Handles: sent, delivered, read, failed statuses
     */
    private function processStatusUpdates($statuses)
    {
        foreach ($statuses as $status) {
            try {
                $messageId = $status['id'] ?? null;
                $statusType = $status['status'] ?? null; // sent, delivered, read, failed
                $recipientId = $status['recipient_id'] ?? null;
                $timestamp = $status['timestamp'] ?? null;
                $error = $status['errors'] ?? null;

                if (!$messageId || !$statusType) {
                    Log::warning('Status update missing required fields:', $status);
                    continue;
                }

                // Map WhatsApp status to our status
                $mappedStatus = $this->mapWhatsAppStatus($statusType);

                if (!$mappedStatus) {
                    Log::warning("Unknown status type received: {$statusType}", $status);
                    continue;
                }

                // Find delivery row by WhatsApp message id (column or metadata fallback)
                $deliveryStatus = MessageDeliveryStatus::where('whatsapp_message_id', $messageId)->first();

                if (!$deliveryStatus) {
                    $deliveryStatus = MessageDeliveryStatus::where('metadata->whatsapp_message_id', $messageId)->first();
                }

                // Fallback: match by recipient phone + recent sent/processing row
                if (!$deliveryStatus && $recipientId) {
                    $digits = preg_replace('/\D+/', '', (string) $recipientId);
                    $deliveryStatus = MessageDeliveryStatus::whereIn('status', ['sent', 'processing', 'pending'])
                        ->whereHas('contact', function ($q) use ($digits, $recipientId) {
                            $q->where('phone', $recipientId)
                                ->orWhere('phone', 'like', '%' . substr($digits, -10));
                        })
                        ->where(function ($q) {
                            $q->whereNull('whatsapp_message_id')->orWhere('whatsapp_message_id', '');
                        })
                        ->orderByDesc('id')
                        ->first();

                    if ($deliveryStatus) {
                        $deliveryStatus->whatsapp_message_id = $messageId;
                        $deliveryStatus->save();
                        Log::info('Linked delivery status via recipient fallback', [
                            'delivery_id' => $deliveryStatus->id,
                            'message_id' => $messageId,
                            'recipient_id' => $recipientId,
                        ]);
                    }
                }

                if (!$deliveryStatus) {
                    Log::warning("Delivery status not found for message_id: {$messageId}", [
                        'message_id' => $messageId,
                        'status' => $statusType,
                        'recipient_id' => $recipientId
                    ]);
                    continue;
                }

                // Prepare metadata
                $metadata = array_merge(
                    is_array($deliveryStatus->metadata) ? $deliveryStatus->metadata : [],
                    [
                        'whatsapp_status' => $statusType,
                        'whatsapp_message_id' => $messageId,
                        'recipient_id' => $recipientId,
                        'timestamp' => $timestamp,
                        'updated_at' => now()->toISOString()
                    ]
                );

                $isPaymentIssue = false;
                $paymentBillingUrl = null;

                if ($error) {
                    $metadata['error'] = $error;
                    $paymentInfo = $this->parsePaymentIssue($error);

                    if ($paymentInfo['is_payment_issue']) {
                        $isPaymentIssue = true;
                        $paymentBillingUrl = $paymentInfo['billing_url'];
                        $metadata['payment_required'] = true;
                        $metadata['billing_url'] = $paymentBillingUrl;
                        $metadata['error_message'] = "Payment required on Meta: Message delivery failed because your WhatsApp Business account has unsettled payments. Visit Meta Billing Hub to resolve: " . $paymentBillingUrl;
                        $deliveryStatus->error_message = $metadata['error_message'];
                    } else {
                        $formattedError = WhatsAppService::formatMetaErrorMessage($error);
                        $metadata['error_message'] = $formattedError;
                        $deliveryStatus->error_message = $formattedError;
                    }

                    Log::error("❌ Meta Webhook failure for message {$messageId} (recipient {$recipientId}): " . json_encode($error, JSON_UNESCAPED_SLASHES));
                }

                // Prevent delayed out-of-order webhooks from reverting status (e.g., sent arriving after delivered or read)
                $statusPrecedence = [
                    'pending' => 1,
                    'processing' => 2,
                    'sent' => 3,
                    'delivered' => 4,
                    'read' => 5,
                    'failed' => 6,
                ];
                $currentPrecedence = $statusPrecedence[strtolower((string) $deliveryStatus->status)] ?? 0;
                $newPrecedence = $statusPrecedence[strtolower((string) $mappedStatus)] ?? 0;

                if ($newPrecedence >= $currentPrecedence || $mappedStatus === 'failed') {
                    $deliveryStatus->updateStatus($mappedStatus, $metadata);
                } else {
                    if ($metadata) {
                        $existing = is_array($deliveryStatus->metadata) ? $deliveryStatus->metadata : [];
                        $deliveryStatus->metadata = array_merge($existing, $metadata);
                        $deliveryStatus->save();
                    }
                }

                // Deduct wallet on sent/delivered/read for wallet-enabled users (idempotent)
                if (in_array($mappedStatus, ['sent', 'delivered', 'read'], true)) {
                    try {
                        $chargeResult = app(WalletService::class)->chargeForDelivery(
                            $deliveryStatus->fresh(),
                            $mappedStatus
                        );
                        Log::info('Wallet charge result after delivery status', [
                            'delivery_id' => $deliveryStatus->id,
                            'message_id' => $messageId,
                            'result' => $chargeResult,
                        ]);
                    } catch (\Throwable $walletEx) {
                        Log::error('Wallet charge on delivery failed: ' . $walletEx->getMessage(), [
                            'delivery_id' => $deliveryStatus->id,
                            'message_id' => $messageId,
                            'trace' => $walletEx->getTraceAsString(),
                        ]);
                    }
                } elseif ($mappedStatus === 'failed') {
                    try {
                        $refundResult = app(WalletService::class)->refundForFailedDelivery(
                            $deliveryStatus->fresh()
                        );
                        if ($refundResult) {
                            Log::info('Wallet refund result after delivery failure', [
                                'delivery_id' => $deliveryStatus->id,
                                'message_id' => $messageId,
                                'result' => $refundResult,
                            ]);
                        }
                    } catch (\Throwable $walletEx) {
                        Log::error('Wallet refund on delivery failure failed: ' . $walletEx->getMessage(), [
                            'delivery_id' => $deliveryStatus->id,
                            'message_id' => $messageId,
                            'trace' => $walletEx->getTraceAsString(),
                        ]);
                    }
                }

                Log::info("✅ Status updated for message", [
                    'message_id' => $messageId,
                    'new_status' => $mappedStatus,
                    'whatsapp_status' => $statusType,
                    'campaign_id' => $deliveryStatus->bulk_message_id,
                    'contact_id' => $deliveryStatus->contact_id
                ]);

                // Update campaign status if needed
                if ($deliveryStatus->bulkMessage) {
                    $this->updateCampaignStatus($deliveryStatus->bulkMessage);

                    // If Meta payment issue detected, alert user via in-app Notification
                    if ($isPaymentIssue) {
                        $campaignUserId = $deliveryStatus->bulkMessage->userId;
                        if ($campaignUserId) {
                            try {
                                $hasRecent = \App\Models\Notification::where('userId', $campaignUserId)
                                    ->where('title', 'like', '%Meta%Payment%')
                                    ->where('created_at', '>=', now()->subHours(6))
                                    ->exists();

                                if (!$hasRecent) {
                                    \App\Models\Notification::create([
                                        'userId' => $campaignUserId,
                                        'title' => 'Meta WhatsApp Payment Required',
                                        'message' => 'Message delivery failed because your WhatsApp Business Account has unsettled payments on Meta (Facebook). Visit Meta Billing Hub to pay your bill: ' . ($paymentBillingUrl ?? 'https://business.facebook.com/billing_hub'),
                                        'type' => 'error',
                                        'isRead' => false,
                                    ]);
                                    Log::warning("⚠️ Created Meta payment alert notification for user {$campaignUserId}");
                                }
                            } catch (\Throwable $notifEx) {
                                Log::error("Failed to create payment alert notification: " . $notifEx->getMessage());
                            }
                        }
                    }
                }

            } catch (\Throwable $e) {
                Log::error("Error processing status update: " . $e->getMessage(), [
                    'status' => $status,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Map WhatsApp status to our internal status
     */
    private function mapWhatsAppStatus($whatsappStatus)
    {
        $statusMap = [
            'sent' => 'sent',
            'delivered' => 'delivered',
            'read' => 'read',
            'failed' => 'failed'
        ];

        return $statusMap[strtolower($whatsappStatus)] ?? null;
    }

    /**
     * Check if an error represents a Meta payment / billing issue
     */
    private function parsePaymentIssue($error): array
    {
        $firstError = is_array($error) && isset($error[0]) ? $error[0] : (is_array($error) ? $error : null);
        $errorCode = $firstError['code'] ?? null;
        $errorTitle = (string) ($firstError['title'] ?? '');
        $errorMsg = (string) ($firstError['message'] ?? (is_string($error) ? $error : ''));
        $errorDetails = (string) ($firstError['error_data']['details'] ?? '');
        $errorHref = $firstError['href'] ?? null;

        if (!$errorHref && !empty($errorDetails)) {
            if (preg_match('/https:\/\/[^\s]+/', $errorDetails, $matches)) {
                $errorHref = rtrim($matches[0], '.');
            }
        }

        $allText = strtolower($errorTitle . ' ' . $errorMsg . ' ' . $errorDetails);

        $isPayment = ($errorCode == 131042 || $errorCode == 131031)
            || str_contains($allText, 'payment')
            || str_contains($allText, 'unsettled payment')
            || str_contains($allText, 'business eligibility payment issue');

        return [
            'is_payment_issue' => $isPayment,
            'billing_url' => $errorHref ?: 'https://business.facebook.com/billing_hub',
            'error_details' => $errorDetails ?: ($errorMsg ?: (is_string($error) ? $error : 'Unknown error'))
        ];
    }

    /**
     * Find or create conversation for the WhatsApp account owner only.
     */
    private function findOrCreateConversation($phoneNumber, ?int $ownerUserId = null)
    {
        if (! $ownerUserId) {
            Log::warning('findOrCreateConversation skipped: missing owner user id', [
                'phone' => $phoneNumber,
            ]);

            return null;
        }

        $user = User::find($ownerUserId);
        if (! $user) {
            Log::warning('findOrCreateConversation skipped: owner user not found', [
                'owner_user_id' => $ownerUserId,
                'phone' => $phoneNumber,
            ]);

            return null;
        }

        $conversation = Conversation::where('phone_number', $phoneNumber)
            ->where('user_id', $user->id)
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'whatsapp_id' => 'conv_' . $phoneNumber . '_' . time(),
                'phone_number' => $phoneNumber,
                'contact_name' => null,
                'user_id' => $user->id,
                'status' => 'active',
                'is_unread' => true,
            ]);
        }

        return $conversation;
    }

    /**
     * Extract message content based on type
     */
    private function extractMessageContent($message, $type)
    {
        switch ($type) {
            case 'text':
                $textBody = $message['text']['body'] ?? '';
                $context = $message['context'] ?? [];
                if (!empty($context['referred_product'])) {
                    $ref = $context['referred_product'];
                    $enrichedRef = app(\App\Services\WhatsAppCatalogService::class)->enrichReferredProduct($ref);
                    $name = !empty($enrichedRef['name']) ? $enrichedRef['name'] : (!empty($ref['product_retailer_id']) ? "Product #{$ref['product_retailer_id']}" : 'Product');
                    return "🛍️ [Product Inquiry: {$name}]\n" . $textBody;
                }
                return $textBody;

            case 'order':
                $order = $message['order'] ?? [];
                $enrichedOrder = app(\App\Services\WhatsAppCatalogService::class)->enrichOrderData($order);
                $items = $enrichedOrder['product_items'] ?? ($order['product_items'] ?? []);
                $totalQty = $enrichedOrder['total_items'] ?? 0;
                $totalPrice = $enrichedOrder['total_price'] ?? 0.0;
                $currency = $enrichedOrder['currency'] ?? 'INR';
                $itemLines = [];

                foreach ($items as $item) {
                    $qty = (int) ($item['quantity'] ?? 1);
                    $price = (float) ($item['item_price'] ?? 0);
                    $cur = $item['currency'] ?? 'INR';
                    $sku = $item['product_retailer_id'] ?? 'item';
                    $name = !empty($item['name']) ? $item['name'] : "Product #{$sku}";
                    $itemLines[] = "• {$name} × {$qty} ({$cur} " . number_format($price, 2) . ")";
                }

                $header = "🛒 Order Placed: {$totalQty} item" . ($totalQty > 1 ? 's' : '') . " (Total: {$currency} " . number_format($totalPrice, 2) . ")";
                $note = !empty($order['text']) ? "\nCustomer Note: " . trim($order['text']) : "";
                $itemsBlock = !empty($itemLines) ? "\n" . implode("\n", $itemLines) : "";

                return $header . $itemsBlock . $note;

            case 'interactive':
                $interactive = $message['interactive'] ?? [];
                $subType = $interactive['type'] ?? '';
                if ($subType === 'button_reply') {
                    return $interactive['button_reply']['title'] ?? '[Button Reply]';
                }
                if ($subType === 'list_reply') {
                    $title = $interactive['list_reply']['title'] ?? '';
                    $desc = $interactive['list_reply']['description'] ?? '';
                    return $title . ($desc ? " - {$desc}" : '');
                }
                if ($subType === 'product_list') {
                    return '[Viewed Product List]';
                }
                return '[Interactive response]';

            case 'button':
                return $message['button']['text'] ?? '[Button Click]';

            case 'image':
            case 'video':
            case 'audio':
            case 'document':
                return $message[$type]['caption'] ?? '';

            case 'location':
                $location = $message['location'] ?? [];
                $lat = $location['latitude'] ?? null;
                $lng = $location['longitude'] ?? null;
                $name = trim((string) ($location['name'] ?? ''));
                $address = trim((string) ($location['address'] ?? ''));

                if ($name !== '' || $address !== '') {
                    return trim("Location: {$name}" . ($name !== '' && $address !== '' ? ' - ' : '') . $address);
                }

                if ($lat !== null && $lng !== null) {
                    return "Location: {$lat},{$lng}";
                }

                return 'Shared location';

            case 'contact':
                $contact = $message['contact'];
                return "Contact: {$contact['name']['formatted_name']} - {$contact['phones'][0]['phone']}";

            case 'sticker':
                return '[Sticker]';

            default:
                return '[Unsupported message type]';
        }
    }

    /**
     * Extract media URL
     */
    private function extractMediaUrl($message, $type)
    {
        if (in_array($type, ['image', 'video', 'audio', 'document'])) {
            return $message[$type]['id'] ?? null;
        }
        return null;
    }

    /**
     * Extract media type
     */
    private function extractMediaType($message, $type)
    {
        if (in_array($type, ['image', 'video', 'audio', 'document'])) {
            return $message[$type]['mime_type'] ?? null;
        }
        return null;
    }

    /**
     * Extract media filename
     */
    private function extractMediaFilename($message, $type)
    {
        if (in_array($type, ['image', 'video', 'audio', 'document'])) {
            return $message[$type]['filename'] ?? null;
        }
        return null;
    }
}
