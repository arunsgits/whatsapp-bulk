<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Template;
use App\Models\Settings;
use App\Models\BulkMessage;
use App\Models\ActivePackage;
use App\Models\MessageDeliveryStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get user-wise dashboard data
     */
    public function getDashboard(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();
            // Default to user-wise dashboard data unless ?global=1 is explicitly passed
            $isGlobal = $request->boolean('global', false) || $request->boolean('all', false);
            $today = now();
            $lastWeek = now()->subDays(6);

            // Total Contacts Count (user-wise by default)
            $totalContacts = $isGlobal
                ? Contact::where('status', 'active')->count()
                : Contact::where('userId', $userId)
                    ->where('status', 'active')
                    ->count();

            // Active templates for this user only (never shared/null-userId rows)
            $settings = $isGlobal ? null : Settings::where('userId', $userId)->first();
            $hasWaba = $settings
                && trim((string) ($settings->waba_id ?? '')) !== ''
                && trim((string) ($settings->access_token ?? '')) !== ''
                && trim((string) ($settings->phoneNumberId ?? '')) !== '';

            $activeTemplatesQuery = Template::where('userId', $userId)
                ->whereIn('status', ['active', 'APPROVED']);
            if (! $hasWaba) {
                $activeTemplatesQuery->where('type', 'custom');
            }
            $activeTemplates = $isGlobal
                ? Template::whereIn('status', ['active', 'APPROVED'])->count()
                : $activeTemplatesQuery->count();

            // Method 1: From BulkMessage.sentStatus arrays (legacy data)
            $messagesQuery = BulkMessage::query();
            if (!$isGlobal) {
                $messagesQuery->where('userId', $userId);
            }
            $messagesData = $messagesQuery->get();

            $legacyMessagesSent = 0;
            $legacyMessagesFailed = 0;
            $legacyMessagesPending = 0;
            $totalMessages = 0;

            foreach ($messagesData as $message) {
                $contactCount = count($message->contactIds ?? []);
                $totalMessages += $contactCount;

                if ($message->sentStatus && !empty($message->sentStatus)) {
                    $legacyMessagesSent += collect($message->sentStatus)->where('status', 'SENT')->count();
                    $legacyMessagesFailed += collect($message->sentStatus)->where('status', 'FAILED')->count();
                    $legacyMessagesPending += collect($message->sentStatus)->where('status', 'PENDING')->count();
                } else {
                    // If no sentStatus, all contacts are pending
                    $legacyMessagesPending += $contactCount;
                }
            }

            // Method 2: From MessageDeliveryStatus table (user-wise accurate data)
            $deliveryStatusQuery = MessageDeliveryStatus::query();
            if (!$isGlobal) {
                $deliveryStatusQuery->whereHas('bulkMessage', function($query) use ($userId) {
                    $query->where('userId', $userId);
                });
            }

            $tableMessagesSent = $deliveryStatusQuery->clone()->whereIn('status', ['sent', 'delivered', 'read'])->count();
            $tableMessagesFailed = $deliveryStatusQuery->clone()->where('status', 'failed')->count();
            $tableMessagesPending = $deliveryStatusQuery->clone()->where('status', 'pending')->count();
            $tableMessagesDelivered = $deliveryStatusQuery->clone()->where('status', 'delivered')->count();
            $tableMessagesRead = $deliveryStatusQuery->clone()->where('status', 'read')->count();

            // Get active package's used message count (Yearly & Monthly)
            if ($isGlobal) {
                $activePlanMessagesSent = ActivePackage::where('status', 1)->sum('usedMsgCount');
                $monthlyMessagesSent = ActivePackage::where('status', 1)->sum('monthlyUsedMsgCount');
            } else {
                $activePackage = ActivePackage::forUser($userId);

                $activePlanMessagesSent = $activePackage ? (int) $activePackage->usedMsgCount : 0;
                $monthlyMessagesSent = $activePackage ? (int) $activePackage->monthlyUsedMsgCount : 0;
            }

            // Yearly used messages based on active package
            $messagesSent = $activePlanMessagesSent;
            $messagesFailed = $tableMessagesFailed;
            $messagesPending = $tableMessagesPending;

            // Calculate delivery rate based on sent vs failed messages
            $totalAttempts = $messagesSent + $messagesFailed + $messagesPending;
            $deliveryRate = $totalAttempts > 0
                ? round(($messagesSent / $totalAttempts) * 100, 1)
                : 0;

            // Message Analytics - Last 7 Days (based on actual sent dates for current user)
            $analyticsQuery = MessageDeliveryStatus::query()
                ->where('sent_at', '>=', $lastWeek)
                ->whereNotNull('sent_at');

            if (!$isGlobal) {
                $analyticsQuery->whereHas('bulkMessage', function($query) use ($userId) {
                    $query->where('userId', $userId);
                });
            }

            $messageAnalytics = $analyticsQuery->get()
                ->groupBy(function ($deliveryStatus) {
                    return $deliveryStatus->sent_at->format('Y-m-d');
                })
                ->map(function ($statuses, $date) {
                    $sentCount = $statuses->whereIn('status', ['sent', 'delivered', 'read'])->count();
                    $failedCount = $statuses->where('status', 'failed')->count();

                    return [
                        'date' => $date,
                        'sent' => $sentCount,
                        'failed' => $failedCount,
                    ];
                });

            // Format analytics data for chart (always 7 days)
            $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            $chartData = [];

            for ($i = 6; $i >= 0; $i--) {
                $date = $today->copy()->subDays($i);
                $dateStr = $date->format('Y-m-d');

                $analytics = $messageAnalytics->get($dateStr, ['sent' => 0, 'failed' => 0]);

                $chartData[] = [
                    'day' => $dayNames[$date->dayOfWeek],
                    'date' => $dateStr,
                    'sent' => $analytics['sent'],
                    'failed' => $analytics['failed'],
                    'total' => $analytics['sent'] + $analytics['failed']
                ];
            }

            // User-wise Active Campaigns
            $campaignsQuery = BulkMessage::query()
                ->with('template')
                ->orderBy('created_at', 'desc')
                ->limit(10);
            if (!$isGlobal) {
                $campaignsQuery->where('userId', $userId);
            }
            $activeCampaigns = $campaignsQuery->get()
                ->map(function ($campaign) {
                    $sentCount = collect($campaign->sentStatus)->where('status', 'SENT')->count();
                    $failedCount = collect($campaign->sentStatus)->where('status', 'FAILED')->count();
                    $totalContacts = count($campaign->contactIds ?? []);
                    $readCount = floor($sentCount * 0.4); // mock read count

                    return [
                        'id' => $campaign->id,
                        'name' => $campaign->name,
                        'templateName' => $campaign->template->name ?? 'Unknown',
                        'status' => $campaign->status,
                        'sent' => $sentCount,
                        'read' => $readCount,
                        'failed' => $failedCount,
                        'total' => $totalContacts,
                        'progress' => $totalContacts > 0 ? round(($sentCount / $totalContacts) * 100) : 0,
                        'createdAt' => $campaign->created_at->toISOString()
                    ];
                });

            // Latest User Template
            $latestTemplate = null;
            if ($isGlobal) {
                $latestTemplate = Template::whereIn('status', ['active', 'APPROVED'])
                    ->orderBy('created_at', 'desc')
                    ->first();
            } else {
                $latestTemplate = Template::where('userId', $userId)
                    ->whereIn('status', ['active', 'APPROVED'])
                    ->orderBy('created_at', 'desc')
                    ->first();
            }

            $latestTemplateDetails = [];
            if ($latestTemplate) {
                $latestTemplateDetails = [[
                    'id' => $latestTemplate->id,
                    'name' => $latestTemplate->name,
                    'language' => $latestTemplate->language ?? 'en_US',
                    'category' => $latestTemplate->category ?? 'UTILITY',
                    'status' => strtoupper($latestTemplate->status),
                    'isCustom' => $latestTemplate->type === 'custom',
                    'components' => $latestTemplate->components ?? [
                        [
                            'type' => 'HEADER',
                            'format' => 'TEXT',
                            'text' => 'Sample Header',
                            'example' => [
                                'header_text' => ['Sample']
                            ]
                        ],
                        [
                            'type' => 'BODY',
                            'text' => 'Sample body text with {{1}} variable',
                            'example' => [
                                'body_text' => [
                                    ['Sample Variable']
                                ]
                            ]
                        ]
                    ],
                    'createdAt' => $latestTemplate->created_at->toISOString()
                ]];
            }

            return response()->json([
                'status' => true,
                'message' => 'Dashboard data fetched successfully',
                'data' => [
                    'stats' => [
                        'totalContacts' => $totalContacts,
                        'activeTemplates' => $activeTemplates,
                        'messagesSent' => $messagesSent, // Used messages count for this user
                        'yearlyUsedMessages' => $messagesSent,
                        'monthlyUsedMessages' => $monthlyMessagesSent,
                        'deliveryRate' => $deliveryRate . '%'
                    ],
                    'debug' => [
                        'legacyData' => [
                            'messagesSent' => $legacyMessagesSent,
                            'messagesFailed' => $legacyMessagesFailed,
                            'messagesPending' => $legacyMessagesPending,
                            'totalMessages' => $totalMessages
                        ],
                        'tableData' => [
                            'messagesSent' => $tableMessagesSent,
                            'messagesFailed' => $tableMessagesFailed,
                            'messagesPending' => $tableMessagesPending,
                            'messagesDelivered' => $tableMessagesDelivered,
                            'messagesRead' => $tableMessagesRead
                        ],
                        'finalCounts' => [
                            'messagesSent' => $messagesSent,
                            'messagesFailed' => $messagesFailed,
                            'messagesPending' => $messagesPending
                        ]
                    ],
                    'latestTemplate' => $latestTemplateDetails,
                    'messageAnalytics' => [
                        'title' => 'Daily message performance over the past week',
                        'data' => $chartData
                    ],
                    'activeCampaigns' => [
                        'title' => 'Current campaign status overview',
                        'data' => $activeCampaigns
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
