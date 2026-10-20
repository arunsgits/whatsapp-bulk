<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Contact;
use App\Models\Settings;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\AutoAiReplyService;
use App\Services\WhatsAppService;
use App\Services\WhatsAppInboxService;
use App\Services\WhatsAppCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class ConversationController extends Controller
{
    protected $whatsappService;
    protected WhatsAppInboxService $inboxService;

    public function __construct(WhatsAppService $whatsappService, WhatsAppInboxService $inboxService)
    {
        $this->whatsappService = $whatsappService;
        $this->inboxService = $inboxService;
    }
    /**
     * Normalize phone number for comparison
     */
    private function normalizePhoneNumber($phoneNumber)
    {
        // Remove all non-numeric characters
        $normalized = preg_replace('/[^0-9]/', '', $phoneNumber);

        // Remove leading zeros and country codes if present
        $normalized = ltrim($normalized, '0');

        // If it starts with country code, remove it (assuming +91 for India, +1 for US, etc.)
        if (strlen($normalized) > 10) {
            // Remove common country codes
            if (str_starts_with($normalized, '91') && strlen($normalized) == 12) {
                $normalized = substr($normalized, 2);
            } elseif (str_starts_with($normalized, '1') && strlen($normalized) == 11) {
                $normalized = substr($normalized, 1);
            }
        }

        return $normalized;
    }
    
    /**
     * Get a specific conversation with messages
     */
    public function show_old(Request $request, $id)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $limit = $request->query('limit', 50); // Default limit of 50 messages
            $offset = $request->query('offset', 0); // For pagination

            // Get total message count first
            $totalMessages = Message::where('conversation_id', $id)->count();

            // If no messages exist, return empty result
            if ($totalMessages == 0) {
                $conversation = Conversation::where('id', $id)
                    ->where('user_id', $userId)
                    ->first();

                if (!$conversation) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Conversation not found'
                    ], 404);
                }

                return response()->json([
                    'success' => true,
                    'data' => $conversation,
                    'pagination' => [
                        'total_messages' => 0,
                        'current_limit' => $limit,
                        'current_offset' => 0,
                        'has_more' => false
                    ]
                ]);
            }

            // Calculate smart offset - if requested offset is beyond total messages,
            // start from the latest messages
            $effectiveOffset = min($offset, max(0, $totalMessages - $limit));

            $conversation = Conversation::where('id', $id)
                ->where('user_id', $userId)
                ->with(['messages' => function($q) use ($limit, $effectiveOffset) {
                    $q->orderBy('whatsapp_timestamp', 'asc')
                      ->limit($limit)
                      ->offset($effectiveOffset);
                }])
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation not found'
                ], 404);
            }

            // Mark conversation as read
            $conversation->markAsRead();

            // Add contact details
            $contact = $this->getContactByPhone($conversation->phone_number, $userId);
            $conversation->contact_details = $contact ? [
                'id' => $contact->id,
                'name' => $contact->name,
                'phone' => $contact->phone,
                'email' => $contact->email,
                'tags' => $contact->tags,
                'groups' => $contact->groups,
                'status' => $contact->status
            ] : null;

            // Process messages to include full media URLs and display information
            if ($conversation->messages) {
                $conversation->messages->transform(function ($message) use ($conversation) {
                    if ($message->type !== 'text' && $message->media_url) {
                        // Check if media_url is a WhatsApp media ID (numeric)
                        if (is_numeric($message->media_url)) {
                            // First try to download and get the filename
                            $downloadResult = $this->whatsappService->downloadWhatsAppMedia($message->media_url);

                            if ($downloadResult['success']) {
                                $message->media_download_url = url("/api/media/{$downloadResult['filename']}");
                                $message->media_display_url = url("/api/media/{$downloadResult['filename']}");
                                $message->media_filename = $downloadResult['filename'];
                                $message->media_type = $downloadResult['mime_type'];
                            } else {
                                // Fallback to download endpoint if download fails
                                $message->media_download_url = url("/api/conversations/{$conversation->id}/media/{$message->media_url}");
                                $message->media_display_url = url("/api/conversations/{$conversation->id}/media/{$message->media_url}");
                            }
                        } else {
                            // If it's already a URL, use it directly
                            $message->media_download_url = $message->media_url;
                            $message->media_display_url = $message->media_url;
                        }

                        // Add media display information
                        $message->media_display = [
                            'type' => $message->type,
                            'mime_type' => $message->media_type,
                            'filename' => $message->media_filename,
                            'url' => $message->media_display_url,
                            'download_url' => $message->media_download_url,
                            'is_image' => in_array($message->type, ['image']),
                            'is_video' => in_array($message->type, ['video']),
                            'is_audio' => in_array($message->type, ['audio']),
                            'is_document' => in_array($message->type, ['document']),
                            'caption' => $message->content,
                            'thumbnail_url' => $message->type === 'video' ? $message->media_display_url : null
                        ];
                    } else {
                        // For text messages, add basic display info
                        $message->media_display = [
                            'type' => 'text',
                            'content' => $message->content,
                            'is_text' => true
                        ];
                    }

                    // Attach Commerce, Order, and Product Inquiry Context
                    $meta = is_array($message->metadata) ? $message->metadata : [];
                    if (!empty($meta['order']) || !empty($meta['order_details'])) {
                        $rawOrder = $meta['order'] ?? $meta['order_details'];
                        $enrichedOrder = app(\App\Services\WhatsAppCatalogService::class)->enrichOrderData(is_array($rawOrder) ? $rawOrder : [], $userId);
                        $display = is_array($message->media_display) ? $message->media_display : [];
                        $display['is_order'] = true;
                        $display['type'] = 'order';
                        $display['order'] = $enrichedOrder;
                        if (!empty($enrichedOrder['image_url'])) {
                            $display['image_url'] = $enrichedOrder['image_url'];
                            $display['url'] = $enrichedOrder['image_url'];
                            $display['thumbnail_url'] = $enrichedOrder['image_url'];
                            if (empty($message->media_url)) {
                                $message->media_url = $enrichedOrder['image_url'];
                                $message->media_display_url = $enrichedOrder['image_url'];
                            }
                        }
                        $message->media_display = $display;
                    }
                    if (!empty($meta['referred_product']) || !empty($meta['context']['referred_product'])) {
                        $rawRef = $meta['referred_product'] ?? ($meta['context']['referred_product'] ?? []);
                        $enrichedRef = app(\App\Services\WhatsAppCatalogService::class)->enrichReferredProduct(is_array($rawRef) ? $rawRef : [], $userId);
                        $display = is_array($message->media_display) ? $message->media_display : [];
                        $display['is_product_inquiry'] = true;
                        $display['type'] = 'product_inquiry';
                        $display['referred_product'] = $enrichedRef;
                        if (!empty($enrichedRef['image_url'])) {
                            $display['image_url'] = $enrichedRef['image_url'];
                            $display['url'] = $enrichedRef['image_url'];
                            $display['thumbnail_url'] = $enrichedRef['image_url'];
                            if (empty($message->media_url)) {
                                $message->media_url = $enrichedRef['image_url'];
                                $message->media_display_url = $enrichedRef['image_url'];
                            }
                        }
                        $message->media_display = $display;
                    }
                    // Attach Catalog Product Image Context
                    $catalogMedia = app(\App\Services\WhatsAppCatalogService::class)->resolveCatalogProductMedia(
                        (string) $message->content,
                        $meta,
                        $message->media_url,
                        $userId
                    );
                    if ($catalogMedia && !empty($catalogMedia['image_url'])) {
                        $primaryImg = $catalogMedia['image_url'];
                        $catalogProd = $catalogMedia['product'] ?? null;
                        $display = is_array($message->media_display) ? $message->media_display : [];
                        $display['type'] = 'image';
                        $display['is_image'] = true;
                        $display['is_product'] = true;
                        $display['is_catalog_product'] = true;
                        $display['image_url'] = $primaryImg;
                        $display['url'] = $primaryImg;
                        $display['thumbnail_url'] = $primaryImg;
                        $display['download_url'] = $primaryImg;
                        $display['caption'] = $message->content;
                        $display['content'] = $message->content;
                        if (!empty($catalogProd)) {
                            $display['product'] = $catalogProd;
                            $display['catalog_product'] = $catalogProd;
                        }
                        if (empty($message->media_url)) {
                            $message->media_url = $primaryImg;
                            $message->media_display_url = $primaryImg;
                            $message->media_type = 'image/jpeg';
                        }
                        $message->media_display = $display;
                    }
                    if (!empty($meta['interactive'])) {
                        $display = is_array($message->media_display) ? $message->media_display : [];
                        $display['is_interactive'] = true;
                        $display['interactive'] = $meta['interactive'];
                        $message->media_display = $display;
                    }

                    return $message;
                });
            }

            return response()->json([
                'success' => true,
                'data' => $conversation,
                'pagination' => [
                    'total_messages' => $totalMessages,
                    'current_limit' => $limit,
                    'requested_offset' => $offset,
                    'effective_offset' => $effectiveOffset,
                    'has_more' => ($effectiveOffset + $limit) < $totalMessages,
                    'showing_latest' => $effectiveOffset != $offset,
                    'messages_returned' => $conversation->messages ? $conversation->messages->count() : 0
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a conversation by ID with its messages
     */
    public function show(Request $request, $id)
    {
        if ($id === 'stats') {
            return $this->getStats($request);
        }
        if ($id === 'unread') {
            return $this->getUnreadConversations($request);
        }
        if ($id === 'latest') {
            return $this->getLatestContacts($request);
        }

        try {
            $userId = $request->user()->getCompanyId();
            $limit = (int) $request->query('limit', 50);
            $pageParam = $request->query('page');
            $offsetParam = $request->query('offset');

            // Determine page index (0-based). If 'page' provided (1-based), use it; otherwise use 'offset' as page index
            if (!is_null($pageParam)) {
                $page = max(1, (int) $pageParam);
                $pageIndex = $page - 1; // 0-based
            } else {
                $pageIndex = max(0, (int) ($offsetParam ?? 0)); // treat offset as page index (0,1,2,...)
                $page = $pageIndex + 1; // 1-based page for metadata
            }

            $recordOffset = $pageIndex * max(1, $limit);

            // Verify conversation ownership
            $conversation = Conversation::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$conversation && is_numeric($id)) {
                $wa = WhatsappConversation::find((int) $id);
                if ($wa && !empty($wa->metadata['legacy_conversation_id'])) {
                    $conversation = Conversation::where('id', $wa->metadata['legacy_conversation_id'])
                        ->where('user_id', $userId)
                        ->first();
                }
            }

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation not found'
                ], 404);
            }

            $conversation = $this->decorateConversationData($conversation, $userId);

            // Fetch messages with pagination (newest first)
            $baseQuery = Message::where('conversation_id', $id);
            $total = (clone $baseQuery)->count();

            $messages = (clone $baseQuery)
                ->orderBy('whatsapp_timestamp', 'desc')
                ->offset($recordOffset)
                ->limit($limit)
                ->get();

            // Return the selected window in ascending order (oldest to newest within the page)
            $messages = $messages
                ->sortBy(function ($m) { return [$m->whatsapp_timestamp, $m->id]; })
                ->values();

            // Enrich messages with media display URLs for frontend (images, files, etc.)
            $messages = $this->enrichMessagesWithMediaUrls(
                $messages,
                (int) $id,
                $request->user()->getCompanyId(),
                (bool) $conversation->messaging_window_active
            );

            $totalPages = $limit > 0 ? (int) ceil($total / $limit) : 1;
            $hasMore = ($pageIndex + 1) < $totalPages;
            $nextOffset = $hasMore ? ($pageIndex + 1) : null; // next page index

            return response()->json([
                'success' => true,
                'data' => [
                    'conversation' => $conversation,
                    'messages' => $messages,
                    'pagination' => [
                        'total' => $total,
                        'limit' => (int) $limit,
                        'offset' => (int) $pageIndex, // page index (0-based)
                        'next_offset' => $nextOffset, // next page index
                        'has_more' => $hasMore,
                        'page' => $page,
                        'total_pages' => $totalPages
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enrich messages with media_display_url and media_display for frontend (images, files, etc.)
     * Uses signed URLs so links work when opened directly (new tab / <img>) without sending Bearer token.
     */
    private function enrichMessagesWithMediaUrls($messages, int $conversationId, int $userId, bool $messagingWindowActive = false)
    {
        return $messages->map(function ($message) use ($conversationId, $userId, $messagingWindowActive) {
            $capabilities = $this->inboxService->buildLegacyMessageCapabilities(
                $message,
                $conversationId,
                $messagingWindowActive
            );

            $message->can_react = $capabilities['can_react'];
            $message->can_reply = $capabilities['can_reply'];
            $message->react_message_id = $capabilities['react_message_id'];
            $message->reply_to_message_id = $capabilities['reply_to_message_id'];
            $message->inbox_conversation_id = $capabilities['inbox_conversation_id'];
            $message->react_endpoint = $capabilities['react_endpoint'];
            $message->reply_endpoint = $capabilities['reply_endpoint'];
            $message->capabilities = $capabilities;
            $message->meta_message_id = $message->whatsapp_message_id;
            $message->reactions = WhatsappMessage::reactionDetailsFromMetadata(
                is_array($message->metadata) ? $message->metadata : []
            );
            $message->reaction_summary = WhatsappMessage::reactionSummaryFromMetadata(
                is_array($message->metadata) ? $message->metadata : []
            );

            if (empty($message->reactions) && filled($message->whatsapp_message_id)) {
                $whatsappMessage = WhatsappMessage::query()
                    ->where('meta_message_id', $message->whatsapp_message_id)
                    ->first();

                if ($whatsappMessage) {
                    $message->reactions = $whatsappMessage->reactionDetails();
                    $message->reaction_summary = $whatsappMessage->reactionSummary();
                }
            }

            $message->reaction_emojis = collect($message->reactions)->pluck('emoji')->unique()->values()->all();
            $message->message_type = $message->type;
            $message->reply_to = WhatsappMessage::replyContextFromLegacy($message);
            $message->is_reply = $message->reply_to !== null;
            $message->replied_to_message_id = is_array($message->reply_to)
                ? ($message->reply_to['message_id'] ?? $message->reply_to['legacy_message_id'] ?? null)
                : null;

            $meta = is_array($message->metadata) ? $message->metadata : [];

            // 1. Order message check
            $hasOrder = !empty($meta['order']) || !empty($meta['order_details']) || ($meta['type'] ?? '') === 'order' || ($meta['raw_type'] ?? '') === 'order';
            if ($hasOrder) {
                $rawOrder = $meta['order'] ?? ($meta['order_details'] ?? []);
                $enrichedOrder = app(\App\Services\WhatsAppCatalogService::class)->enrichOrderData(is_array($rawOrder) ? $rawOrder : [], $userId);
                $primaryImg = $enrichedOrder['image_url'] ?? null;

                if (empty($message->media_url) && $primaryImg) {
                    $message->media_url = $primaryImg;
                    $message->media_display_url = $primaryImg;
                    $message->media_type = 'image/jpeg';
                }

                $message->media_display = [
                    'type' => 'order',
                    'is_order' => true,
                    'content' => $message->content,
                    'image_url' => $primaryImg,
                    'url' => $primaryImg,
                    'thumbnail_url' => $primaryImg,
                    'order' => $enrichedOrder,
                ];

                return $message;
            }

            // 2. Product inquiry (referred_product) check
            $hasProductInquiry = !empty($meta['referred_product']) || !empty($meta['context']['referred_product']);
            if ($hasProductInquiry) {
                $rawRef = $meta['referred_product'] ?? ($meta['context']['referred_product'] ?? []);
                $enrichedRef = app(\App\Services\WhatsAppCatalogService::class)->enrichReferredProduct(is_array($rawRef) ? $rawRef : [], $userId);
                $primaryImg = $enrichedRef['image_url'] ?? null;

                if (empty($message->media_url) && $primaryImg) {
                    $message->media_url = $primaryImg;
                    $message->media_display_url = $primaryImg;
                    $message->media_type = 'image/jpeg';
                }

                $message->media_display = [
                    'type' => 'product_inquiry',
                    'is_product_inquiry' => true,
                    'content' => $message->content,
                    'image_url' => $primaryImg,
                    'url' => $primaryImg,
                    'thumbnail_url' => $primaryImg,
                    'referred_product' => $enrichedRef,
                ];

                return $message;
            }

            if ($message->type === 'location') {
                $message->location = WhatsappMessage::locationDataFromLegacy($message);
                $message->media_display = [
                    'type' => 'location',
                    'is_location' => true,
                    'location' => $message->location,
                    'content' => $message->content,
                ];

                return $message;
            }

            // 3. Catalog Product message check (Outbound Single Product Message, Auto AI catalog matches, or catalog text cards)
            $catalogMedia = app(\App\Services\WhatsAppCatalogService::class)->resolveCatalogProductMedia(
                (string) $message->content,
                $meta,
                $message->media_url,
                $userId
            );

            if ($catalogMedia && !empty($catalogMedia['image_url'])) {
                $primaryImg = $catalogMedia['image_url'];
                $catalogProd = $catalogMedia['product'] ?? null;

                if (empty($message->media_url)) {
                    $message->media_url = $primaryImg;
                    try {
                        \App\Models\Message::where('id', $message->id)->update([
                            'media_url' => $primaryImg,
                            'media_type' => 'image/jpeg',
                        ]);
                    } catch (\Throwable $e) {}
                }

                $message->media_display_url = $primaryImg;
                $message->media_download_url = $primaryImg;
                $message->media_type = 'image/jpeg';
                $message->type = 'image';
                $message->message_type = 'image';

                $display = [
                    'type' => 'image',
                    'mime_type' => 'image/jpeg',
                    'filename' => 'product.jpg',
                    'url' => $primaryImg,
                    'image_url' => $primaryImg,
                    'thumbnail_url' => $primaryImg,
                    'download_url' => $primaryImg,
                    'is_image' => true,
                    'is_text' => true,
                    'is_product' => true,
                    'is_catalog_product' => true,
                    'caption' => $message->content,
                    'content' => $message->content,
                ];

                if (!empty($meta['interactive'])) {
                    $display['is_interactive'] = true;
                    $display['interactive'] = $meta['interactive'];
                }

                if (!empty($catalogProd)) {
                    $display['product'] = $catalogProd;
                    $display['catalog_product'] = $catalogProd;
                }

                $message->media_display = $display;
                return $message;
            }

            if ($message->type === 'text' || empty($message->media_url)) {
                $display = [
                    'type' => 'text',
                    'content' => $message->content,
                    'is_text' => true,
                ];
                if (!empty($meta['interactive'])) {
                    $display['is_interactive'] = true;
                    $display['interactive'] = $meta['interactive'];
                }
                $message->media_display = $display;
                return $message;
            }

            $displayUrl = null;
            $downloadUrl = null;

            if (is_numeric($message->media_url)) {
                // Signed URL: works when opened directly or in <img> without auth
                $displayUrl = URL::temporarySignedRoute('conversations.media.signed', now()->addHours(24), [
                    'conversationId' => $conversationId,
                    'mediaId' => $message->media_url,
                    'user_id' => $userId,
                ]);
                $downloadUrl = URL::temporarySignedRoute('conversations.media.signed', now()->addHours(24), [
                    'conversationId' => $conversationId,
                    'mediaId' => $message->media_url,
                    'user_id' => $userId,
                    'download' => 1,
                ]);
            } else {
                // Already a full URL (e.g. our stored file)
                $displayUrl = $message->media_url;
                $downloadUrl = $message->media_url;
            }

            $message->media_display_url = $displayUrl;
            $message->media_download_url = $downloadUrl;
            $message->media_display = [
                'type' => $message->type,
                'mime_type' => $message->media_type,
                'filename' => $message->media_filename,
                'url' => $displayUrl,
                'download_url' => $downloadUrl,
                'is_image' => in_array($message->type, ['image']),
                'is_video' => in_array($message->type, ['video']),
                'is_audio' => in_array($message->type, ['audio']),
                'is_document' => in_array($message->type, ['document']),
                'caption' => $message->content,
                'thumbnail_url' => $message->type === 'video' ? $displayUrl : null,
            ];

            return $message;
        });
    }

    /**
     * Serve conversation media via signed URL (no auth required).
     * Allows direct link / <img> access without Bearer token.
     */
    public function serveSignedMedia(Request $request, $conversationId, $mediaId)
    {
        if (!$request->hasValidSignature()) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid or expired link',
                'error' => 'Authentication required'
            ], 401);
        }

        $userId = (int) $request->query('user_id');
        if (!$userId) {
            return response()->json(['status' => false, 'message' => 'Invalid link'], 400);
        }

        $conversation = Conversation::where('id', $conversationId)
            ->where('user_id', $userId)
            ->first();

        if (!$conversation) {
            return response()->json(['status' => false, 'message' => 'Conversation not found'], 404);
        }

        $result = $this->whatsappService->downloadWhatsAppMedia($mediaId);
        if (!$result['success']) {
            return response()->json([
                'status' => false,
                'message' => $result['error'] ?? 'Failed to load media'
            ], 400);
        }

        $filePath = public_path('uploads/media/' . $result['filename']);
        if (!file_exists($filePath)) {
            return response()->json(['status' => false, 'message' => 'File not found'], 404);
        }

        $disposition = $request->query('download') === '1' ? 'attachment' : 'inline';
        return response()->file($filePath, [
            'Content-Type' => $result['mime_type'],
            'Content-Disposition' => $disposition . '; filename="' . ($result['filename'] ?? 'media') . '"',
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }

    /**
     * Get contact details by phone number
     */
    private function getContactByPhone($phoneNumber, $userId)
    {
        $normalizedPhone = $this->normalizePhoneNumber($phoneNumber);

        // Try exact match first
        $contact = Contact::where('userId', $userId)
            ->where('phone', $phoneNumber)
            ->first();

        if ($contact) {
            return $contact;
        }

        // Try normalized match
        $contacts = Contact::where('userId', $userId)->get();
        foreach ($contacts as $contact) {
            if ($this->normalizePhoneNumber($contact->phone) === $normalizedPhone) {
                return $contact;
            }
        }

        return null;
    }

    /**
     * Decorate a legacy Conversation model with complete contact, status, tags, and operational flags.
     */
    protected function decorateConversationData(Conversation $conversation, int $userId): Conversation
    {
        // 1. Contact details
        $contact = $this->getContactByPhone($conversation->phone_number, $userId);
        $conversation->contact_details = $contact ? [
            'id' => $contact->id,
            'name' => $contact->name,
            'phone' => $contact->phone,
            'email' => $contact->email,
            'tags' => $contact->tags,
            'groups' => $contact->groups,
            'status' => $contact->status
        ] : null;

        // 2. Messaging window & capabilities
        $lastInbound = Message::where('conversation_id', $conversation->id)
            ->where('direction', 'inbound')
            ->latest('whatsapp_timestamp')
            ->first();
        $messagingWindowActive = (bool) ($lastInbound && $lastInbound->isWithin24Hours());
        $conversation->messaging_window_active = $messagingWindowActive;
        $conversation->messaging_window_status = $messagingWindowActive ? 'active' : 'inactive';
        $conversation->messaging_window_hours_remaining = $messagingWindowActive
            ? max(0, (int) (24 - $lastInbound->whatsapp_timestamp->diffInHours(now())))
            : 0;
        $conversation->last_inbound_at = $lastInbound ? $lastInbound->whatsapp_timestamp : null;
        $conversation->inbox_conversation_id = $conversation->id;
        $conversation->ai_reply_enabled = ! (bool) (($conversation->metadata['ai_reply_disabled'] ?? false));
        $conversation->capabilities = $this->inboxService->buildLegacyConversationCapabilities(
            $conversation,
            $messagingWindowActive
        );

        $settings = Settings::where('userId', $userId)->first()
            ?: Settings::whereNull('userId')->first();
        $conversation->auto_ai_enabled = (bool) ($settings->auto_ai_enabled ?? false);

        // 3. Unread counts
        $unreadCount = (int) ($conversation->unread_count ?? 0);
        if ($unreadCount === 0 && (bool) $conversation->is_unread) {
            $unreadCount = 1;
        }
        $conversation->unread_count = $unreadCount;
        $conversation->unread_messages_count = $unreadCount;

        // 4. Status normalization & flags (active, closed, archived)
        $rawStatus = strtolower(trim((string) ($conversation->status ?? 'active')));
        $status = match ($rawStatus) {
            'close', 'closed' => 'closed',
            'archive', 'archived' => 'archived',
            'pending', 'pending_payment' => 'pending',
            default => 'active',
        };

        // 5. Tags extraction
        $meta = is_array($conversation->metadata) ? $conversation->metadata : [];
        $metaTags = is_array($meta['tags'] ?? null) ? $meta['tags'] : [];
        $tags = [];
        foreach ($metaTags as $tagName => $enabled) {
            if (!empty($enabled)) {
                $tags[] = (string) $tagName;
            }
        }

        // Check if there is an associated WhatsappConversation with newer status/tags
        $waConv = WhatsappConversation::where('metadata->legacy_conversation_id', $conversation->id)->first();
        if (!$waConv && !empty($conversation->phone_number)) {
            $phone = (string) $conversation->phone_number;
            $cleaned = preg_replace('/[^0-9]/', '', $phone);
            $last10 = strlen($cleaned) >= 10 ? substr($cleaned, -10) : $cleaned;
            $waConv = WhatsappConversation::where('phone_number', $phone)
                ->orWhere('phone_number', 'like', "%{$last10}")
                ->first();
        }
        if ($waConv) {
            if ($waConv->status === WhatsappConversation::STATUS_CLOSED && $status !== 'closed') {
                $status = 'closed';
            } elseif ($waConv->status === WhatsappConversation::STATUS_ARCHIVED && $status !== 'archived') {
                $status = 'archived';
            } elseif ($waConv->status === WhatsappConversation::STATUS_OPEN && $status !== 'active') {
                $status = 'active';
            }
            $waTags = $waConv->activeTags();
            if (!empty($waTags)) {
                $tags = array_values(array_unique(array_merge($tags, $waTags)));
            }
        }

        $conversation->status = $status;
        $conversation->status_label = match ($status) {
            'closed' => 'Closed',
            'archived' => 'Archived',
            'pending' => 'Pending',
            default => 'Active',
        };
        $conversation->is_closed = ($status === 'closed');
        $conversation->is_archived = ($status === 'archived');
        $conversation->tags = array_values(array_unique($tags));
        $conversation->active_tags = array_values(array_unique($tags));
        $isImportant = in_array('important', $tags, true);
        $conversation->is_important = $isImportant;
        $conversation->important = $isImportant;
        $conversation->tag = !empty($tags) ? $tags[0] : null;
        $conversation->tag_label = !empty($tags) ? ucwords(str_replace(['_', '-'], ' ', $tags[0])) : null;

        return $conversation;
    }

    /**
     * Get all conversations for the authenticated user (supports active, archived, closed, all)
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $status = $request->query('status', 'all');
            $search = $request->query('search');
            $includeArchived = $request->boolean('include_archived', $request->boolean('with_archived', false));
            $onlyArchived = $request->boolean('archived', false);
            $limit = (int) $request->query('limit', 20);
            if ($limit <= 0) {
                $limit = 20;
            }

            $query = Conversation::where('user_id', $userId)
                ->withCount(['messages as unread_count' => function($q) {
                    $q->where('direction', 'inbound')->where('is_read', false);
                }])
                ->with(['latestMessage' => function($q) {
                    $q->latest('whatsapp_timestamp')->limit(1);
                }]);

            // Filter by status
            $rawStatus = strtolower(trim((string) $status));
            if ($onlyArchived || in_array($rawStatus, ['archived', 'archive'], true)) {
                $query->where(function($q) {
                    $q->whereIn('status', ['archived', 'archive'])
                      ->orWhereIn('id', function($sub) {
                          $sub->selectRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.legacy_conversation_id')) AS UNSIGNED)")
                              ->from('whatsapp_conversations')
                              ->where('status', WhatsappConversation::STATUS_ARCHIVED);
                      });
                });
            } elseif ($rawStatus !== '' && !in_array($rawStatus, ['all', 'any', '*'], true)) {
                if (in_array($rawStatus, ['active', 'open'], true)) {
                    if ($includeArchived) {
                        $query->where(function($q) {
                            $q->whereIn('status', ['active', 'open', 'archived', 'archive'])
                              ->orWhereNull('status')
                              ->orWhere('status', '');
                        });
                    } else {
                        $query->where(function($q) {
                            $q->whereIn('status', ['active', 'open'])
                              ->orWhereNull('status')
                              ->orWhere('status', '');
                        })->whereNotIn('id', function($sub) {
                            $sub->selectRaw("CAST(JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.legacy_conversation_id')) AS UNSIGNED)")
                                ->from('whatsapp_conversations')
                                ->where('status', WhatsappConversation::STATUS_ARCHIVED);
                        });
                    }
                } elseif (in_array($rawStatus, ['closed', 'close'], true)) {
                    $query->whereIn('status', ['closed', 'close']);
                } elseif (in_array($rawStatus, ['pending', 'pending_payment'], true)) {
                    $query->whereIn('status', ['pending', 'pending_payment']);
                } elseif ($rawStatus === 'unread') {
                    $query->where('is_unread', true);
                } elseif (in_array($rawStatus, ['important', 'starred'], true)) {
                    $query->where(function($q) {
                        $q->where('metadata->tags->important', true)
                          ->orWhere('metadata->tags->important', 'true')
                          ->orWhere('metadata->tags->important', 1);
                    });
                } else {
                    $query->where('status', $rawStatus);
                }
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('phone_number', 'like', "%{$search}%")
                      ->orWhere('contact_name', 'like', "%{$search}%");
                });
            }

            $conversations = $query->orderBy('last_message_at', 'desc')
                ->paginate($limit);

            // Add contact details, unread counts, status flags, and capabilities to each conversation
            $conversations->getCollection()->transform(function ($conversation) use ($userId) {
                return $this->decorateConversationData($conversation, $userId);
            });

            return response()->json([
                'success' => true,
                'data' => $conversations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get latest contacts (conversations with recent activity)
     */
    public function getLatestContacts(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $limit = (int) $request->query('limit', 10);
            $hours = (int) $request->query('hours', 24); // Last 24 hours by default

            $conversations = Conversation::where('user_id', $userId)
                ->where('last_message_at', '>=', now()->subHours($hours))
                ->withCount(['messages as unread_count' => function($q) {
                    $q->where('direction', 'inbound')->where('is_read', false);
                }])
                ->with(['latestMessage' => function($q) {
                    $q->latest('whatsapp_timestamp')->limit(1);
                }])
                ->orderBy('last_message_at', 'desc')
                ->limit($limit)
                ->get();

            // Add contact details, unread counts, status flags, and capabilities to each conversation
            $conversations->transform(function ($conversation) use ($userId) {
                return $this->decorateConversationData($conversation, $userId);
            });

            return response()->json([
                'success' => true,
                'data' => $conversations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get conversations with unread messages
     */
    public function getUnreadConversations(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();

            $conversations = Conversation::where('user_id', $userId)
                ->where('is_unread', true)
                ->withCount(['messages as unread_count' => function($q) {
                    $q->where('direction', 'inbound')->where('is_read', false);
                }])
                ->with(['latestMessage' => function($q) {
                    $q->latest('whatsapp_timestamp')->limit(1);
                }])
                ->orderBy('last_message_at', 'desc')
                ->get();

            // Add contact details, unread counts, status flags, and capabilities to each conversation
            $conversations->transform(function ($conversation) use ($userId) {
                return $this->decorateConversationData($conversation, $userId);
            });

            return response()->json([
                'success' => true,
                'data' => $conversations
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get archived conversations for the authenticated user
     */
    public function getArchivedConversations(Request $request)
    {
        $request->merge(['status' => 'archived']);
        return $this->index($request);
    }

    /**
     * Update conversation status
     */
    public function updateStatus(Request $request, $id)
    {
        // Keep WhatsappConversation and legacy Conversation synchronized
        $resolved = $this->inboxService->resolveConversation($id);
        if (!$resolved && is_numeric($id)) {
            $legacy = Conversation::find((int) $id);
            if ($legacy) {
                $resolved = $this->inboxService->ensureWhatsappConversationForLegacy($legacy);
            }
        }
        if ($resolved) {
            return app(\App\Http\Controllers\Api\WhatsAppInboxController::class)->updateStatus($request, $resolved->id);
        }

        try {
            $userId = $request->user()->getCompanyId();
            $rawStatus = mb_strtolower(trim((string) ($request->status ?? $request->input('status', ''))));
            $normalized = match ($rawStatus) {
                'close', 'closed' => 'closed',
                'archive', 'archived' => 'archived',
                'pending', 'pending_payment' => 'pending',
                default => 'active',
            };

            $conversation = Conversation::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation not found'
                ], 404);
            }

            $conversation->update(['status' => $normalized]);
            $conversation = $this->decorateConversationData($conversation, $userId);

            return response()->json([
                'success' => true,
                'message' => 'Conversation status updated successfully',
                'status' => $conversation->status,
                'status_label' => $conversation->status_label,
                'is_closed' => $conversation->is_closed,
                'is_archived' => $conversation->is_archived,
                'tags' => $conversation->tags,
                'active_tags' => $conversation->active_tags,
                'is_important' => $conversation->is_important,
                'important' => $conversation->is_important,
                'data' => [
                    'id' => (int) $conversation->id,
                    'status' => $conversation->status,
                    'status_label' => $conversation->status_label,
                    'contact_name' => $conversation->contact_name,
                    'is_closed' => $conversation->is_closed,
                    'is_archived' => $conversation->is_archived,
                    'tags' => $conversation->tags,
                    'is_important' => $conversation->is_important,
                ],
                'conversation' => $conversation,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enable/disable Auto AI replies for a single chat.
     */
    public function setAiReplyPreference(Request $request, $id, AutoAiReplyService $autoAi)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $request->validate(['ai_reply_enabled' => 'required|boolean']);
            $enabled = $request->boolean('ai_reply_enabled');

            // Prefer legacy conversations table
            $conversation = Conversation::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if ($conversation) {
                $autoAi->setChatAiEnabled($conversation, $enabled);
            } else {
                // Frontend may send WhatsApp inbox conversation id
                $wa = \App\Models\WhatsappConversation::find($id);
                if (! $wa) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Conversation not found',
                    ], 404);
                }

                $legacyId = app(\App\Services\WhatsAppInboxService::class)->legacyConversationId($wa);
                $legacy = $legacyId
                    ? Conversation::where('id', $legacyId)->where('user_id', $userId)->first()
                    : null;

                if ($legacy) {
                    $autoAi->setChatAiEnabled($legacy, $enabled);
                } else {
                    $autoAi->setChatAiEnabledForPhone((string) $wa->phone_number, $enabled, null, $wa);
                }
            }

            $settings = Settings::where('userId', $userId)->first()
                ?: Settings::whereNull('userId')->first();

            return response()->json([
                'success' => true,
                'ai_reply_enabled' => $enabled,
                'auto_ai_enabled' => (bool) ($settings->auto_ai_enabled ?? false),
                'message' => $enabled
                    ? 'Auto AI enabled for this chat'
                    : 'Auto AI disabled for this chat',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download WhatsApp media
     */
    public function downloadMedia(Request $request, $conversationId, $mediaId)
    {
        try {
            $userId = $request->user()->getCompanyId();

            // Verify conversation ownership
            $conversation = Conversation::where('id', $conversationId)
                ->where('user_id', $userId)
                ->first();

            if (!$conversation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversation not found'
                ], 404);
            }

            // Download the media using WhatsApp service
            $result = $this->whatsappService->downloadWhatsAppMedia($mediaId);

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['error']
                ], 400);
            }

            // Return file for direct display (e.g. <img src="..."> or link open in browser)
            $wantsFile = $request->query('stream') === '1' || $request->query('download') === '1' || !$request->expectsJson();
            if ($wantsFile) {
                $filePath = public_path('uploads/media/' . $result['filename']);
                if (file_exists($filePath)) {
                    $disposition = $request->query('download') === '1' ? 'attachment' : 'inline';
                    return response()->file($filePath, [
                        'Content-Type' => $result['mime_type'],
                        'Content-Disposition' => $disposition . '; filename="' . ($result['filename'] ?? 'media') . '"',
                        'Cache-Control' => 'public, max-age=31536000',
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'media_id' => $mediaId,
                    'filename' => $result['filename'],
                    'url' => $result['url'],
                    'full_url' => url('/api/conversations/' . $conversationId . '/media/' . $mediaId),
                    'mime_type' => $result['mime_type'],
                    'size' => $result['size']
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get conversation statistics
     */
    public function getStats(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();

            $stats = [
                'total_conversations' => Conversation::where('user_id', $userId)->count(),
                'active_conversations' => Conversation::where('user_id', $userId)->where(function($q) {
                    $q->whereIn('status', ['active', 'open'])->orWhereNull('status')->orWhere('status', '');
                })->count(),
                'closed_conversations' => Conversation::where('user_id', $userId)->whereIn('status', ['closed', 'close'])->count(),
                'archived_conversations' => Conversation::where('user_id', $userId)->whereIn('status', ['archived', 'archive'])->count(),
                'unread_conversations' => Conversation::where('user_id', $userId)->where('is_unread', true)->count(),
                'recent_conversations' => Conversation::where('user_id', $userId)
                    ->where('last_message_at', '>=', now()->subHours(24))
                    ->count(),
                'total_messages_today' => Message::whereHas('conversation', function($q) use ($userId) {
                    $q->where('user_id', $userId);
                })->whereDate('created_at', today())->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Set or toggle Important tag on conversation.
     */
    public function setImportant(Request $request, $id)
    {
        return app(\App\Http\Controllers\Api\WhatsAppInboxController::class)->setImportant($request, $id);
    }

    /**
     * Close conversation.
     */
    public function closeConversation(Request $request, $id)
    {
        return app(\App\Http\Controllers\Api\WhatsAppInboxController::class)->closeConversation($request, $id);
    }

    /**
     * Archive conversation.
     */
    public function archiveConversation(Request $request, $id)
    {
        return app(\App\Http\Controllers\Api\WhatsAppInboxController::class)->archiveConversation($request, $id);
    }

    /**
     * Reopen conversation.
     */
    public function reopenConversation(Request $request, $id)
    {
        return app(\App\Http\Controllers\Api\WhatsAppInboxController::class)->reopenConversation($request, $id);
    }

    /**
     * Toggle or set tag on conversation.
     */
    public function toggleTag(Request $request, $id)
    {
        return app(\App\Http\Controllers\Api\WhatsAppInboxController::class)->toggleTag($request, $id);
    }
}

