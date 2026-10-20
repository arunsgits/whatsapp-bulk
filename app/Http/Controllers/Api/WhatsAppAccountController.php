<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsappConnectedAccount;
use App\Models\Group;
use App\Models\Contact;
use App\Models\ActivePackage;
use App\Services\WhatsAppSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Exception;

class WhatsAppAccountController extends Controller
{
    protected WhatsAppSessionService $sessionService;

    public function __construct(WhatsAppSessionService $sessionService)
    {
        $this->sessionService = $sessionService;
    }

    /**
     * Get all connected WhatsApp accounts for the authenticated user.
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();

            $accounts = WhatsappConnectedAccount::forUser($userId)
                ->orderBy('created_at', 'desc')
                ->get();

            // Check if bridge service is online
            $isBridgeOnline = $this->sessionService->isServiceOnline();

            return response()->json([
                'status' => true,
                'count' => $accounts->count(),
                'bridge_online' => $isBridgeOnline,
                'data' => $accounts->map(function ($account) {
                    return [
                        'id' => $account->id,
                        'account_name' => $account->account_name,
                        'phone_number' => $account->phone_number,
                        'profile_name' => $account->profile_name,
                        'profile_picture' => $account->profile_picture,
                        'status' => $account->status,
                        'is_connected' => $account->status === 'connected',
                        'last_connected_at' => $account->last_connected_at,
                        'created_at' => $account->created_at,
                    ];
                })
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initialize connection for a new WhatsApp account and generate QR code.
     */
    public function connect(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = $request->user()->getCompanyId();
            $sessionId = 'wa_sess_usr' . $userId . '_' . bin2hex(random_bytes(6));

            $account = WhatsappConnectedAccount::create([
                'userId' => $userId,
                'account_name' => $request->account_name,
                'session_id' => $sessionId,
                'status' => 'initializing',
            ]);

            // Call the Node.js session bridge
            $sessionResult = $this->sessionService->initSession($sessionId);

            if (($sessionResult['status'] ?? '') === 'qr_ready' && !empty($sessionResult['qr'])) {
                $account->update([
                    'status' => 'qr_ready',
                    'qr_code' => $sessionResult['qr']
                ]);
            } elseif (($sessionResult['status'] ?? '') === 'connected') {
                $account->update([
                    'status' => 'connected',
                    'phone_number' => $sessionResult['phone'] ?? null,
                    'profile_name' => $sessionResult['name'] ?? null,
                    'last_connected_at' => now(),
                    'qr_code' => null
                ]);
            }

            return response()->json([
                'status' => true,
                'message' => 'WhatsApp account session initialized. Scan QR code to connect.',
                'data' => [
                    'id' => $account->id,
                    'account_name' => $account->account_name,
                    'session_id' => $account->session_id,
                    'status' => $account->status,
                    'qr_code' => $account->qr_code,
                    'is_connected' => $account->status === 'connected',
                ]
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to initialize WhatsApp connection: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get live connection status & QR code for a specific account.
     */
    public function status(Request $request, $id)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $account = WhatsappConnectedAccount::forUser($userId)->findOrFail($id);

            // If not connected yet or reconnecting, sync status from bridge
            if ($account->status !== 'connected') {
                $sessionStatus = $this->sessionService->getSessionStatus($account->session_id);

                if (($sessionStatus['status'] ?? '') === 'connected') {
                    $account->update([
                        'status' => 'connected',
                        'phone_number' => $sessionStatus['phone'] ?? $account->phone_number,
                        'profile_name' => $sessionStatus['name'] ?? $account->profile_name,
                        'last_connected_at' => now(),
                        'qr_code' => null,
                    ]);
                } elseif (($sessionStatus['status'] ?? '') === 'qr_ready' && !empty($sessionStatus['qr'])) {
                    $account->update([
                        'status' => 'qr_ready',
                        'qr_code' => $sessionStatus['qr'],
                    ]);
                } elseif (($sessionStatus['status'] ?? '') === 'disconnected') {
                    $account->update([
                        'status' => 'disconnected',
                        'qr_code' => null,
                    ]);
                }
            }

            return response()->json([
                'status' => true,
                'data' => [
                    'id' => $account->id,
                    'account_name' => $account->account_name,
                    'phone_number' => $account->phone_number,
                    'profile_name' => $account->profile_name,
                    'status' => $account->status,
                    'is_connected' => $account->status === 'connected',
                    'qr_code' => $account->qr_code,
                    'last_connected_at' => $account->last_connected_at,
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Account not found or error checking status: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Disconnect / Logout a WhatsApp account.
     */
    public function disconnect(Request $request, $id)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $account = WhatsappConnectedAccount::forUser($userId)->findOrFail($id);

            $this->sessionService->deleteSession($account->session_id);

            $account->update([
                'status' => 'disconnected',
                'qr_code' => null,
            ]);

            return response()->json([
                'status' => true,
                'message' => "WhatsApp account '{$account->account_name}' disconnected successfully."
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to disconnect account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a WhatsApp account record completely.
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $account = WhatsappConnectedAccount::forUser($userId)->findOrFail($id);

            $this->sessionService->deleteSession($account->session_id);
            $account->delete();

            return response()->json([
                'status' => true,
                'message' => 'WhatsApp account removed successfully.'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all WhatsApp groups from this connected account.
     */
    public function getGroups(Request $request, $id)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $account = WhatsappConnectedAccount::forUser($userId)->findOrFail($id);

            if ($account->status !== 'connected') {
                return response()->json([
                    'status' => false,
                    'message' => 'This WhatsApp account is not connected. Please scan QR code first.'
                ], 400);
            }

            $result = $this->sessionService->getGroups($account->session_id);

            if (!($result['status'] ?? false)) {
                return response()->json([
                    'status' => false,
                    'message' => $result['message'] ?? 'Failed to fetch groups from WhatsApp'
                ], 400);
            }

            return response()->json([
                'status' => true,
                'account' => [
                    'id' => $account->id,
                    'account_name' => $account->account_name,
                    'phone_number' => $account->phone_number,
                ],
                'total_groups' => count($result['groups'] ?? []),
                'groups' => $result['groups'] ?? []
            ]);
        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error fetching groups: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export members from a selected WhatsApp group into Models\Group and Models\Contact.
     */
    public function exportGroupContacts(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'group_jid' => 'required|string',
            'target_group_name' => 'nullable|string|max:255',
            'target_group_id' => 'nullable|integer',
            'tags' => 'nullable|array',
            'skip_existing' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = $request->user()->getCompanyId();
            $account = WhatsappConnectedAccount::forUser($userId)->findOrFail($id);

            if ($account->status !== 'connected') {
                return response()->json([
                    'status' => false,
                    'message' => 'This WhatsApp account is not connected. Please scan QR code first.'
                ], 400);
            }

            // 1. Fetch participants from the WhatsApp session bridge
            $participantsResult = $this->sessionService->getGroupParticipants($account->session_id, $request->group_jid);

            if (!($participantsResult['status'] ?? false)) {
                return response()->json([
                    'status' => false,
                    'message' => $participantsResult['message'] ?? 'Failed to retrieve group participants'
                ], 400);
            }

            $participants = $participantsResult['participants'] ?? [];
            $groupTitle = $participantsResult['group_name'] ?? 'WhatsApp Group';

            if (empty($participants)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No participants found in the selected WhatsApp group.'
                ], 400);
            }

            // 2. Validate user's plan limit
            $activePackage = ActivePackage::forUser($userId);
            if (!$activePackage) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active package found. Please purchase a package first.'
                ], 400);
            }

            $allowedContactCount = $activePackage->resolvedContactLimit();
            $currentContactCount = Contact::where('userId', $userId)->count();

            // 3. Resolve or Create the Target Group in Models\Group
            $targetGroup = null;
            if ($request->filled('target_group_id')) {
                $targetGroup = Group::where('userId', $userId)->find($request->target_group_id);
            }

            if (!$targetGroup) {
                $groupName = $request->target_group_name ?: $groupTitle;
                $targetGroup = Group::firstOrCreate(
                    [
                        'name' => $groupName,
                        'userId' => $userId,
                    ],
                    [
                        'description' => "Exported from WhatsApp Group via {$account->account_name} ({$account->phone_number})",
                        'status' => 'active',
                    ]
                );
            }

            // 4. Process and Insert/Update Contacts in Models\Contact
            $tags = $request->tags ?? ['group_export', 'whatsapp'];
            $newContactsCount = 0;
            $updatedExistingCount = 0;
            $skippedDueToLimit = 0;

            foreach ($participants as $item) {
                $rawPhone = $item['phone'] ?? '';
                $cleanPhone = preg_replace('/\D+/', '', $rawPhone);

                if (empty($cleanPhone) || strlen($cleanPhone) < 7) {
                    continue; // Skip invalid or system numbers
                }

                // Check if contact already exists for this user
                $existingContact = Contact::where('phone', $cleanPhone)
                    ->where('userId', $userId)
                    ->first();

                if ($existingContact) {
                    // Append this target group to the existing contact's groupIds
                    $currentGroupIds = $existingContact->groupIds ?? [];
                    if (!is_array($currentGroupIds)) {
                        $currentGroupIds = [];
                    }

                    if (!in_array((int)$targetGroup->id, $currentGroupIds)) {
                        $currentGroupIds[] = (int)$targetGroup->id;
                        $existingContact->update(['groupIds' => $currentGroupIds]);
                    }
                    $updatedExistingCount++;
                } else {
                    // Check quota limit before creating new contact
                    if ($currentContactCount >= $allowedContactCount) {
                        $skippedDueToLimit++;
                        continue;
                    }

                    $contactName = !empty($item['name']) ? $item['name'] : $cleanPhone;

                    Contact::create([
                        'name' => $contactName,
                        'phone' => $cleanPhone,
                        'email' => null,
                        'tags' => $tags,
                        'groupIds' => [(int)$targetGroup->id],
                        'userId' => $userId,
                        'status' => 'active',
                    ]);

                    $currentContactCount++;
                    $newContactsCount++;
                    $activePackage->incrementContactUsage(1);
                }
            }

            return response()->json([
                'status' => true,
                'message' => "Group contacts exported successfully into '{$targetGroup->name}'.",
                'data' => [
                    'group' => [
                        'id' => $targetGroup->id,
                        'name' => $targetGroup->name,
                        'description' => $targetGroup->description,
                        'total_contacts_in_group' => $targetGroup->contacts->count(),
                    ],
                    'summary' => [
                        'total_extracted' => count($participants),
                        'new_contacts_created' => $newContactsCount,
                        'already_existing_updated' => $updatedExistingCount,
                        'skipped_due_to_limit' => $skippedDueToLimit,
                    ],
                    'limits' => [
                        'current' => $currentContactCount,
                        'allowed' => $allowedContactCount,
                        'remaining' => max(0, $allowedContactCount - $currentContactCount),
                    ]
                ]
            ]);
        } catch (Exception $e) {
            Log::error("Export group contacts error: {$e->getMessage()}", [
                'account_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Failed to export group contacts: ' . $e->getMessage()
            ], 500);
        }
    }
}
