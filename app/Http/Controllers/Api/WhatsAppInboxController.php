<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappFollowup;
use App\Models\WhatsappMessage;
use App\Models\WhatsappCampaign;
use App\Models\Template;
use App\Models\Conversation;
use App\Models\Settings;
use App\Services\WhatsAppInboxService;
use App\Services\WhatsAppCatalogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class WhatsAppInboxController extends Controller
{
    private WhatsAppInboxService $inboxService;

    public function __construct(WhatsAppInboxService $inboxService)
    {
        $this->inboxService = $inboxService;
    }

    private function findInboxConversation($id): ?WhatsappConversation
    {
        return $this->inboxService->resolveConversation($id);
    }

    private function userOwnsConversation(WhatsappConversation $conversation, int $ownerId): bool
    {
        if ($ownerId <= 0) {
            return false;
        }

        $resolved = $this->inboxService->resolveOwnerUserId($conversation);
        if ($resolved !== null && (int) $resolved === $ownerId) {
            return true;
        }

        return (int) ($conversation->lead?->userId ?? 0) === $ownerId;
    }

    public function inbox(Request $request)
    {
        $filter = $request->get('status', WhatsappConversation::FILTER_ALL);

        $ownerId = (int) ($request->user()?->getCompanyId() ?? 0);
        $isAdmin = strtolower((string) ($request->user()?->role ?? '')) === 'admin';
        $isGlobal = $isAdmin && ($request->boolean('global', false) || $request->boolean('all', false));

        $conversations = WhatsappConversation::with(['lead', 'assignedUser', 'latestMessage'])
            ->inboxFilter($filter)
            ->when(! $isGlobal && $ownerId > 0, fn ($q) => $q->ownedBy($ownerId))
            ->when($request->assigned_to, fn($q) => $q->where('assigned_user_id', $request->assigned_to))
            ->when($request->search, function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('contact_name', 'like', $term)
                        ->orWhere('phone_number', 'like', $term);
                });
            })
            ->orderByDesc('last_message_at')
            ->paginate(30);

        $totalUnreadQuery = WhatsappConversation::where('unread_count', '>', 0);
        $totalUnreadQuery->when(! $isGlobal && $ownerId > 0, fn ($q) => $q->ownedBy($ownerId));
        $totalUnread = $totalUnreadQuery->sum('unread_count');

        $conversations->setCollection(
            $conversations->getCollection()->map(fn ($conversation) => $this->formatConversation($conversation))
        );

        return response()->json([
            'success' => true,
            'conversations' => $conversations,
            'totalUnread' => $totalUnread,
            'filter' => $filter
        ]);
    }

    public function conversation(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);

        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $ownerId = (int) ($request->user()?->getCompanyId() ?? 0);
        $isAdmin = strtolower((string) ($request->user()?->role ?? '')) === 'admin';
        $isGlobal = $isAdmin && ($request->boolean('global', false) || $request->boolean('all', false));

        // Ownership check (webhook chats may have no lead — use owner_user_id / assigned / lead)
        $conversation->load(['lead', 'assignedUser', 'followups.assignedUser']);
        if (! $isGlobal && ! $this->userOwnsConversation($conversation, $ownerId)) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $conversation->markAsRead();

        $chatMessages = $conversation->messages()
            ->where('message_type', '!=', 'reaction')
            ->orderByDesc('created_at')
            ->limit(150)
            ->get()
            ->sortBy('created_at')
            ->values();

        $formattedMessages = $chatMessages->map(
            fn ($message) => $this->formatMessage($message, $conversation)
        );

        $settings = Settings::where('userId', $ownerId)->where('isActive', true)->first();
        $hasWaba = $settings
            && filled(trim((string) ($settings->waba_id ?? '')))
            && filled(trim((string) ($settings->access_token ?? '')))
            && filled(trim((string) ($settings->phoneNumberId ?? '')));

        $templatesQuery = Template::query()
            ->where('userId', $ownerId)
            ->where('status', 'APPROVED');

        // If user has not connected WhatsApp credentials yet, never show Meta/system templates.
        if (! $hasWaba) {
            $templatesQuery->where('type', 'custom');
        }

        $templates = $templatesQuery->orderBy('name')->get();

        $usersQuery = User::query()->orderBy('name');
        if (! $isGlobal) {
            $usersQuery->where(function ($q) use ($ownerId) {
                $q->where('id', $ownerId)->orWhere('parent_id', $ownerId);
            });
        }
        $users = $usersQuery->get();

        return response()->json([
            'success' => true,
            'conversation' => array_merge(
                $conversation->toArray(),
                ['capabilities' => $this->inboxService->buildConversationCapabilities($conversation)]
            ),
            'messages' => $formattedMessages,
            'templates' => $templates,
            'users' => $users,
        ]);
    }

    public function sendMessage(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required_without:template_name|nullable|string|max:4096',
            'template_name' => 'required_without:message|nullable|string',
            'variables' => 'nullable|array',
            'reply_to_message_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $replyTo = null;
        if ($request->filled('reply_to_message_id')) {
            $replyTo = $this->inboxService->resolveMessage(
                $conversation,
                $request->integer('reply_to_message_id')
            );

            if (! $replyTo) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reply target message not found',
                ], 404);
            }

            $replyTo = $this->inboxService->ensureMessageMetaId($replyTo);
            if (! $replyTo->meta_message_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reply target message has no WhatsApp message id yet',
                ], 422);
            }
        }

        if ($request->template_name) {
            $message = $this->inboxService->sendTemplateMessage(
                $conversation,
                $request->template_name,
                $request->variables ?? [],
                Auth::id()
            );
        } else {
            $sendError = null;
            $message = $this->inboxService->sendTextMessage(
                $conversation,
                $request->message,
                Auth::id(),
                $sendError,
                $replyTo
            );
        }

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => $sendError ?? 'Failed to send message.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message, $conversation),
        ]);
    }

    public function sendMedia(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:16384',
            'caption' => 'nullable|string|max:1024',
            'voice_note' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $sendError = null;
        $message = $this->inboxService->sendMediaMessage(
            $conversation,
            $request->file('file'),
            $request->caption,
            Auth::id(),
            $sendError,
            (bool) $request->voice_note
        );

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => $sendError ?? 'Failed to send media.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message, $conversation),
        ]);
    }

    public function sendLocation(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1024',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $sendError = null;
        $message = $this->inboxService->sendLocationMessage(
            $conversation,
            (float) $request->latitude,
            (float) $request->longitude,
            $request->name,
            $request->address,
            Auth::id(),
            $sendError
        );

        if (!$message) {
            return response()->json([
                'success' => false,
                'message' => $sendError ?? 'Failed to send location.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $this->formatMessage($message, $conversation),
        ]);
    }

    public function getMessages(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $query = $conversation->messages()
            ->where('message_type', '!=', 'reaction');

        if ($request->after_id) {
            $query->where('id', '>', $request->after_id);
        }

        $messages = $query->orderBy('created_at', 'asc')->limit(80)->get();

        $hasNewIncoming = $messages->contains(fn ($m) => $m->direction === 'incoming');
        if ($hasNewIncoming || (int) $conversation->unread_count > 0) {
            $conversation->markAsRead();
        }

        return response()->json([
            'success' => true,
            'messages' => $messages->map(fn ($message) => $this->formatMessage($message, $conversation)),
            'conversation' => [
                'capabilities' => $this->inboxService->buildConversationCapabilities($conversation),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function searchInbox(Request $request)
    {
        $query = trim((string) $request->get('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['chats' => [], 'messages' => []]);
        }

        $filter = $request->get('status', WhatsappConversation::FILTER_ALL);
        $term = '%' . $query . '%';
        $ownerId = (int) ($request->user()?->getCompanyId() ?? 0);
        $isAdmin = strtolower((string) ($request->user()?->role ?? '')) === 'admin';
        $isGlobal = $isAdmin && ($request->boolean('global', false) || $request->boolean('all', false));

        $chats = WhatsappConversation::with(['lead', 'latestMessage'])
            ->inboxFilter($filter)
            ->when(! $isGlobal && $ownerId > 0, fn ($q) => $q->ownedBy($ownerId))
            ->where(function ($sub) use ($term) {
                $sub->where('contact_name', 'like', $term)
                    ->orWhere('phone_number', 'like', $term);
            })
            ->orderByDesc('last_message_at')
            ->limit(30)
            ->get();

        $messages = WhatsappMessage::query()
            ->with(['conversation'])
            ->whereNotIn('message_type', ['reaction', 'revoked'])
            ->where('message', 'like', $term)
            ->whereHas('conversation', function ($q) use ($filter, $isGlobal, $ownerId) {
                $q->inboxFilter($filter);
                if (! $isGlobal && $ownerId > 0) {
                    $q->ownedBy($ownerId);
                }
            })
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();

        return response()->json([
            'chats' => $chats->map(fn ($c) => $this->formatConversation($c)),
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'conversation_id' => $m->conversation_id,
                'contact_name' => $m->conversation?->contact_name ?? $m->conversation?->phone_number,
                'phone_number' => $m->conversation?->phone_number,
                'message_type' => $m->message_type,
                'snippet' => $m->message,
                'time' => $m->created_at?->format('d M Y, h:i A'),
            ]),
        ]);
    }

    public function conversationList(Request $request)
    {
        $filter = $request->get('status', WhatsappConversation::FILTER_ALL);
        $ownerId = (int) ($request->user()?->getCompanyId() ?? 0);
        $isAdmin = strtolower((string) ($request->user()?->role ?? '')) === 'admin';
        $isGlobal = $isAdmin && ($request->boolean('global', false) || $request->boolean('all', false));

        $conversations = WhatsappConversation::with(['lead', 'latestMessage'])
            ->inboxFilter($filter)
            ->when(! $isGlobal && $ownerId > 0, fn ($q) => $q->ownedBy($ownerId))
            ->when($request->search, function ($q) use ($request) {
                $term = '%' . $request->search . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('contact_name', 'like', $term)
                        ->orWhere('phone_number', 'like', $term);
                });
            })
            ->orderByDesc('last_message_at')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'conversations' => $conversations->map(fn ($conversation) => $this->formatConversation($conversation)),
        ]);
    }

    public function searchConversationMessages(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $query = trim((string) $request->get('q', ''));
        if (mb_strlen($query) < 1) {
            return response()->json(['results' => []]);
        }

        $term = '%' . $query . '%';
        $messages = $conversation->messages()
            ->whereNotIn('message_type', ['reaction', 'revoked'])
            ->where('message', 'like', $term)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'success' => true,
            'results' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'direction' => $m->direction,
                'message_type' => $m->message_type,
                'snippet' => $m->message,
                'time' => $m->created_at?->format('d M Y, h:i A'),
            ]),
        ]);
    }

    public function deleteMessage(Request $request, $conversationId, $messageId)
    {
        $conversation = $this->findInboxConversation($conversationId);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $message = $this->inboxService->resolveMessage($conversation, $messageId);
        if (!$message) {
            return response()->json(['success' => false, 'message' => 'Message not found'], 404);
        }

        if (!$this->inboxService->deleteMessage($message, Auth::id())) {
            return response()->json(['success' => false, 'message' => 'Could not delete message'], 422);
        }

        return response()->json(['success' => true, 'message_id' => $message->id]);
    }

    public function reactMessage(Request $request, $conversationId, $messageId)
    {
        try {
            $conversation = $this->findInboxConversation($conversationId);
            if (! $conversation) {
                return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
            }

            $message = $this->inboxService->resolveMessage($conversation, $messageId);
            if (! $message) {
                return response()->json(['success' => false, 'message' => 'Message not found'], 404);
            }

            $request->validate([
                'emoji' => 'nullable|string|max:32',
                'reaction' => 'nullable|string|max:32',
            ]);

            $emoji = $request->input('emoji', $request->input('reaction'));

            $sendError = null;
            $reactions = $this->inboxService->sendReaction(
                $conversation,
                $message,
                $emoji,
                Auth::id(),
                $sendError
            );

            if ($reactions === null) {
                return response()->json(['success' => false, 'message' => $sendError ?? 'Could not react'], 422);
            }

            return response()->json([
                'success' => true,
                'message_id' => $message->id,
                'reactions' => $message->reactionDetails(),
                'reaction_summary' => $message->reactionSummary(),
                'reaction_emojis' => $message->reactionEmojis(),
            ]);
        } catch (\Throwable $e) {
            Log::error('React message failed', [
                'conversation_id' => $conversationId,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Could not send reaction.',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    public function assign(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $request->validate([
            'userId' => 'nullable|exists:users,id',
            'user_id' => 'nullable|exists:users,id',
        ]);
        $assignedId = $request->input('userId', $request->input('user_id'));
        $conversation->update(['assigned_user_id' => $assignedId]);

        return response()->json(['success' => true]);
    }

    public function updateStatus(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $rawStatus = mb_strtolower(trim((string) ($request->status ?? $request->input('status', ''))));
        $normalized = match ($rawStatus) {
            'close', 'closed' => WhatsappConversation::STATUS_CLOSED,
            'archive', 'archived' => WhatsappConversation::STATUS_ARCHIVED,
            'open', 'reopen', 'active' => WhatsappConversation::STATUS_OPEN,
            default => $rawStatus,
        };

        if (!in_array($normalized, [WhatsappConversation::STATUS_OPEN, WhatsappConversation::STATUS_CLOSED, WhatsappConversation::STATUS_ARCHIVED, 'active', 'pending'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status. Supported statuses: active, closed, archived',
            ], 422);
        }

        $targetWaStatus = match ($normalized) {
            WhatsappConversation::STATUS_CLOSED => WhatsappConversation::STATUS_CLOSED,
            WhatsappConversation::STATUS_ARCHIVED => WhatsappConversation::STATUS_ARCHIVED,
            default => WhatsappConversation::STATUS_OPEN,
        };

        $conversation->update(['status' => $targetWaStatus]);
        $this->syncLegacyConversation($conversation);

        $statusKey = match ($conversation->status) {
            WhatsappConversation::STATUS_CLOSED => 'closed',
            WhatsappConversation::STATUS_ARCHIVED => 'archived',
            default => 'active',
        };
        $statusLabel = match ($statusKey) {
            'closed' => 'Closed',
            'archived' => 'Archived',
            default => 'Active',
        };
        $isClosed = ($statusKey === 'closed');
        $isArchived = ($statusKey === 'archived');
        $isImportant = $conversation->hasTag(WhatsappConversation::TAG_IMPORTANT);
        $tags = $conversation->activeTags();
        $contactName = $conversation->contact_name ?? ($conversation->lead?->name ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Conversation status updated successfully',
            'status' => $statusKey,
            'status_label' => $statusLabel,
            'is_closed' => $isClosed,
            'is_archived' => $isArchived,
            'tags' => $tags,
            'is_important' => $isImportant,
            'data' => [
                'id' => (int) $id,
                'status' => $statusKey,
                'status_label' => $statusLabel,
                'contact_name' => $contactName,
                'is_closed' => $isClosed,
                'is_archived' => $isArchived,
                'tags' => $tags,
                'is_important' => $isImportant,
            ],
            'conversation' => $conversation,
        ]);
    }

    public function toggleTag(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $rawTag = mb_strtolower(trim((string) ($request->tag ?? $request->input('tag', ''))));
        $rawTag = str_replace([' ', '-', '(', ')'], ['_', '_', '', ''], $rawTag);

        if ($rawTag === '') {
            return response()->json([
                'success' => false,
                'message' => 'Tag cannot be empty',
            ], 422);
        }

        $tag = match (true) {
            str_contains($rawTag, 'pending') => WhatsappConversation::TAG_PENDING_PAYMENT,
            $rawTag === 'paid' => WhatsappConversation::TAG_PAID,
            $rawTag === 'important' => WhatsappConversation::TAG_IMPORTANT,
            default => $rawTag,
        };

        // Allow direct value specification if passed (e.g. action: 'add'/'remove', enabled: true/false, value: true/false)
        if ($request->has('action')) {
            $wantEnabled = in_array(strtolower((string) $request->input('action')), ['add', 'set', 'attach', '1', 'true'], true);
            $enabled = $conversation->setTag($tag, $wantEnabled);
        } elseif ($request->has('enabled') || $request->has('value')) {
            $wantEnabled = $request->boolean('enabled', $request->boolean('value', true));
            $enabled = $conversation->setTag($tag, $wantEnabled);
        } else {
            $enabled = $conversation->toggleTag($tag);
        }

        $this->syncLegacyConversation($conversation);

        $statusKey = match ($conversation->status) {
            WhatsappConversation::STATUS_CLOSED => 'closed',
            WhatsappConversation::STATUS_ARCHIVED => 'archived',
            default => 'active',
        };
        $statusLabel = match ($conversation->status) {
            WhatsappConversation::STATUS_CLOSED => 'Closed',
            WhatsappConversation::STATUS_ARCHIVED => 'Archived',
            default => 'Active',
        };
        $tags = $conversation->activeTags();
        $isImportant = $conversation->hasTag(WhatsappConversation::TAG_IMPORTANT);

        return response()->json([
            'success' => true,
            'message' => 'Tag updated successfully',
            'tag' => $tag,
            'enabled' => $enabled,
            'is_important' => $isImportant,
            'tag_label' => $conversation->tagLabel($tag),
            'tags' => $tags,
            'status' => $statusKey,
            'status_label' => $statusLabel,
            'is_closed' => $conversation->status === WhatsappConversation::STATUS_CLOSED,
            'is_archived' => $conversation->status === WhatsappConversation::STATUS_ARCHIVED,
            'data' => [
                'id' => (int) $id,
                'tag' => $tag,
                'enabled' => $enabled,
                'tags' => $tags,
                'status' => $statusKey,
            ],
            'conversation' => $conversation,
        ]);
    }

    /**
     * Set or toggle Important status on conversation.
     */
    public function setImportant(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        if ($request->has('action')) {
            $wantEnabled = in_array(strtolower((string) $request->input('action')), ['add', 'set', 'attach', '1', 'true'], true);
            $enabled = $conversation->setTag(WhatsappConversation::TAG_IMPORTANT, $wantEnabled);
        } elseif ($request->has('important') || $request->has('enabled') || $request->has('value')) {
            $wantEnabled = $request->boolean('important', $request->boolean('enabled', $request->boolean('value', true)));
            $enabled = $conversation->setTag(WhatsappConversation::TAG_IMPORTANT, $wantEnabled);
        } else {
            $enabled = $conversation->toggleTag(WhatsappConversation::TAG_IMPORTANT);
        }

        $this->syncLegacyConversation($conversation);

        $statusKey = match ($conversation->status) {
            WhatsappConversation::STATUS_CLOSED => 'closed',
            WhatsappConversation::STATUS_ARCHIVED => 'archived',
            default => 'active',
        };
        $statusLabel = match ($conversation->status) {
            WhatsappConversation::STATUS_CLOSED => 'Closed',
            WhatsappConversation::STATUS_ARCHIVED => 'Archived',
            default => 'Active',
        };
        $tags = $conversation->activeTags();

        return response()->json([
            'success' => true,
            'message' => $enabled ? 'Conversation marked as important' : 'Important tag removed from conversation',
            'is_important' => $enabled,
            'tag' => WhatsappConversation::TAG_IMPORTANT,
            'tag_label' => 'Important',
            'tags' => $tags,
            'status' => $statusKey,
            'status_label' => $statusLabel,
            'is_closed' => $conversation->status === WhatsappConversation::STATUS_CLOSED,
            'is_archived' => $conversation->status === WhatsappConversation::STATUS_ARCHIVED,
            'data' => [
                'id' => (int) $id,
                'is_important' => $enabled,
                'status' => $statusKey,
                'status_label' => $statusLabel,
                'tags' => $tags,
            ],
            'conversation' => $conversation,
        ]);
    }

    /**
     * Close the conversation.
     */
    public function closeConversation(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $conversation->close();
        $this->syncLegacyConversation($conversation);

        $tags = $conversation->activeTags();
        $isImportant = $conversation->hasTag(WhatsappConversation::TAG_IMPORTANT);
        $contactName = $conversation->contact_name ?? ($conversation->lead?->name ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Conversation closed successfully',
            'status' => 'closed',
            'status_label' => 'Closed',
            'is_closed' => true,
            'is_archived' => false,
            'tags' => $tags,
            'is_important' => $isImportant,
            'data' => [
                'id' => (int) $id,
                'status' => 'closed',
                'status_label' => 'Closed',
                'is_closed' => true,
                'is_archived' => false,
                'tags' => $tags,
                'contact_name' => $contactName,
            ],
            'conversation' => $conversation,
        ]);
    }

    /**
     * Archive the conversation.
     */
    public function archiveConversation(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $conversation->archive();
        $this->syncLegacyConversation($conversation);

        $tags = $conversation->activeTags();
        $isImportant = $conversation->hasTag(WhatsappConversation::TAG_IMPORTANT);
        $contactName = $conversation->contact_name ?? ($conversation->lead?->name ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Conversation archived successfully',
            'status' => 'archived',
            'status_label' => 'Archived',
            'is_closed' => false,
            'is_archived' => true,
            'tags' => $tags,
            'is_important' => $isImportant,
            'data' => [
                'id' => (int) $id,
                'status' => 'archived',
                'status_label' => 'Archived',
                'is_closed' => false,
                'is_archived' => true,
                'tags' => $tags,
                'contact_name' => $contactName,
            ],
            'conversation' => $conversation,
        ]);
    }

    /**
     * Reopen a closed or archived conversation.
     */
    public function reopenConversation(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $conversation->reopen();
        $this->syncLegacyConversation($conversation);

        $tags = $conversation->activeTags();
        $isImportant = $conversation->hasTag(WhatsappConversation::TAG_IMPORTANT);
        $contactName = $conversation->contact_name ?? ($conversation->lead?->name ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Conversation reopened successfully',
            'status' => 'active',
            'status_label' => 'Active',
            'is_closed' => false,
            'is_archived' => false,
            'tags' => $tags,
            'is_important' => $isImportant,
            'data' => [
                'id' => (int) $id,
                'status' => 'active',
                'status_label' => 'Active',
                'is_closed' => false,
                'is_archived' => false,
                'tags' => $tags,
                'contact_name' => $contactName,
            ],
            'conversation' => $conversation,
        ]);
    }

    protected function syncLegacyConversation(WhatsappConversation $conversation): void
    {
        try {
            $legacyId = $conversation->metadata['legacy_conversation_id'] ?? null;
            $legacy = $legacyId ? Conversation::find($legacyId) : null;
            if (!$legacy) {
                $phone = (string) $conversation->phone_number;
                $cleaned = preg_replace('/[^0-9]/', '', $phone);
                $last10 = strlen($cleaned) >= 10 ? substr($cleaned, -10) : $cleaned;
                $legacy = Conversation::where('phone_number', $phone)
                    ->orWhere('phone_number', 'like', "%{$last10}")
                    ->first();
            }
            if ($legacy) {
                $legMeta = is_array($legacy->metadata) ? $legacy->metadata : [];
                $legMeta['tags'] = $conversation->metadata['tags'] ?? [];
                $legacyStatus = match ($conversation->status) {
                    WhatsappConversation::STATUS_CLOSED => 'closed',
                    WhatsappConversation::STATUS_ARCHIVED => 'archived',
                    default => 'active',
                };
                $legacy->update([
                    'status' => $legacyStatus,
                    'metadata' => $legMeta,
                ]);
            }
        } catch (\Throwable $e) {
            // Ignore background sync errors
        }
    }

    public function pinMessage(Request $request, $conversationId, $messageId)
    {
        $conversation = $this->findInboxConversation($conversationId);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $message = $this->inboxService->resolveMessage($conversation, $messageId);
        if (!$message) {
            return response()->json(['success' => false, 'message' => 'Message not found'], 404);
        }

        $conversation->pinMessage($message, Auth::id());
        return response()->json(['success' => true, 'pinned_message' => $conversation->pinnedMessagePreview()]);
    }

    public function unpinMessage(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $conversation->unpinMessage();
        return response()->json(['success' => true, 'pinned_message' => null]);
    }

    public function setAiReplyPreference(Request $request, $id)
    {
        $conversation = $this->findInboxConversation($id);
        if (!$conversation) {
            return response()->json(['success' => false, 'message' => 'Conversation not found'], 404);
        }

        $request->validate(['ai_reply_enabled' => 'required|boolean']);
        $enabled = $request->boolean('ai_reply_enabled');
        $autoAi = app(\App\Services\AutoAiReplyService::class);

        $legacyId = $this->inboxService->legacyConversationId($conversation);
        $legacy = $legacyId ? \App\Models\Conversation::find($legacyId) : null;

        if ($legacy) {
            $autoAi->setChatAiEnabled($legacy, $enabled);
        } else {
            $autoAi->setChatAiEnabledForPhone((string) $conversation->phone_number, $enabled, null, $conversation);
        }

        // Re-read flag from the chat the UI is looking at
        $conversation->refresh();

        return response()->json([
            'success' => true,
            'ai_reply_enabled' => $conversation->aiReplyEnabled(),
        ]);
    }

    // Followups
    public function followupIndex(Request $request)
    {
        $followups = WhatsappFollowup::with(['lead', 'assignedUser', 'conversation'])
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->assigned_to, fn($q) => $q->where('assigned_user_id', $request->assigned_to))
            ->when($request->due_filter === 'today', fn($q) => $q->dueToday())
            ->when($request->due_filter === 'overdue', fn($q) => $q->overdue())
            ->when($request->due_filter === 'week', fn($q) => $q->dueThisWeek())
            ->orderBy('due_date')
            ->paginate(20);

        $users = User::orderBy('name')->get();
        $stats = [
            'pending' => WhatsappFollowup::pending()->count(),
            'overdue' => WhatsappFollowup::overdue()->count(),
            'today' => WhatsappFollowup::dueToday()->count(),
            'completed' => WhatsappFollowup::where('status', 'completed')->count(),
        ];

        return response()->json([
            'success' => true,
            'followups' => $followups,
            'users' => $users,
            'stats' => $stats
        ]);
    }

    public function followupStore(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lead_id' => 'required|exists:leads,id',
            'conversation_id' => 'nullable|exists:whatsapp_conversations,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'required|date',
            'assigned_user_id' => 'nullable|exists:users,id',
            'template_id' => 'nullable|exists:templates,id',
            'message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only([
            'lead_id', 'conversation_id', 'title', 'description', 
            'due_date', 'assigned_user_id', 'template_id', 'message'
        ]);
        $data['status'] = 'pending';
        $data['created_by'] = Auth::id();

        $followup = WhatsappFollowup::create($data);
        return response()->json(['success' => true, 'followup' => $followup->load(['lead', 'assignedUser'])]);
    }

    public function followupUpdate(Request $request, $id)
    {
        $followup = WhatsappFollowup::find($id);
        if (!$followup) {
            return response()->json(['success' => false, 'message' => 'Followup not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'sometimes|date',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'assigned_user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $data = $request->only(['title', 'description', 'due_date', 'status', 'assigned_user_id']);
        $data['updated_by'] = Auth::id();

        $followup->update($data);
        return response()->json(['success' => true, 'followup' => $followup]);
    }

    public function followupComplete($id)
    {
        $followup = WhatsappFollowup::find($id);
        if (!$followup) {
            return response()->json(['success' => false, 'message' => 'Followup not found'], 404);
        }

        $followup->markAsCompleted();

        if ($followup->conversation_id && ($followup->template_id || $followup->message)) {
            $conversation = $followup->conversation;
            if ($conversation) {
                if ($followup->template_id && $followup->template) {
                    $this->inboxService->sendTemplateMessage($conversation, $followup->template->name);
                } elseif ($followup->message) {
                    $this->inboxService->sendTextMessage($conversation, $followup->message);
                }
                $followup->markAsSent();
            }
        }

        return response()->json(['success' => true]);
    }

    public function followupDestroy($id)
    {
        $followup = WhatsappFollowup::find($id);
        if (!$followup) {
            return response()->json(['success' => false, 'message' => 'Followup not found'], 404);
        }

        $followup->delete();
        return response()->json(['success' => true]);
    }

    // Analytics
    public function analyticsDashboard(Request $request)
    {
        $userId = $request->user()?->getCompanyId();
        $isAdmin = strtolower((string) ($request->user()->role ?? '')) === 'admin';
        $isGlobal = $isAdmin && ($request->boolean('global', false) || $request->boolean('all', false));
        if ($isAdmin && $request->filled('user_id')) {
            $userId = (int) $request->query('user_id');
            $isGlobal = false;
        }

        $campaignQuery = WhatsappCampaign::query();
        if (! $isGlobal && $userId) {
            $campaignQuery->where('userId', $userId);
        }

        $stats = [
            'total_leads' => Lead::count(),
            'new_leads_today' => Lead::whereDate('created_at', today())->count(),
            'total_conversations' => WhatsappConversation::count(),
            'open_conversations' => WhatsappConversation::where('status', 'open')->count(),
            'unread_messages' => WhatsappConversation::sum('unread_count'),
            'messages_today' => WhatsappMessage::whereDate('created_at', today())->count(),
            'total_campaigns' => (clone $campaignQuery)->count(),
            'campaign_draft' => (clone $campaignQuery)->where('status', 'draft')->count(),
            'campaign_scheduled' => (clone $campaignQuery)->where('status', 'scheduled')->count(),
            'campaign_completed' => (clone $campaignQuery)->where('status', 'completed')->count(),
            'messages_sent_total' => WhatsappMessage::where('direction', 'outgoing')->count(),
            'messages_received_total' => WhatsappMessage::where('direction', 'incoming')->count(),
        ];

        $recentCampaigns = (clone $campaignQuery)->with('template')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'recentCampaigns' => $recentCampaigns,
            'meta' => [
                'userId' => $isGlobal ? null : $userId,
            ],
        ]);
    }

    public function analyticsChartData(Request $request)
    {
        $period = $request->period ?? '7days';
        $days = $period === '30days' ? 30 : 7;
        $startDate = now()->subDays($days);

        $data = WhatsappMessage::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as total'),
            DB::raw("SUM(CASE WHEN direction = 'incoming' THEN 1 ELSE 0 END) as incoming"),
            DB::raw("SUM(CASE WHEN direction = 'outgoing' THEN 1 ELSE 0 END) as outgoing")
        )
            ->where('created_at', '>=', $startDate)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json(['success' => true, 'data' => $data]);
    }

    // Helper formatting methods
    private function formatMessage(WhatsappMessage $message, ?WhatsappConversation $conversation = null): array
    {
        $conversation = $conversation ?? $message->conversation;
        $capabilities = $conversation
            ? $this->inboxService->buildWhatsappMessageCapabilities($message, $conversation)
            : [
                'can_react' => $message->canReact(),
                'can_reply' => false,
                'react_message_id' => $message->canReact() ? $message->apiMessageId() : null,
                'reply_to_message_id' => null,
                'inbox_conversation_id' => null,
                'meta_message_id' => $message->meta_message_id,
                'react_endpoint' => null,
                'reply_endpoint' => null,
            ];

        $replyContext = $message->replyContext();

        return [
            'id' => $message->apiMessageId(),
            'whatsapp_message_id' => $message->id,
            'legacy_message_id' => $message->legacyMessageId(),
            'direction' => $message->direction,
            'message' => $message->message,
            'message_type' => $message->message_type,
            'media_url' => $message->displayMediaUrl(),
            'media_type' => $message->media_type,
            'status' => $message->status,
            'reactions' => $message->reactionDetails(),
            'reaction_summary' => $message->reactionSummary(),
            'reaction_emojis' => $message->reactionEmojis(),
            'location' => $message->message_type === 'location' ? $message->locationData() : null,
            'contacts' => in_array($message->message_type, ['contacts', 'contact'], true) ? $message->contactsData() : [],
            'media_display' => $this->buildMessageMediaDisplay($message),
            'revoked' => $message->isRevoked(),
            'reply_to' => $replyContext,
            'is_reply' => $replyContext !== null,
            'meta_message_id' => $message->meta_message_id,
            'created_at' => $message->created_at?->format('Y-m-d H:i:s'),
            'time' => $message->created_at?->format('h:i A'),
            'date' => $message->created_at?->format('d M Y'),
            'can_react' => $capabilities['can_react'],
            'can_reply' => $capabilities['can_reply'],
            'react_message_id' => $capabilities['react_message_id'],
            'reply_to_message_id' => $replyContext['message_id'] ?? $capabilities['reply_to_message_id'],
            'inbox_conversation_id' => $capabilities['inbox_conversation_id'],
            'react_endpoint' => $capabilities['react_endpoint'],
            'reply_endpoint' => $capabilities['reply_endpoint'],
            'capabilities' => $capabilities,
        ];
    }

    private function buildMessageMediaDisplay(WhatsappMessage $message): array
    {
        $meta = is_array($message->metadata) ? $message->metadata : [];

        // 1. Order message check
        $hasOrder = !empty($meta['order']) || !empty($meta['order_details']) || ($meta['type'] ?? '') === 'order' || ($meta['raw_type'] ?? '') === 'order';
        if ($hasOrder) {
            $rawOrder = $meta['order'] ?? ($meta['order_details'] ?? []);
            $enrichedOrder = app(\App\Services\WhatsAppCatalogService::class)->enrichOrderData(is_array($rawOrder) ? $rawOrder : []);
            $primaryImg = $enrichedOrder['image_url'] ?? null;

            return [
                'type' => 'order',
                'is_order' => true,
                'content' => $message->message,
                'image_url' => $primaryImg,
                'url' => $primaryImg,
                'thumbnail_url' => $primaryImg,
                'order' => $enrichedOrder,
            ];
        }

        // 2. Product inquiry (referred_product) check
        $hasProductInquiry = !empty($meta['referred_product']) || !empty($meta['context']['referred_product']);
        if ($hasProductInquiry) {
            $rawRef = $meta['referred_product'] ?? ($meta['context']['referred_product'] ?? []);
            $enrichedRef = app(\App\Services\WhatsAppCatalogService::class)->enrichReferredProduct(is_array($rawRef) ? $rawRef : []);
            $primaryImg = $enrichedRef['image_url'] ?? null;

            return [
                'type' => 'product_inquiry',
                'is_product_inquiry' => true,
                'content' => $message->message,
                'image_url' => $primaryImg,
                'url' => $primaryImg,
                'thumbnail_url' => $primaryImg,
                'referred_product' => $enrichedRef,
            ];
        }

        if ($message->message_type === 'location') {
            return [
                'type' => 'location',
                'is_location' => true,
                'location' => $message->locationData(),
                'content' => $message->message,
            ];
        }

        if (in_array($message->message_type, ['contacts', 'contact'], true)) {
            return [
                'type' => 'contacts',
                'is_contacts' => true,
                'contacts' => $message->contactsData(),
                'content' => $message->message,
            ];
        }

        // 3. Catalog Product message check
        $catalogMedia = app(\App\Services\WhatsAppCatalogService::class)->resolveCatalogProductMedia(
            (string) $message->message,
            $meta,
            $message->media_url
        );

        if ($catalogMedia && !empty($catalogMedia['image_url'])) {
            $primaryImg = $catalogMedia['image_url'];
            $catalogProd = $catalogMedia['product'] ?? null;

            $res = [
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
                'caption' => $message->message,
                'content' => $message->message,
            ];

            if (!empty($meta['interactive'])) {
                $res['is_interactive'] = true;
                $res['interactive'] = $meta['interactive'];
            }

            if (!empty($catalogProd)) {
                $res['product'] = $catalogProd;
                $res['catalog_product'] = $catalogProd;
            }

            return $res;
        }

        if ($message->message_type === 'text' || empty($message->media_url)) {
            $res = [
                'type' => 'text',
                'is_text' => true,
                'content' => $message->message,
            ];
            if (!empty($meta['interactive'])) {
                $res['is_interactive'] = true;
                $res['interactive'] = $meta['interactive'];
            }
            return $res;
        }

        return [
            'type' => $message->message_type,
            'mime_type' => $message->media_type,
            'url' => $message->displayMediaUrl(),
            'content' => $message->message,
            'is_image' => $message->message_type === 'image',
            'is_video' => $message->message_type === 'video',
            'is_audio' => $message->message_type === 'audio',
            'is_document' => $message->message_type === 'document',
        ];
    }

    private function formatConversation(WhatsappConversation $conversation): array
    {
        $latest = $conversation->latestMessage;
        $capabilities = $this->inboxService->buildConversationCapabilities($conversation);

        return [
            'id' => $capabilities['inbox_conversation_id'],
            'whatsapp_conversation_id' => $conversation->id,
            'legacy_conversation_id' => $capabilities['legacy_conversation_id'],
            'phone_number' => $conversation->phone_number,
            'contact_name' => $conversation->contact_name ?? $conversation->phone_number,
            'status' => $conversation->status,
            'status_label' => $conversation->statusLabel(),
            'is_closed' => $conversation->status === WhatsappConversation::STATUS_CLOSED,
            'is_archived' => $conversation->status === WhatsappConversation::STATUS_ARCHIVED,
            'tags' => $conversation->activeTags(),
            'is_important' => $conversation->hasTag(WhatsappConversation::TAG_IMPORTANT),
            'unread_count' => $conversation->unread_count,
            'last_message_at' => $conversation->last_message_at?->diffForHumans(),
            'last_message' => $latest?->message,
            'lead_id' => $conversation->lead_id,
            'lead_name' => $conversation->lead?->name,
            'messaging_window_active' => $capabilities['messaging_window_active'],
            'can_send_freeform' => $capabilities['can_send_freeform'],
            'can_send_media' => $capabilities['can_send_media'],
            'can_reply' => $capabilities['can_reply'],
            'can_react' => $capabilities['can_react'],
            'ai_reply_enabled' => $conversation->aiReplyEnabled(),
            'capabilities' => $capabilities,
        ];
    }
}
