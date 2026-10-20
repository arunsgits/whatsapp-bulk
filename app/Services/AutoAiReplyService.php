<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Settings;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Env;
use Illuminate\Support\Str;

class AutoAiReplyService
{
    public function __construct(
        protected WhatsAppInboxService $inboxService
    ) {
    }

    /**
     * Auto-reply for ANY business using free AI + business profile context.
     */
    public function maybeAutoReply(
        Conversation $legacyConversation,
        string $incomingText,
        string $messageType = 'text',
        ?string $inboundMetaMessageId = null,
        ?array $rawWebhookMessage = null
    ): void {
        try {
            if (! $this->shouldReplyToType($messageType)) {
                return;
            }

            $text = trim($incomingText);
            if ($text === '' || mb_strlen($text) > 2000) {
                return;
            }

            $userId = (int) ($legacyConversation->user_id ?: 0);
            if (! $userId) {
                $legacyConversation->refresh();
                $userId = (int) ($legacyConversation->user_id ?: 0);
            }

            $settings = $this->resolveSettings($userId ?: null);
            if (! $settings) {
                $whatsappConversation = $this->inboxService->ensureWhatsappConversationForLegacy($legacyConversation);
                $ownerCandidate = (int) ($whatsappConversation->assigned_user_id ?: ($whatsappConversation->metadata['owner_user_id'] ?? 0));
                if ($ownerCandidate) {
                    $userId = $ownerCandidate;
                    if (! $legacyConversation->user_id) {
                        $legacyConversation->update(['user_id' => $userId]);
                    }
                    $settings = $this->resolveSettings($userId);
                }
            }

            if (! $settings || ! (bool) ($settings->auto_ai_enabled ?? false)) {
                Log::info('Auto AI skipped: global auto_ai_enabled is off', [
                    'conversation_id' => $legacyConversation->id,
                ]);

                return;
            }

            // Fresh DB state (toggle may have changed after webhook started)
            $legacyConversation->refresh();

            // Hard stop if Auto AI is off for this phone on ANY chat row (UI/webhook mismatch safe)
            if ($this->isAutoAiDisabledForPhone((string) $legacyConversation->phone_number, $legacyConversation)) {
                Log::info('Auto AI skipped: disabled for this phone/chat', [
                    'conversation_id' => $legacyConversation->id,
                    'phone' => $legacyConversation->phone_number,
                ]);

                return;
            }

            $whatsappConversation = $this->inboxService->ensureWhatsappConversationForLegacy($legacyConversation);
            $whatsappConversation->refresh();

            if (! $this->isChatAiEnabled($legacyConversation, $whatsappConversation)) {
                Log::info('Auto AI skipped: chat AI reply is off', [
                    'conversation_id' => $legacyConversation->id,
                    'whatsapp_conversation_id' => $whatsappConversation->id,
                ]);

                return;
            }

            if ($this->alreadyRepliedToInbound($whatsappConversation, $inboundMetaMessageId)) {
                return;
            }

            // Soft anti-burst first (avoid double close on rapid messages)
            $veryRecent = WhatsappMessage::query()
                ->where('conversation_id', $whatsappConversation->id)
                ->where('direction', 'outgoing')
                ->where('metadata->auto_ai', true)
                ->where('created_at', '>=', now()->subSeconds(8))
                ->exists();
            if ($veryRecent) {
                return;
            }

            $history = $this->buildHistory($whatsappConversation);
            $lastAiReply = $this->lastAutoAiReply($whatsappConversation);
            $style = $this->resolveReplyStyle($history, $text);
            $isGreeting = $this->isPureGreeting(mb_strtolower($text));
            $lower = mb_strtolower($text);

            if ($isGreeting) {
                $this->clearLeadState($whatsappConversation);
            }

            // 1. Immediate friendly order acknowledgment for WhatsApp Cart orders
            if ($messageType === 'order' || str_starts_with($text, '🛒 Order')) {
                $bizName = $settings->business_name ?? 'our store';
                $lastMsg = $legacyConversation->messages()->latest('id')->first();
                $orderMeta = $rawWebhookMessage['order'] ?? ($lastMsg?->metadata['order'] ?? null);
                $itemSummary = '';
                $primaryImageUrl = null;

                if (!empty($orderMeta) && is_array($orderMeta)) {
                    $enrichedOrder = app(\App\Services\WhatsAppCatalogService::class)->enrichOrderData($orderMeta, (int) $legacyConversation->user_id);
                    $primaryImageUrl = $enrichedOrder['image_url'] ?? null;
                    $lines = [];
                    foreach (array_slice($enrichedOrder['product_items'] ?? [], 0, 4) as $item) {
                        $lines[] = "• " . ($item['name'] ?? 'Product') . " × " . ($item['quantity'] ?? 1) . " (" . ($item['formatted_price'] ?? '') . ")";
                    }
                    if (!empty($lines)) {
                        $itemSummary = "\n\nItems:\n" . implode("\n", $lines) . "\nTotal: " . ($enrichedOrder['formatted_total'] ?? '');
                    }
                }

                $orderAck = "Thank you for your order! We have received your cart request.{$itemSummary}\n\nOur team at {$bizName} is reviewing your items and will confirm your order and delivery details shortly. 🙏";
                $this->sendAutoAiMessage(
                    $whatsappConversation,
                    $legacyConversation,
                    $orderAck,
                    $inboundMetaMessageId,
                    'order_acknowledgement',
                    $primaryImageUrl
                );
                return;
            }

            // 2. Predefined first-time user welcome message (if configured in Admin User Settings)
            $ownerId = (int) ($legacyConversation->user_id ?: ($whatsappConversation->assigned_user_id ?: ($whatsappConversation->metadata['owner_user_id'] ?? 0)));
            $firstTimeMsg = $this->resolveFirstTimeMessage($settings, $ownerId);

            if ($firstTimeMsg !== '' && $this->isFirstTimeUserMessage($whatsappConversation, $legacyConversation, $settings)) {
                $this->sendAutoAiMessage(
                    $whatsappConversation,
                    $legacyConversation,
                    $firstTimeMsg,
                    $inboundMetaMessageId,
                    'first_time_message'
                );

                $this->markFirstTimeMessageSent($whatsappConversation, $legacyConversation);

                Log::info('Sent predefined first_time_message to first-time WhatsApp user', [
                    'conversation_id' => $legacyConversation->id,
                    'whatsapp_conversation_id' => $whatsappConversation->id,
                    'phone' => $legacyConversation->phone_number,
                ]);

                return;
            }

            // 3. Product Inquiry / referred product from Meta Catalog
            $referredProduct = $rawWebhookMessage['context']['referred_product']
                ?? ($rawWebhookMessage['referred_product'] ?? null);

            if (! $referredProduct) {
                $lastInboundMsg = $legacyConversation->messages()
                    ->where('direction', 'inbound')
                    ->latest('id')
                    ->first();
                $meta = is_array($lastInboundMsg?->metadata) ? $lastInboundMsg->metadata : [];
                $referredProduct = $meta['referred_product'] ?? ($meta['context']['referred_product'] ?? null);
            }

            $skuFromText = null;
            if (preg_match('/\[Product Inquiry:\s*#?([A-Za-z0-9_-]+)\]/i', $text, $m)) {
                $skuFromText = $m[1];
            } elseif (preg_match('/#([A-Za-z0-9_-]{2,30})/i', $text, $m)) {
                $skuFromText = $m[1];
            }

            $catalogSku = $referredProduct['product_retailer_id'] ?? ($referredProduct['retailer_id'] ?? $skuFromText);
            $catalogId = $referredProduct['catalog_id'] ?? null;
            $ownerUserId = (int) ($legacyConversation->user_id ?: ($settings->userId ?? 0));

            if ($catalogSku && $this->hasCatalogPermission($ownerUserId)) {
                $catalogService = app(\App\Services\WhatsAppCatalogService::class);
                $product = $catalogService->resolveProductDetails((string) $catalogSku, $catalogId, $ownerUserId);

                if ($product) {
                    $prodName = $product['name'] ?: "Product #{$catalogSku}";
                    $priceStr = $product['formatted_price'] ?: (!empty($product['price']) ? ($product['currency'] . ' ' . number_format($product['price'], 2)) : '');
                    $desc = !empty($product['description']) ? "\n\n" . trim($product['description']) : '';
                    $availability = !empty($product['availability']) ? "\n📦 Status: " . ucfirst(str_replace('_', ' ', $product['availability'])) : '';
                    $priceLine = $priceStr ? "\n💰 Price: {$priceStr}" : '';
                    $imageUrl = $product['image_url'] ?? null;

                    $replyText = "🛍️ *{$prodName}*{$priceLine}{$availability}{$desc}\n\nThank you for reaching out! Would you like to place an order or know more details? Our team is here to assist you. 🙏";

                    $this->sendAutoAiMessage(
                        $whatsappConversation,
                        $legacyConversation,
                        $replyText,
                        $inboundMetaMessageId,
                        'product_catalog_response',
                        $imageUrl
                    );
                    return;
                }
            }

            // 3. Natural Language Catalog & Product Queries (Exact Matched Product / Variety / Store Catalog)
            // Triggered ONLY for explicit retail catalog/shopping queries when user has catalog permission; service, real estate, and general queries pass to AI
            if (! $isGreeting && ! $this->isPureGreeting(mb_strtolower($text)) && $this->hasCatalogPermission($ownerUserId) && $this->hasExplicitCatalogOrShoppingIntent($text, $settings, $ownerUserId)) {
                try {
                    $catalogHandled = $this->handleCatalogAutoReply(
                        $whatsappConversation,
                        $legacyConversation,
                        $text,
                        $inboundMetaMessageId,
                        $settings,
                        $history
                    );
                    if ($catalogHandled) {
                        return;
                    }
                } catch (\Throwable $catEx) {
                    Log::warning('Auto AI catalog query handling warning: ' . $catEx->getMessage(), [
                        'conversation_id' => $legacyConversation->id,
                        'text' => Str::limit($text, 100),
                    ]);
                }
            }

            // 4. Store Address & Map Location Queries (Provides complete address + Google Maps location link)
            if (! $isGreeting && ! $this->isPureGreeting(mb_strtolower($text))) {
                try {
                    $locationHandled = $this->handleLocationAutoReply(
                        $whatsappConversation,
                        $legacyConversation,
                        $text,
                        $inboundMetaMessageId,
                        $settings
                    );
                    if ($locationHandled) {
                        return;
                    }
                } catch (\Throwable $locEx) {
                    Log::warning('Auto AI location query handling warning: ' . $locEx->getMessage(), [
                        'conversation_id' => $legacyConversation->id,
                        'text' => Str::limit($text, 100),
                    ]);
                }
            }

            // 5. High-Priority Smart FAQ & Knowledge Base Matcher (Matches verified company FAQs)
            if (! $isGreeting && ! $this->isPureGreeting(mb_strtolower($text))) {
                try {
                    $faqHandled = $this->handleFaqAutoReply(
                        $whatsappConversation,
                        $legacyConversation,
                        $text,
                        $inboundMetaMessageId,
                        $settings
                    );
                    if ($faqHandled) {
                        return;
                    }
                } catch (\Throwable $faqEx) {
                    Log::warning('Auto AI FAQ query handling warning: ' . $faqEx->getMessage(), [
                        'conversation_id' => $legacyConversation->id,
                        'text' => Str::limit($text, 100),
                    ]);
                }
            }

            // After lead is locked, only soft human ack — never restart budget/pages questions
            if (! $isGreeting && $this->isLeadMarkedComplete($whatsappConversation)) {
                if ($this->isSoftFollowUp($lower)) {
                    // Plain sure/thanks after lock → stay quiet (no spam)
                    if (
                        preg_match('/^(sure|ok|okay|thanks|thank you|thx|haan|han|yes|ji)(\s+sir)?[\s!.]*$/iu', $lower)
                        && $this->recentClosingSent($whatsappConversation, 30)
                    ) {
                        return;
                    }
                    // "please do fast" / number confirm → one short human ack
                    if ($this->recentClosingSent($whatsappConversation, 2)) {
                        return;
                    }
                    $reply = $this->softAckReply($style);
                    $this->sendAutoAiMessage($whatsappConversation, $legacyConversation, $reply, $inboundMetaMessageId, 'local_soft_ack');

                    return;
                }

                // Brand-new requirement after lock → reset lead and continue
                $this->clearLeadState($whatsappConversation);
            }

            // ALWAYS try live AI first (Gemini → OpenAI → Groq → OpenRouter → Pollinations).
            // Static templates only if every provider fails — that is why replies felt "not AI".
            $reply = null;
            $provider = 'local_fallback';

            try {
                $aiResult = $this->callFreeAi($settings, $history, $text, $lastAiReply, $whatsappConversation);
                $aiReply = is_array($aiResult) ? (string) ($aiResult['text'] ?? '') : (string) ($aiResult ?? '');
                $aiProvider = is_array($aiResult) ? (string) ($aiResult['provider'] ?? 'unknown') : 'unknown';

                if (filled($aiReply)) {
                    $clean = $this->sanitizeReply($aiReply);
                    // Only drop AI if it re-asks known fields or is empty after sanitize.
                    // Do NOT reject for style mismatch — that was forcing static templates.
                    if (
                        filled($clean)
                        && ! $this->asksForAlreadyKnown($clean, $text, $history, $whatsappConversation)
                        && ! $this->isObviouslyBrokenAi($clean)
                        && ! $this->isOffCategoryScript($clean, $settings)
                    ) {
                        $reply = $clean;
                        $provider = $aiProvider;
                    } else {
                        Log::info('Auto AI reply rejected after sanitize/known-field check', [
                            'provider' => $aiProvider,
                            'preview' => Str::limit($clean, 120),
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Auto AI call failed', ['error' => $e->getMessage()]);
            }

            if (! filled($reply)) {
                Log::warning('Auto AI using STATIC fallback — add GEMINI_API_KEY in .env for real AI', [
                    'conversation_id' => $legacyConversation->id,
                ]);
                $reply = $this->generateKnowledgeFallback($settings, $text, $history, $lastAiReply, $whatsappConversation);
                $provider = 'local_fallback';
            }

            if (! $isGreeting && $lastAiReply && $this->isTooSimilar($reply, $lastAiReply)) {
                // Never send another near-identical close/template
                if ($this->looksLikeClosingReply($lastAiReply) || $this->looksLikeClosingReply((string) $reply)) {
                    return;
                }
                // Ask AI once more for a different next step before static
                try {
                    $retry = $this->callFreeAi($settings, $history, $text."\n(Do not repeat your last reply. Ask only the next missing detail.)", $lastAiReply, $whatsappConversation);
                    $retryText = is_array($retry) ? (string) ($retry['text'] ?? '') : (string) ($retry ?? '');
                    if (filled($retryText) && ! $this->isTooSimilar($retryText, $lastAiReply)) {
                        $reply = $this->sanitizeReply($retryText);
                        $provider = is_array($retry) ? (string) ($retry['provider'] ?? $provider) : $provider;
                    } else {
                        $reply = $this->nextStepAfterRepeat($settings, $text, $history, $lastAiReply, $whatsappConversation);
                        $provider = 'local_fallback';
                    }
                } catch (\Throwable $e) {
                    $reply = $this->nextStepAfterRepeat($settings, $text, $history, $lastAiReply, $whatsappConversation);
                    $provider = 'local_fallback';
                }
                if ($this->isTooSimilar($reply, $lastAiReply)) {
                    return;
                }
            }

            // Persist lead + mark complete when closing
            $lead = $this->resolveLeadState($whatsappConversation, $history, $text);
            $this->persistLeadState($whatsappConversation, $lead);
            if ($this->looksLikeClosingReply((string) $reply) || ($lead['isComplete'] ?? false)) {
                $this->markLeadComplete($whatsappConversation, $lead);
                if ($this->recentClosingSent($whatsappConversation, 15)) {
                    return;
                }
            }

            $this->sendAutoAiMessage($whatsappConversation, $legacyConversation, (string) $reply, $inboundMetaMessageId, $provider);
        } catch (\Throwable $e) {
            Log::error('Auto AI reply exception', [
                'conversation_id' => $legacyConversation->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function sendAutoAiMessage(
        WhatsappConversation $whatsappConversation,
        Conversation $legacyConversation,
        string $reply,
        ?string $inboundMetaMessageId,
        string $provider = 'unknown',
        ?string $imageUrl = null
    ): void {
        // Strip hallucinated markdown images and fake URLs
        $reply = preg_replace('/!\[.*?\]\([^\)]+\)/u', '', $reply) ?: $reply;
        $reply = preg_replace('/https?:\/\/(?:i\.)?imgur\.com\/\S+/iu', '', $reply) ?: $reply;
        $reply = preg_replace('/https?:\/\/(?:images\.unsplash\.com|via\.placeholder\.com)\/\S+/iu', '', $reply) ?: $reply;
        $reply = trim(preg_replace("/\n{3,}/", "\n\n", $reply));

        if ($imageUrl && (str_contains($imageUrl, 'imgur.com') || str_contains($imageUrl, 'via.placeholder'))) {
            $imageUrl = null;
        }

        $error = null;
        $message = null;

        if ($imageUrl) {
            $message = $this->inboxService->sendMediaUrlMessage(
                $whatsappConversation,
                $imageUrl,
                'image',
                $reply,
                null,
                $error
            );
        }

        if (! $message) {
            $message = $this->inboxService->sendTextMessage(
                $whatsappConversation,
                $reply,
                null,
                $error
            );
        }

        if ($message) {
            $meta = is_array($message->metadata) ? $message->metadata : [];
            $meta['auto_ai'] = true;
            $meta['auto_ai_provider'] = $provider;
            $meta['auto_ai_for_meta_message_id'] = $inboundMetaMessageId;
            if ($provider === 'first_time_message') {
                $meta['first_time_message'] = true;
            }
            if ($imageUrl) {
                $meta['image_url'] = $imageUrl;
            }
            if ($this->looksLikeClosingReply($reply)) {
                $meta['auto_ai_closing'] = true;
            }
            $message->update(['metadata' => $meta]);

            if ($message->meta_message_id) {
                $legacyMessage = Message::where('whatsapp_message_id', $message->meta_message_id)->first();
                if ($legacyMessage) {
                    $legMeta = is_array($legacyMessage->metadata) ? $legacyMessage->metadata : [];
                    $legMeta['auto_ai'] = true;
                    $legMeta['auto_ai_provider'] = $provider;
                    $legMeta['auto_ai_for_meta_message_id'] = $inboundMetaMessageId;
                    if ($provider === 'first_time_message') {
                        $legMeta['first_time_message'] = true;
                    }
                    if ($imageUrl) {
                        $legMeta['image_url'] = $imageUrl;
                    }
                    if ($this->looksLikeClosingReply($reply)) {
                        $legMeta['auto_ai_closing'] = true;
                    }
                    $legUpdate = ['metadata' => $legMeta];
                    if ($imageUrl && empty($legacyMessage->media_url)) {
                        $legUpdate['media_url'] = $imageUrl;
                        $legUpdate['media_type'] = 'image/jpeg';
                        $legUpdate['type'] = 'image';
                    }
                    $legacyMessage->update($legUpdate);
                }
            }

            Log::info('Auto AI message sent', [
                'conversation_id' => $legacyConversation->id,
                'provider' => $provider,
                'has_image' => !empty($imageUrl),
                'preview' => Str::limit($reply, 100),
            ]);
        } elseif ($error) {
            Log::warning('Auto AI reply send failed', [
                'conversation_id' => $legacyConversation->id,
                'error' => $error,
            ]);
        }
    }

    /**
     * Check if this incoming message is from a first-time user.
     */
    public function isFirstTimeUserMessage(
        WhatsappConversation $whatsappConversation,
        Conversation $legacyConversation,
        ?Settings $settings = null
    ): bool {
        $waMeta = is_array($whatsappConversation->metadata) ? $whatsappConversation->metadata : [];
        $legacyMeta = is_array($legacyConversation->metadata) ? $legacyConversation->metadata : [];

        // 1. Check if a first_time_message was ALREADY sent in this WhatsApp conversation
        $alreadySentWelcome = WhatsappMessage::query()
            ->where('conversation_id', $whatsappConversation->id)
            ->where('direction', 'outgoing')
            ->where(function ($q) {
                $q->where('metadata->auto_ai_provider', 'first_time_message')
                    ->orWhere('metadata->first_time_message', true);
            })
            ->exists();

        if ($alreadySentWelcome) {
            return false;
        }

        // Check if already sent in legacy conversation
        $alreadySentLegacyWelcome = $legacyConversation->messages()
            ->where('direction', 'outbound')
            ->where(function ($q) {
                $q->where('metadata->auto_ai_provider', 'first_time_message')
                    ->orWhere('metadata->first_time_message', true);
            })
            ->exists();

        if ($alreadySentLegacyWelcome) {
            return false;
        }

        // 2. Check metadata flag - only respect it if there has actually been at least one outgoing message
        if (!empty($waMeta['first_time_message_sent']) || !empty($legacyMeta['first_time_message_sent'])) {
            $hasAnyOutgoing = WhatsappMessage::query()
                ->where('conversation_id', $whatsappConversation->id)
                ->where('direction', 'outgoing')
                ->exists()
                || $legacyConversation->messages()->where('direction', 'outbound')->exists();

            if ($hasAnyOutgoing) {
                return false;
            }
        }

        // 3. Check if an Auto AI reply was already sent in this conversation (excluding order acknowledgments)
        $hasAiReply = WhatsappMessage::query()
            ->where('conversation_id', $whatsappConversation->id)
            ->where('direction', 'outgoing')
            ->where('metadata->auto_ai', true)
            ->where('metadata->auto_ai_provider', '!=', 'order_acknowledgement')
            ->exists();

        if ($hasAiReply) {
            return false;
        }

        $hasLegacyAiReply = $legacyConversation->messages()
            ->where('direction', 'outbound')
            ->where('metadata->auto_ai', true)
            ->where('metadata->auto_ai_provider', '!=', 'order_acknowledgement')
            ->exists();

        if ($hasLegacyAiReply) {
            return false;
        }

        // 4. Check if human agent has already replied to this customer (not campaign/broadcasts)
        $hasHumanReply = WhatsappMessage::query()
            ->where('conversation_id', $whatsappConversation->id)
            ->where('direction', 'outgoing')
            ->whereNull('metadata->template_name')
            ->whereNull('metadata->campaign_id')
            ->where(function ($q) {
                $q->whereNull('metadata->auto_ai')
                    ->orWhere('metadata->auto_ai', false);
            })
            ->exists();

        if ($hasHumanReply) {
            return false;
        }

        // 5. Check if this phone number already received a first_time_message across any conversation for this account owner
        $phone = $this->normalizePhone((string) ($whatsappConversation->phone_number ?: $legacyConversation->phone_number));
        $ownerId = $whatsappConversation->assigned_user_id 
            ?: (($waMeta['owner_user_id'] ?? null) ?: $legacyConversation->user_id);

        if ($phone !== '') {
            $alreadySentToPhone = WhatsappMessage::query()
                ->where('direction', 'outgoing')
                ->where(function ($q) {
                    $q->where('metadata->auto_ai_provider', 'first_time_message')
                        ->orWhere('metadata->first_time_message', true);
                })
                ->whereHas('conversation', function ($q) use ($phone, $ownerId) {
                    $q->where('phone_number', $phone);
                    if ($ownerId) {
                        $q->where(function ($sub) use ($ownerId) {
                            $sub->where('assigned_user_id', $ownerId)
                                ->orWhere('metadata->owner_user_id', $ownerId);
                        });
                    }
                })
                ->exists();

            if ($alreadySentToPhone) {
                return false;
            }

            // Check if this contact has an older, already active conversation with outgoing replies for this owner
            $hasPriorRepliedChat = WhatsappConversation::query()
                ->where('phone_number', $phone)
                ->where('id', '!=', $whatsappConversation->id)
                ->where(function ($sub) use ($ownerId) {
                    if ($ownerId) {
                        $sub->where('assigned_user_id', $ownerId)
                            ->orWhere('metadata->owner_user_id', $ownerId);
                    }
                })
                ->whereHas('messages', function ($q) {
                    $q->where('direction', 'outgoing');
                })
                ->exists();

            if ($hasPriorRepliedChat) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resolve configured first time welcome message from settings or user profile.
     */
    public function resolveFirstTimeMessage(?Settings $settings, ?int $userId = null): string
    {
        if ($settings) {
            $msg = trim((string) ($settings->first_time_message ?? ($settings->firstTimeMessage ?? '')));
            if ($msg !== '') {
                return $msg;
            }
        }

        if ($userId) {
            $candidate = Settings::where('userId', $userId)
                ->whereNotNull('first_time_message')
                ->where('first_time_message', '!=', '')
                ->orderByDesc('id')
                ->first();

            if ($candidate) {
                $msg = trim((string) $candidate->first_time_message);
                if ($msg !== '') {
                    return $msg;
                }
            }
        }

        return '';
    }

    /**
     * Mark the first_time_message_sent flag on conversation metadata.
     */
    public function markFirstTimeMessageSent(
        WhatsappConversation $whatsappConversation,
        Conversation $legacyConversation
    ): void {
        try {
            $waMeta = is_array($whatsappConversation->metadata) ? $whatsappConversation->metadata : [];
            $waMeta['first_time_message_sent'] = true;
            $whatsappConversation->update(['metadata' => $waMeta]);

            $legacyMeta = is_array($legacyConversation->metadata) ? $legacyConversation->metadata : [];
            $legacyMeta['first_time_message_sent'] = true;
            $legacyConversation->update(['metadata' => $legacyMeta]);
        } catch (\Throwable $e) {
            Log::warning('Could not mark first_time_message_sent: ' . $e->getMessage());
        }
    }

    public function isChatAiEnabled(Conversation $legacy, ?WhatsappConversation $whatsapp = null): bool
    {
        return ! $this->isAutoAiDisabledForPhone((string) $legacy->phone_number, $legacy, $whatsapp);
    }

    /**
     * True when Auto AI must NOT reply for this contact (any matching chat/legacy row).
     */
    public function isAutoAiDisabledForPhone(
        string $phone,
        ?Conversation $legacy = null,
        ?WhatsappConversation $whatsapp = null
    ): bool {
        if ($legacy) {
            $legacyMeta = is_array($legacy->metadata) ? $legacy->metadata : [];
            if ($this->isAiDisabledFlag($legacyMeta['ai_reply_disabled'] ?? null)) {
                return true;
            }
        }

        if ($whatsapp) {
            $waMeta = is_array($whatsapp->metadata) ? $whatsapp->metadata : [];
            if ($this->isAiDisabledFlag($waMeta['ai_reply_disabled'] ?? null)) {
                return true;
            }
        }

        foreach ($this->findWhatsappConversationsByPhone($phone, $legacy) as $linked) {
            $meta = is_array($linked->metadata) ? $linked->metadata : [];
            if ($this->isAiDisabledFlag($meta['ai_reply_disabled'] ?? null)) {
                return true;
            }
        }

        foreach ($this->findLegacyConversationsByPhone($phone) as $linkedLegacy) {
            if ($legacy && (int) $linkedLegacy->id === (int) $legacy->id) {
                continue;
            }
            $meta = is_array($linkedLegacy->metadata) ? $linkedLegacy->metadata : [];
            if ($this->isAiDisabledFlag($meta['ai_reply_disabled'] ?? null)) {
                return true;
            }
        }

        return false;
    }

    public function setChatAiEnabled(Conversation $legacy, bool $enabled): void
    {
        $this->setChatAiEnabledForPhone((string) $legacy->phone_number, $enabled, $legacy);
    }

    /**
     * Sync Auto AI toggle across ALL legacy + inbox rows for this phone.
     */
    public function setChatAiEnabledForPhone(
        string $phone,
        bool $enabled,
        ?Conversation $legacy = null,
        ?WhatsappConversation $primaryWhatsapp = null
    ): void {
        if ($legacy) {
            $meta = is_array($legacy->metadata) ? $legacy->metadata : [];
            if ($enabled) {
                unset($meta['ai_reply_disabled']);
            } else {
                $meta['ai_reply_disabled'] = true;
            }
            $legacy->update(['metadata' => $meta]);
        }

        foreach ($this->findLegacyConversationsByPhone($phone) as $row) {
            $meta = is_array($row->metadata) ? $row->metadata : [];
            if ($enabled) {
                unset($meta['ai_reply_disabled']);
            } else {
                $meta['ai_reply_disabled'] = true;
            }
            $row->update(['metadata' => $meta]);
        }

        $targets = $this->findWhatsappConversationsByPhone($phone, $legacy);
        if ($primaryWhatsapp && ! $targets->contains('id', $primaryWhatsapp->id)) {
            $targets->push($primaryWhatsapp);
        }

        foreach ($targets as $whatsapp) {
            $waMeta = is_array($whatsapp->metadata) ? $whatsapp->metadata : [];
            if ($enabled) {
                unset($waMeta['ai_reply_disabled']);
            } else {
                $waMeta['ai_reply_disabled'] = true;
            }
            if ($legacy && ! isset($waMeta['legacy_conversation_id'])) {
                $waMeta['legacy_conversation_id'] = $legacy->id;
            }
            $whatsapp->update(['metadata' => $waMeta]);
        }

        // Bridge ensure + force flag on the row webhook will use
        if ($legacy) {
            $bridged = $this->inboxService->ensureWhatsappConversationForLegacy($legacy);
            $bridgedMeta = is_array($bridged->metadata) ? $bridged->metadata : [];
            if ($enabled) {
                unset($bridgedMeta['ai_reply_disabled']);
            } else {
                $bridgedMeta['ai_reply_disabled'] = true;
            }
            $bridgedMeta['legacy_conversation_id'] = $legacy->id;
            $bridged->update(['metadata' => $bridgedMeta]);
        }
    }

    /**
     * Treat true / 1 / "1" / "true" as disabled (JSON quirks).
     */
    protected function isAiDisabledFlag(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if (is_string($value) && strtolower(trim($value)) === 'true') {
            return true;
        }

        return false;
    }

    /**
     * Phone variants: raw, normalized 91..., and last 10 digits.
     *
     * @return array<int, string>
     */
    public function phoneVariants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        $normalized = $digits !== '' ? $this->inboxService->normalizedPhone($digits) : '';
        $last10 = strlen($digits) >= 10 ? substr($digits, -10) : $digits;

        return array_values(array_unique(array_filter([
            trim($phone),
            $digits,
            $normalized,
            $last10,
            $last10 !== '' ? '91'.$last10 : '',
            $last10 !== '' ? '+91'.$last10 : '',
        ])));
    }

    /**
     * @return \Illuminate\Support\Collection<int, WhatsappConversation>
     */
    protected function findWhatsappConversationsByPhone(string $phone, ?Conversation $legacy = null)
    {
        $variants = $this->phoneVariants($phone);
        $last10 = strlen(preg_replace('/\D+/', '', $phone) ?: '') >= 10
            ? substr(preg_replace('/\D+/', '', $phone), -10)
            : null;

        return WhatsappConversation::query()
            ->where(function ($query) use ($variants, $last10, $legacy) {
                if ($legacy) {
                    $query->where('metadata->legacy_conversation_id', $legacy->id);
                } else {
                    $query->whereRaw('0 = 1');
                }
                if ($variants !== []) {
                    $query->orWhereIn('phone_number', $variants);
                }
                if ($last10) {
                    $query->orWhere('phone_number', 'like', '%'.$last10);
                }
            })
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Conversation>
     */
    protected function findLegacyConversationsByPhone(string $phone)
    {
        $variants = $this->phoneVariants($phone);
        $last10 = strlen(preg_replace('/\D+/', '', $phone) ?: '') >= 10
            ? substr(preg_replace('/\D+/', '', $phone), -10)
            : null;

        return Conversation::query()
            ->where(function ($query) use ($variants, $last10) {
                if ($variants !== []) {
                    $query->whereIn('phone_number', $variants);
                }
                if ($last10) {
                    $query->orWhere('phone_number', 'like', '%'.$last10);
                }
            })
            ->get();
    }

    /**
     * @deprecated use findWhatsappConversationsByPhone
     * @return \Illuminate\Support\Collection<int, WhatsappConversation>
     */
    protected function linkedWhatsappConversations(Conversation $legacy)
    {
        return $this->findWhatsappConversationsByPhone((string) $legacy->phone_number, $legacy);
    }

    protected function shouldReplyToType(string $type): bool
    {
        return in_array($type, ['text', 'button', 'interactive', 'order'], true);
    }

    protected function resolveSettings(?int $userId): ?Settings
    {
        if (! $userId) {
            Log::warning('Auto AI skipped: conversation has no user_id');

            return null;
        }

        $settings = Settings::where('userId', $userId)->where('isActive', true)->orderByDesc('id')->first()
            ?: Settings::where('userId', $userId)->orderByDesc('id')->first();

        if (! $settings) {
            Log::warning('Auto AI skipped: no settings for conversation owner', [
                'user_id' => $userId,
            ]);
        }

        // Never fall back to another company's settings
        return $settings;
    }

    protected function alreadyRepliedToInbound(WhatsappConversation $conversation, ?string $inboundMetaMessageId): bool
    {
        if (! $inboundMetaMessageId) {
            return false;
        }

        return WhatsappMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'outgoing')
            ->where('metadata->auto_ai_for_meta_message_id', $inboundMetaMessageId)
            ->exists();
    }

    protected function lastAutoAiReply(WhatsappConversation $conversation): ?string
    {
        $message = WhatsappMessage::query()
            ->where('conversation_id', $conversation->id)
            ->where('direction', 'outgoing')
            ->where('metadata->auto_ai', true)
            ->orderByDesc('id')
            ->first();

        return $message ? trim((string) $message->message) : null;
    }

    protected function buildHistory(WhatsappConversation $conversation): array
    {
        return WhatsappMessage::query()
            ->where('conversation_id', $conversation->id)
            ->whereNotIn('message_type', ['reaction', 'revoked'])
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->reverse()
            ->map(function (WhatsappMessage $message) {
                return [
                    'role' => $message->direction === 'incoming' ? 'user' : 'assistant',
                    'content' => mb_substr((string) $message->message, 0, 400),
                ];
            })
            ->filter(fn ($row) => filled(trim((string) ($row['content'] ?? ''))))
            ->values()
            ->all();
    }

    protected function generateReply(
        Settings $settings,
        string $customerMessage,
        array $history,
        ?string $lastAiReply = null
    ): string {
        $aiResult = $this->callFreeAi($settings, $history, $customerMessage, $lastAiReply);
        $aiReply = is_array($aiResult) ? (string) ($aiResult['text'] ?? '') : '';
        if (filled($aiReply) && ! $this->isObviouslyBrokenAi($aiReply)) {
            return $this->sanitizeReply($aiReply);
        }

        return $this->generateKnowledgeFallback($settings, $customerMessage, $history, $lastAiReply);
    }

    protected function detectLanguageStyle(string $message): string
    {
        $hasDevanagari = (bool) preg_match('/[\x{0900}-\x{097F}]/u', $message);
        $lower = mb_strtolower(trim($message));

        if ($hasDevanagari && preg_match('/[a-zA-Z]{3,}/', $message)) {
            return 'hinglish';
        }
        if ($hasDevanagari) {
            return 'hindi';
        }

        // Clear Hinglish only (avoid English false positives like ok / budget / related)
        $strongHints = [
            'hai', 'hain', 'kya', 'kyu', 'kyun', 'kaise', 'kitna', 'chahiye', 'btao', 'batao',
            'mujhe', 'mera', 'meri', 'apka', 'aapki', 'aap', 'nahi', 'nhi', 'haan', 'han',
            'theek', 'thik', 'karna', 'karne', 'ke liye', 'wala', 'wali', 'bhej', 'bolo',
            'samajh', 'bilkul', 'zaroor', 'accha', 'achha', 'madad', 'swagat', 'dhanyavaad',
            'bataye', 'karein', 'karun', 'sakta', 'sakte', 'chahiye', 'dena', 'lena',
            'scratch se', 'banwa', 'banana', 'banani', 'chahiye',
        ];
        $score = 0;
        foreach ($strongHints as $hint) {
            if (preg_match('/\b'.preg_quote($hint, '/').'\b/u', $lower)) {
                $score++;
            }
        }

        // Phrases that are clearly Hinglish even alone
        if (preg_match('/\b(ke liye|scratch se|kaise madad|aapki|mujhe|chahiye)\b/u', $lower)) {
            return 'hinglish';
        }

        if ($score >= 2) {
            return 'hinglish';
        }
        if ($score >= 1 && preg_match('/[a-z]/i', $message) && ! $this->looksLikePureEnglish($lower)) {
            return 'hinglish';
        }

        return 'english';
    }

    protected function looksLikePureEnglish(string $lower): bool
    {
        return (bool) preg_match(
            '/^(hi|hello|hey|hii|helo|good morning|good evening|good afternoon|yes|no|ok|okay|please|thanks|thank you)([\s!,.]*|$)/u',
            $lower
        ) || (
            preg_match('/\b(the|and|for|with|need|want|create|website|budget|from|scratch)\b/u', $lower)
            && ! preg_match('/\b(hai|kya|chahiye|mujhe|aap|kaise|nahi|haan|ke liye)\b/u', $lower)
        );
    }

    /**
     * Match customer language: Hinglish only when user uses Hinglish.
     * English greetings / English messages stay English.
     */
    protected function resolveReplyStyle(array $history, string $latest): string
    {
        $lower = mb_strtolower(trim($latest));

        // Greeting language follows the greeting itself (hey/hi → English)
        if ($this->isPureGreeting($lower)) {
            if (preg_match('/namaste|namaskar/u', $lower) || preg_match('/[\x{0900}-\x{097F}]/u', $latest)) {
                return preg_match('/[\x{0900}-\x{097F}]/u', $latest) && ! preg_match('/[a-zA-Z]{3,}/', $latest)
                    ? 'hindi'
                    : 'hinglish';
            }

            return 'english';
        }

        $latestStyle = $this->detectLanguageStyle($latest);
        if ($latestStyle !== 'english') {
            return $latestStyle;
        }

        // Short ambiguous follow-ups (10k, ok, yes): inherit last clear user language
        if ($this->isAmbiguousFollowUp($latest)) {
            foreach (array_reverse($history) as $row) {
                if (($row['role'] ?? '') !== 'user') {
                    continue;
                }
                $prev = trim((string) ($row['content'] ?? ''));
                if ($prev === '' || mb_strlen($prev) < 2) {
                    continue;
                }
                $prevLower = mb_strtolower($prev);
                if ($this->isPureGreeting($prevLower) || $this->isAmbiguousFollowUp($prev)) {
                    continue;
                }

                return $this->detectLanguageStyle($prev);
            }

            // Default English — do not force Hinglish
            return 'english';
        }

        return 'english';
    }

    protected function isAmbiguousFollowUp(string $message): bool
    {
        $t = trim($message);
        if ($t === '') {
            return false;
        }

        $lower = mb_strtolower($t);

        // Pure numbers / budget tokens
        if (preg_match('/^\d+\s*[kj]?\s*$/iu', $lower)) {
            return true;
        }

        return (bool) preg_match(
            '/^(ok|okay|yes|no|haan|han|ji|scratch(\s+se)?|from\s+scratch|nahi|nhi|theek|thik|accha|achha)[\s!.]*$/iu',
            $lower
        );
    }

    protected function isShortFollowUp(string $message): bool
    {
        $t = trim($message);
        if ($t === '') {
            return false;
        }
        if ($this->isPureGreeting(mb_strtolower($t))) {
            return false;
        }
        if (mb_strlen($t) <= 24) {
            return true;
        }

        return $this->isAmbiguousFollowUp($t);
    }

    protected function matchesCustomerStyle(string $reply, string $style): bool
    {
        $lower = mb_strtolower($reply);

        $slang = [
            'haan bolo',
            'heyy',
            'batao?',
            'batao kya',
            'haan batao',
            'ok batao',
            'perfect —',
            'perfect -',
            'what exactly do you need',
            'specializing in',
            'share 1)',
            'exact requirement',
            'next steps',
            'all details noted',
            'please share your approximate',
            'please share your preferred',
            'how may i help you today',
            'aapka swagat hai',
        ];
        foreach ($slang as $phrase) {
            if (str_contains($lower, $phrase)) {
                return false;
            }
        }

        // User wrote English → don't accept Hinglish replies
        if ($style === 'english') {
            $hinglishHits = 0;
            foreach (['mein aapka', 'kaise madad', 'share karein', 'samajh gaya', 'dhanyavaad', 'aapki', 'hum ', 'zaroor —', 'please bataye'] as $m) {
                if (str_contains($lower, $m)) {
                    $hinglishHits++;
                }
            }
            if ($hinglishHits >= 2) {
                return false;
            }
        }

        if (in_array($style, ['hinglish', 'hindi'], true)) {
            if (mb_strlen($reply) > 200 && ! preg_match('/\b(hai|haan|aap|madad|please|swagat|dhanyavaad|samajh)\b/u', $lower)
                && ! preg_match('/[\x{0900}-\x{097F}]/u', $reply)) {
                return false;
            }
        }

        return true;
    }

    protected function conversationLanguage(array $history, string $latest): string
    {
        return $this->resolveReplyStyle($history, $latest);
    }

    protected function buildSystemPrompt(
        Settings $settings,
        string $customerMessage,
        array $history,
        ?string $lastAiReply = null,
        ?WhatsappConversation $whatsapp = null
    ): string {
        $name = trim((string) ($settings->business_name ?: 'our business'));
        $category = $this->resolveBusinessCategory($settings);
        $family = $this->categoryFamily($category);
        $purpose = $this->categoryPurpose($category);
        $details = trim((string) ($settings->business_details ?: ''));
        $hours = trim((string) ($settings->business_hours ?: ''));
        $tone = strtolower(trim((string) ($settings->auto_ai_tone ?: 'friendly')));
        $shortName = $this->shortBizName($name);
        $style = $this->conversationLanguage($history, $customerMessage);

        $lead = $whatsapp
            ? $this->resolveLeadState($whatsapp, $history, $customerMessage)
            : $this->extractLeadState(
                mb_strtolower($this->sessionUserText($history, $customerMessage)),
                mb_strtolower($customerMessage),
                $customerMessage
            );

        $nextStep = $this->nextConversationStep($family, $lead, $customerMessage, $history);

        $prompt = "You are a professional Business & Customer Support Specialist representing {$shortName}.\n";
        $prompt .= "Your job is to provide clear, helpful, expert, and courteous support to clients and prospective customers on WhatsApp.\n";
        $industry = trim((string) ($settings->industry ?: ''));
        if ($industry !== '') {
            $prompt .= "Industry: {$industry}\n";
        }
        $prompt .= "Business type: {$category}. What you do: {$purpose}\n";
        $prompt .= "Tone: {$tone}, professional, polite, attentive, and confident — like an experienced support specialist or relationship manager.\n";
        $prompt .= "Reply language: {$style} only (always match the customer's language: English, Hindi, or Hinglish).\n";

        // Working days & hours
        $workingDays = is_array($settings->working_days)
            ? implode(', ', $settings->working_days)
            : (is_string($settings->working_days) ? $settings->working_days : '');
        if ($workingDays !== '') {
            $prompt .= "Working days: {$workingDays}\n";
        }
        if ($hours !== '') {
            $prompt .= "Working hours: {$hours}\n";
        }

        // Services & Products
        $services = trim((string) ($settings->services ?: ''));
        $products = trim((string) ($settings->products ?: ''));
        if ($services !== '') {
            $prompt .= "Services Offered: {$services}\n";
        }
        if ($products !== '') {
            $prompt .= "Core Products / Plans: {$products}\n";
        }

        if ($details !== '') {
            $prompt .= "Business knowledge (use only what fits the customer's question; never dump the whole text):\n";
            $prompt .= Str::limit($details, 900)."\n";
        }

        // Location, Coverage & Address
        $location = trim((string) ($settings->location ?: ''));
        if ($location !== '') {
            $prompt .= "Service Coverage / Location: {$location}\n";
        }

        // Location, Store Address & Google Maps link
        $address = trim((string) ($settings->business_address ?: ''));
        if ($address !== '') {
            $encodedAddress = urlencode(($shortName ? $shortName . ', ' : '') . $address);
            $mapUrl = "https://www.google.com/maps/search/?api=1&query={$encodedAddress}";
            $prompt .= "Store Location & Address: {$address}\n";
            $prompt .= "Google Maps Location Link: {$mapUrl}\n";
            $prompt .= "Location Rule: When asked for map location, address, or directions, ALWAYS provide the store address along with the Google Maps link ({$mapUrl}) so the customer can easily locate and navigate to the store.\n";
        }

        // Website
        $website = trim((string) ($settings->website_url ?: ''));
        if ($website !== '') {
            $prompt .= "Website: {$website}\n";
        }

        // Contact & Support
        $supportPhone = trim((string) ($settings->support_phone ?: ''));
        $supportEmail = trim((string) ($settings->support_email ?: ''));
        if ($supportPhone !== '') {
            $prompt .= "Customer Care / Support Phone: {$supportPhone}\n";
        }
        if ($supportEmail !== '') {
            $prompt .= "Support Email: {$supportEmail}\n";
        }

        // Vernacular / Indian retail intelligence
        $prompt .= "Vernacular & Regional Terms: Understand Indian apparel & retail terms naturally (e.g. Mojadi / Mojari / Jutti = traditional handcrafted leather footwear/shoes; Safa / Pagdi = traditional turban; Hunting Kurta = short structured shirt-style kurta; Sanganeri = Jaipuri block print; Indo-Western / Sherwani = formal ethnic wear). Never ask the customer to define or explain common items like Mojadi/Mojari or Kurta — understand immediately and reply enthusiastically!\n";

        // FAQs & Knowledge Base Items
        $faqs = $settings->getParsedFaqs();
        try {
            $kbItems = \App\Models\KnowledgeBase::where('user_id', $settings->userId)
                ->where('is_published', true)
                ->where('is_enabled', true)
                ->limit(25)
                ->get();
            foreach ($kbItems as $kb) {
                $faqs[] = [
                    'question' => $kb->question,
                    'answer' => $kb->answer,
                ];
            }
        } catch (\Throwable $e) {
            // Graceful fallback if table not yet queried
        }

        if (!empty($faqs)) {
            $prompt .= "Verified Business FAQs & Knowledge Base (Use to answer customer questions accurately):\n";
            $seenQuestions = [];
            foreach (array_slice($faqs, 0, 25) as $f) {
                $q = trim((string) ($f['question'] ?? ''));
                $a = trim((string) ($f['answer'] ?? ''));
                $qLower = mb_strtolower($q);
                if ($q !== '' && $a !== '' && !isset($seenQuestions[$qLower])) {
                    $seenQuestions[$qLower] = true;
                    $prompt .= "Q: {$q}\nA: {$a}\n";
                }
            }
        }

        // Only include retail catalog product list if this business has catalog enabled and is a retail store/shop
        $hasCatalog = $this->hasCatalogPermission((int) ($settings->userId ?? 0));
        if ($hasCatalog && !in_array($family, ['it', 'realty', 'finance', 'clinic'], true)) {
            $catalogSummary = $this->resolveCatalogKnowledgeSummary($settings, $customerMessage);
            if ($catalogSummary !== '') {
                $prompt .= "Available Catalog Products:\n" . $catalogSummary . "\n";
            }
            $prompt .= "Catalog Rule: When discussing catalog products, describe the item warmly and accurately based on Available Catalog Products. Do not instruct the user to tap icons or search elsewhere unless they ask how to order.\n";
            $prompt .= "Multilingual Catalog Matching: Catalog product titles may be written in Hindi (Devanagari script), e.g. 'लेदर लोफर्स' means 'Leather Loafers' or 'Ledar loafers', 'सफ़ारी' means 'Safari', 'कुर्ता' means 'Kurta', 'जूते' means 'Shoes'. When a customer inquires in English or Hinglish (such as 'ledar loafers', 'leather shoes', 'kurta'), match it accurately with the corresponding Hindi/Devanagari product from the catalog and confirm availability enthusiastically.\n";
        }

        $prompt .= "Conversation goal now: {$nextStep}\n";

        if ($family === 'it') {
            $prompt .= "Known lead fields (do NOT re-ask if filled):\n";
            $prompt .= '- Budget: '.(($lead['budget'] ?? null) ?: 'missing')."\n";
            $prompt .= '- Project/name: '.(($lead['projectName'] ?? null) ?: 'missing')."\n";
            $prompt .= '- Pages: '.(($lead['pagesSummary'] ?? null) ?: (($lead['hasPages'] ?? false) ? 'mentioned' : 'missing'))."\n";
            $prompt .= '- Phone: '.(($lead['phone'] ?? null) ?: 'missing')."\n";
            $prompt .= '- Timeline: '.(($lead['timeline'] ?? null) ?: 'missing')."\n";
        } else {
            $prompt .= "Already known from chat (do NOT re-ask):\n";
            $prompt .= '- Need/topic: '.(($lead['topic'] ?? null) ?: 'not clear yet')."\n";
            $prompt .= '- Name: '.(($lead['projectName'] ?? null) ?: 'missing')."\n";
            $prompt .= '- Phone: '.(($lead['phone'] ?? null) ?: 'missing')."\n";
            if ($lead['budget'] ?? null) {
                $prompt .= '- Amount/budget mentioned: '.$lead['budget']."\n";
            }
        }

        $prompt .= "Professional & Friendly Support Workflow:\n";
        $prompt .= "1. Understand Customer Need: First, analyze the conversation history and the latest incoming message to understand exactly what the user is asking or looking for.\n";
        $prompt .= "2. Reference Business Knowledge: Check what this specific business provides in Settings (services, products, business details), verified FAQs, and catalog items. Formulate your answer based on what this business actually offers.\n";
        $prompt .= "3. Deliver Professional & Friendly Reply: Reply in a polite, helpful, and friendly tone. Keep the message balanced and concise (never too high or long — strictly 2 to 4 clean sentences suitable for mobile WhatsApp screen). Avoid overwhelming paragraphs or walls of text.\n";
        $prompt .= "- For custom services & development (e.g. app development, eCommerce website, custom software, digital marketing): acknowledge their requirement professionally, highlight relevant capabilities from our services, and ask 1 focused question to clarify their vision (e.g. target platform, key features, or design preferences).\n";
        $prompt .= "- For real estate / house brokers / property dealers: assist with property requirements (buying/renting/selling, 1/2/3 BHK, budget, preferred area) and offer property visits or options.\n";
        $prompt .= "- NEVER force or pitch catalog products or product cards when a customer is asking about custom services, real estate, or general support.\n";
        $prompt .= "- WhatsApp formatting: Friendly, clean spacing, mobile-friendly. Courteous emojis (👋, 🙏, 💼, 🏠) are welcome.\n";
        $prompt .= "- Output ONLY the WhatsApp chat message to be sent to the customer.\n";
        $prompt .= "- CRITICAL RULE: NEVER output Markdown image syntax like ![alt](url) or fake image URLs (like imgur.com, unsplash.com, placeholder links). WhatsApp text chats cannot render markdown images.\n";

        $prompt .= $this->promptExamplesForFamily($family);

        if ($lastAiReply) {
            $prompt .= 'Do not repeat your last message: '.Str::limit($lastAiReply, 120)."\n";
        }

        return $prompt;
    }

    /**
     * Human next step based on business category — not always "ask budget".
     */
    protected function nextConversationStep(string $family, array $lead, string $customerMessage, array $history): string
    {
        $lower = mb_strtolower(trim($customerMessage));
        $turnCount = count(array_filter($history, fn ($r) => ($r['role'] ?? '') === 'user'));

        if ($this->isPureGreeting($lower) || $turnCount <= 1) {
            return match ($family) {
                'finance' => 'Greet warmly as this finance firm, briefly say you help with investments/insurance/MF, ask how you can help today',
                'clinic', 'salon', 'food' => 'Greet warmly, ask how you can help (booking / enquiry)',
                'retail' => 'Greet warmly, ask what they need from the shop',
                'education' => 'Greet warmly, ask which course or enquiry they have',
                'realty' => 'Greet warmly, ask what kind of property they are looking for',
                'it' => 'Greet warmly, ask what they need help with (website/app/support) — do NOT ask budget yet',
                default => 'Greet warmly using this business, ask how you can help — do NOT open with budget',
            };
        }

        if ($this->containsAny($lower, ['price', 'rate', 'cost', 'fees', 'charge', 'kitna', 'budget', 'premium', 'return', 'roi'])) {
            return 'Answer about pricing/cost carefully using business knowledge if possible; if unknown, say a team member will share exact details, then ask one clarifying need';
        }

        if ($family === 'it') {
            if (! ($lead['budget'] ?? null) && ($lead['wantsDev'] || $lead['topic'] || $lead['hasPages'] || $lead['projectName'])) {
                return 'Acknowledge their need, then casually ask rough budget only if relevant';
            }
            if (($lead['budget'] ?? null) && ! ($lead['projectName'] ?? null) && ! ($lead['hasPages'] ?? false)) {
                return 'Ask project/business name and which pages they need';
            }
            if (($lead['budget'] ?? null) && (($lead['projectName'] ?? null) || ($lead['hasPages'] ?? false))) {
                if (! ($lead['timeline'] ?? null) && ! ($lead['phone'] ?? null)) {
                    return 'Ask timeline and WhatsApp number casually';
                }
                if (! ($lead['timeline'] ?? null)) {
                    return 'Ask timeline only';
                }
                if (! ($lead['phone'] ?? null)) {
                    return 'Ask WhatsApp number only';
                }

                return 'Confirm you noted everything and say someone from the team will connect soon';
            }

            return 'Understand their requirement in plain human talk; do not force budget if they only asked a simple question';
        }

        if ($family === 'finance') {
            if (! ($lead['topic'] ?? null)) {
                return 'Ask what they want help with: mutual funds, equity, life/health/general insurance, or credit cards — one friendly question';
            }
            if (! ($lead['phone'] ?? null) && $turnCount >= 3) {
                return 'Offer helpful next step and casually ask best number/time to connect if needed';
            }

            return 'Answer helpfully about their finance need using business knowledge; ask one soft clarifying question (goal, existing cover, SIP amount) — never ask website pages';
        }

        return 'Answer helpfully using business knowledge, then ask one short clarifying question if needed';
    }

    protected function promptExamplesForFamily(string $family): string
    {
        return match ($family) {
            'finance' => "Examples:\n"
                ."Customer: hi\n"
                ."You: Hello! 👋 Welcome to our support. How can I assist you with mutual funds, insurance, or investments today?\n"
                ."Customer: want to know about mutual funds\n"
                ."You: Certainly! We offer personalized investment advisory. What is your primary goal—wealth creation, tax saving, or child education?\n",
            'it' => "Examples:\n"
                ."Customer: Want to develop new App and website of ecommerce\n"
                ."You: Hello! 👋 We would be glad to help you develop your eCommerce website and mobile app. Could you share a few details about the key features you need (such as payment gateway, product catalog, tracking) and your preferred timeline?\n"
                ."Customer: Need a website for my business\n"
                ."You: Great! We specialize in custom website development and UI/UX design. What type of business is this for, and do you already have design preferences in mind?\n",
            'realty' => "Examples:\n"
                ."Customer: Looking for a 2BHK flat in Adajan\n"
                ."You: Hello! 👋 We have prime 2BHK residential properties available in Adajan. Are you looking to buy or rent, and what is your approximate budget?\n"
                ."Customer: want to sell or rent my property\n"
                ."You: Certainly! Please share the property details (type, location, configuration) and we will connect you with verified prospects and assist you end-to-end.\n",
            'clinic' => "Examples:\n"
                ."Customer: hi\n"
                ."You: Hello! 👋 Welcome to our clinic. Would you like to schedule an appointment or do you have a consultation query?\n",
            default => "Examples:\n"
                ."Customer: hi\n"
                ."You: Hello! 👋 Welcome to our support. How may I assist you today?\n"
                ."Customer: What services do you offer?\n"
                ."You: We provide comprehensive business solutions. What specific service are you interested in?\n",
        };
    }

    /**
     * Category shown after company name in settings — drives AI purpose.
     */
    protected function resolveBusinessCategory(Settings $settings): string
    {
        $type = trim((string) ($settings->business_type ?: ''));
        if ($type !== '') {
            return $type;
        }

        // Fallback: guess from business name / details keywords
        $blob = mb_strtolower(
            trim((string) ($settings->business_name ?: '')).' '.
            trim((string) ($settings->business_details ?: ''))
        );

        foreach ([
            'finserve' => 'Financial Services',
            'fintech' => 'Financial Services',
            'mutual fund' => 'Financial Services',
            'insurance' => 'Insurance',
            'investment' => 'Investments',
            'finance' => 'Financial Services',
            'software' => 'Software / IT',
            'web develop' => 'Web Development',
            'mobile app' => 'App Development',
            'it company' => 'IT Services',
            'grocery' => 'Grocery',
            'clinic' => 'Clinic',
            'salon' => 'Salon',
            'restaurant' => 'Restaurant',
            'hotel' => 'Hotel',
            'education' => 'Education',
            'real estate' => 'Real Estate',
            'broker' => 'Real Estate / Property Broker',
            'property' => 'Real Estate / Property Broker',
        ] as $needle => $label) {
            if (str_contains($blob, $needle)) {
                return $label;
            }
        }

        return 'Business';
    }

    protected function categoryFamily(string $category): string
    {
        $c = mb_strtolower($category);

        if ($this->containsAny($c, [
            'finserve', 'fiinserve', 'finance', 'financial', 'mutual', 'insurance',
            'invest', 'equity', 'sip', 'credit card', 'wealth', 'loan', 'nbfc',
        ])) {
            return 'finance';
        }
        if ($this->containsAny($c, [
            'retail', 'clothing', 'apparel', 'footwear', 'garment', 'fashion',
            'grocery', 'kirana', 'supermarket', 'store', 'shop', 'ecommerce'
        ])) {
            return 'retail';
        }
        if (preg_match('/\b(it|software|web|develop|developer|development|tech|technology|app|saas|programming|coding|agency)\b/iu', $c)) {
            return 'it';
        }
        if ($this->containsAny($c, ['clinic', 'hospital', 'doctor', 'dental', 'medical', 'health'])) {
            return 'clinic';
        }
        if ($this->containsAny($c, ['restaurant', 'cafe', 'hotel', 'food', 'cloud kitchen'])) {
            return 'food';
        }
        if ($this->containsAny($c, ['salon', 'beauty', 'spa', 'parlour', 'parlor'])) {
            return 'salon';
        }
        if ($this->containsAny($c, ['education', 'coaching', 'school', 'tuition', 'institute'])) {
            return 'education';
        }
        if ($this->containsAny($c, ['real estate', 'property', 'broker', 'builder', 'house broker', 'realtor', 'housing', 'plots'])) {
            return 'realty';
        }

        return 'general';
    }

    protected function categoryPurpose(string $category): string
    {
        return match ($this->categoryFamily($category)) {
            'finance' => "Help with {$category}: mutual funds, equity investments, life/health/general insurance, credit cards, SIPs, financial planning. Build trust, clarify goals, never sell websites.",
            'it' => "Help with {$category}: websites, apps, software, UI/UX, e-commerce, custom development, support. Understand client needs professionally and guide them.",
            'retail' => "Help with {$category}: products, stock, prices, orders, delivery. Stay as a helpful shop assistant.",
            'clinic' => "Help with {$category}: appointments, timings, services, consultations. Stay as clinic staff.",
            'food' => "Help with {$category}: menu, orders, booking, timings. Stay as restaurant/hotel staff.",
            'salon' => "Help with {$category}: services, booking, prices, timings. Stay as salon staff.",
            'education' => "Help with {$category}: courses, fees, admission, batch timings.",
            'realty' => "Help with {$category}: buying, selling, or renting properties, flats, houses, villas, plots, and commercial spaces. Act as an expert property consultant and house broker.",
            default => "Help customers only with {$category} related needs using the saved business details. Stay in this category.",
        };
    }

    protected function isItCategory(Settings $settings): bool
    {
        return $this->categoryFamily($this->resolveBusinessCategory($settings)) === 'it';
    }

    /**
     * Prefer Gemini / OpenAI for realistic replies; fall back to free no-key providers.
     *
     * @return array{text:?string,provider:string}|null
     */
    protected function callFreeAi(
        Settings $settings,
        array $history,
        string $customerMessage,
        ?string $lastAiReply = null,
        ?WhatsappConversation $whatsapp = null
    ): ?array {
        $system = $this->buildSystemPrompt($settings, $customerMessage, $history, $lastAiReply, $whatsapp);
        $messages = [['role' => 'system', 'content' => $system]];

        foreach (array_slice($history, -8) as $row) {
            if (! in_array($row['role'] ?? '', ['user', 'assistant'], true)) {
                continue;
            }
            $messages[] = [
                'role' => $row['role'],
                'content' => (string) $row['content'],
            ];
        }
        $messages[] = ['role' => 'user', 'content' => $customerMessage];

        // 1) Groq first (fast + free tier) — needs GROQ_API_KEY in .env
        //    https://console.groq.com/keys
        $groqKey = $this->resolveProviderApiKey('groq', 'GROQ_API_KEY');
        if ($groqKey !== '') {
            $groqModels = array_values(array_filter(array_unique([
                $this->resolveProviderModel('groq', 'GROQ_MODEL', 'openai/gpt-oss-20b'),
                'openai/gpt-oss-20b',
                'qwen/qwen3.6-27b',
                'openai/gpt-oss-120b',
            ])));

            foreach ($groqModels as $groqModel) {
                $groq = $this->callOpenAiCompatible(
                    'https://api.groq.com/openai/v1/chat/completions',
                    $groqKey,
                    $groqModel,
                    $messages,
                    12
                );
                if (filled($groq) && ! $this->isObviouslyBrokenAi($groq)) {
                    Log::info('Auto AI provider used', ['provider' => 'groq', 'model' => $groqModel]);

                    return ['text' => $groq, 'provider' => 'groq'];
                }
                Log::warning('Groq model failed/empty', ['model' => $groqModel]);
            }
        } else {
            Log::warning('Auto AI: GROQ_API_KEY missing — get free key at https://console.groq.com/keys');
        }

        // 2) Claude / Anthropic
        $claudeKey = $this->resolveProviderApiKey('claude', 'CLAUDE_API_KEY');
        if ($claudeKey === '') {
            $claudeKey = $this->resolveProviderApiKey('claude', 'ANTHROPIC_API_KEY');
        }
        if ($claudeKey !== '') {
            $claudeModel = $this->resolveProviderModel('claude', 'CLAUDE_MODEL', 'claude-3-5-haiku-20241022');
            $claude = $this->callClaude($claudeKey, $claudeModel, $system, $messages, 12);
            if (filled($claude) && ! $this->isObviouslyBrokenAi($claude)) {
                Log::info('Auto AI provider used', ['provider' => 'claude', 'model' => $claudeModel]);

                return ['text' => $claude, 'provider' => 'claude'];
            }
        }

        // 3) Gemini (optional fallback)
        $geminiKey = $this->resolveGeminiApiKey();
        if ($geminiKey !== '') {
            $gemini = $this->callGemini($geminiKey, $system, $history, $customerMessage);
            if (filled($gemini) && ! $this->isObviouslyBrokenAi($gemini)) {
                Log::info('Auto AI provider used', ['provider' => 'gemini']);

                return ['text' => $gemini, 'provider' => 'gemini'];
            }
            Log::warning('Gemini returned empty/bad reply', ['preview' => Str::limit((string) $gemini, 80)]);
        }

        // 4) OpenAI (optional)
        $openAiKey = $this->resolveProviderApiKey('openai', 'OPENAI_API_KEY');
        if ($openAiKey !== '') {
            $openAi = $this->callOpenAiCompatible(
                'https://api.openai.com/v1/chat/completions',
                $openAiKey,
                $this->resolveProviderModel('openai', 'OPENAI_MODEL', 'gpt-4o-mini'),
                $messages,
                12
            );
            if (filled($openAi) && ! $this->isObviouslyBrokenAi($openAi)) {
                Log::info('Auto AI provider used', ['provider' => 'openai']);

                return ['text' => $openAi, 'provider' => 'openai'];
            }
        }

        // 5) OpenRouter free models
        $openRouterKey = $this->resolveProviderApiKey('openrouter', 'OPENROUTER_API_KEY');
        if ($openRouterKey !== '') {
            $orModels = array_filter([
                $this->resolveProviderModel('openrouter', 'OPENROUTER_MODEL', 'google/gemma-2-9b-it:free'),
                'google/gemma-2-9b-it:free',
            ]);
            foreach (array_unique($orModels) as $orModel) {
                $or = $this->callOpenAiCompatible(
                    'https://openrouter.ai/api/v1/chat/completions',
                    $openRouterKey,
                    $orModel,
                    $messages,
                    12
                );
                if (filled($or) && ! $this->isObviouslyBrokenAi($or)) {
                    Log::info('Auto AI provider used', ['provider' => 'openrouter', 'model' => $orModel]);

                    return ['text' => $or, 'provider' => 'openrouter'];
                }
            }
        }

        // 6) No-key free Pollinations
        $raced = $this->racePollinationsFreeAi($messages, $system, $customerMessage, $history);
        if (filled($raced) && ! $this->isObviouslyBrokenAi($raced)) {
            Log::info('Auto AI provider used', ['provider' => 'pollinations']);

            return ['text' => $raced, 'provider' => 'pollinations'];
        }

        Log::warning('Auto AI: all live providers failed — static local_fallback will be used');

        return null;
    }

    /**
     * Parallel free Pollinations calls — keeps latency low while improving success rate.
     */
    protected function racePollinationsFreeAi(
        array $messages,
        string $system,
        string $customerMessage,
        array $history
    ): ?string {
        $payloadBase = [
            'temperature' => 0.7,
            'max_tokens' => 450,
            'messages' => $messages,
        ];

        $shortPrompt = Str::limit(
            $system."\n\nCustomer: {$customerMessage}\nReply in 1-2 short WhatsApp lines only. No lists.",
            900
        );

        try {
            $responses = Http::pool(function ($pool) use ($payloadBase, $shortPrompt) {
                return [
                    $pool->as('openai')
                        ->timeout(12)
                        ->connectTimeout(4)
                        ->acceptJson()
                        ->asJson()
                        ->withHeaders(['User-Agent' => 'WhatsAppBulkAutoAI/2.4'])
                        ->post('https://text.pollinations.ai/openai', array_merge($payloadBase, [
                            'model' => 'openai',
                        ])),
                    $pool->as('openai_fast')
                        ->timeout(12)
                        ->connectTimeout(4)
                        ->acceptJson()
                        ->asJson()
                        ->withHeaders(['User-Agent' => 'WhatsAppBulkAutoAI/2.4'])
                        ->post('https://text.pollinations.ai/openai', array_merge($payloadBase, [
                            'model' => 'openai-fast',
                        ])),
                    $pool->as('mistral')
                        ->timeout(12)
                        ->connectTimeout(4)
                        ->acceptJson()
                        ->asJson()
                        ->withHeaders(['User-Agent' => 'WhatsAppBulkAutoAI/2.4'])
                        ->post('https://text.pollinations.ai/openai', array_merge($payloadBase, [
                            'model' => 'mistral',
                        ])),
                    $pool->as('simple')
                        ->timeout(12)
                        ->connectTimeout(4)
                        ->withHeaders(['User-Agent' => 'WhatsAppBulkAutoAI/2.4'])
                        ->get('https://text.pollinations.ai/'.rawurlencode($shortPrompt), [
                            'model' => 'openai',
                        ]),
                ];
            });

            foreach (['openai', 'openai_fast', 'mistral', 'simple'] as $key) {
                $response = $responses[$key] ?? null;
                if (! $response || ! method_exists($response, 'successful') || ! $response->successful()) {
                    continue;
                }

                $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
                if ($content === '') {
                    $body = trim((string) $response->body());
                    if ($body !== '' && ! str_starts_with($body, '{') && ! str_starts_with($body, '[')) {
                        $content = $body;
                    }
                }

                if ($content !== '') {
                    return $content;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Pollinations race failed', ['error' => $e->getMessage()]);
        }

        return null;
    }

    protected function callGemini(string $apiKey, string $system, array $history, string $customerMessage): ?string
    {
        try {
            $contents = [];
            foreach (array_slice($history, -8) as $row) {
                $contents[] = [
                    'role' => ($row['role'] ?? '') === 'assistant' ? 'model' : 'user',
                    'parts' => [['text' => (string) $row['content']]],
                ];
            }
            $contents[] = [
                'role' => 'user',
                'parts' => [['text' => $customerMessage]],
            ];

            // Prefer configured model; keep one fast fallback. Avoid long multi-model loops (webhook timeout).
            $models = array_values(array_filter(array_unique([
                $this->resolveGeminiModel(),
                'gemini-2.5-flash',
            ])));

            $basePayload = [
                'system_instruction' => [
                    'parts' => [['text' => $system]],
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 500,
                ],
            ];

            foreach ($models as $model) {
                $response = Http::timeout(10)
                    ->connectTimeout(4)
                    ->post(
                        'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent?key='.urlencode($apiKey),
                        $basePayload
                    );

                if (! $response->successful()) {
                    Log::warning('Gemini model failed', [
                        'model' => $model,
                        'status' => $response->status(),
                        'error' => Str::limit((string) data_get($response->json(), 'error.message', $response->body()), 180),
                    ]);
                    continue;
                }

                $text = $this->extractGeminiText($response->json());
                if ($text !== '') {
                    Log::info('Gemini reply ok', ['model' => $model, 'chars' => mb_strlen($text)]);

                    return $text;
                }

                Log::warning('Gemini empty text', [
                    'model' => $model,
                    'finish' => data_get($response->json(), 'candidates.0.finishReason'),
                ]);
            }

            return null;
        } catch (\Throwable $e) {
            Log::warning('Gemini Auto AI failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Resolve Gemini key even when `php artisan config:cache` was built before the key existed.
     * (Laravel hides env() after config is cached.)
     */
    protected function resolveGeminiApiKey(): string
    {
        return $this->resolveProviderApiKey('gemini', 'GEMINI_API_KEY');
    }

    protected function resolveGeminiModel(): string
    {
        return $this->resolveProviderModel('gemini', 'GEMINI_MODEL', 'gemini-3.6-flash');
    }

    protected function resolveProviderApiKey(string $configName, string $envName): string
    {
        $key = trim((string) (config("services.{$configName}.key") ?: ''));
        if ($key !== '') {
            return $key;
        }

        $fromEnv = trim((string) (Env::get($envName) ?: getenv($envName) ?: ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        return $this->readEnvFileValue($envName);
    }

    protected function resolveProviderModel(string $configName, string $envName, string $default): string
    {
        $model = trim((string) (config("services.{$configName}.model") ?: ''));
        if ($model !== '') {
            return $model;
        }

        $fromEnv = trim((string) (Env::get($envName) ?: getenv($envName) ?: ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }

        $fromFile = $this->readEnvFileValue($envName);

        return $fromFile !== '' ? $fromFile : $default;
    }

    protected function readEnvFileValue(string $name): string
    {
        static $map = null;

        if ($map === null) {
            $map = [];
            $path = base_path('.env');
            if (is_readable($path)) {
                foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                        continue;
                    }
                    [$k, $v] = explode('=', $line, 2);
                    $map[trim($k)] = trim($v, " \t\"'");
                }
            }
        }

        return trim((string) ($map[$name] ?? ''));
    }

    /**
     * Extract assistant text from Gemini generateContent JSON (handles multi-part replies).
     */
    protected function extractGeminiText(?array $json): string
    {
        $parts = data_get($json, 'candidates.0.content.parts', []);
        if (! is_array($parts) || $parts === []) {
            return '';
        }

        $chunks = [];
        foreach ($parts as $part) {
            if (! is_array($part)) {
                continue;
            }
            $t = trim((string) ($part['text'] ?? ''));
            if ($t !== '') {
                $chunks[] = $t;
            }
        }

        return trim(implode("\n", $chunks));
    }

    protected function callOpenAiCompatible(
        string $url,
        ?string $apiKey,
        string $model,
        array $messages,
        int $timeoutSeconds = 12
    ): ?string {
        try {
            $request = Http::timeout($timeoutSeconds)
                ->connectTimeout(4)
                ->acceptJson()
                ->asJson()
                ->withHeaders(['User-Agent' => 'WhatsAppBulkAutoAI/2.5']);

            if (filled($apiKey)) {
                $request = $request->withToken($apiKey);
            }

            // OpenRouter recommends these optional headers.
            if (str_contains($url, 'openrouter.ai')) {
                $request = $request->withHeaders([
                    'HTTP-Referer' => (string) config('app.url', 'https://zapreach.com'),
                    'X-Title' => 'ZapReach Auto AI',
                ]);
            }

            $response = $request->post($url, [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 500,
            ]);

            if (! $response->successful()) {
                Log::warning('OpenAI-compatible provider failed', [
                    'url' => $url,
                    'model' => $model,
                    'status' => $response->status(),
                    'error' => Str::limit((string) data_get($response->json(), 'error.message', $response->body()), 180),
                ]);

                return null;
            }

            $content = trim((string) data_get($response->json(), 'choices.0.message.content', ''));
            if ($content !== '') {
                return $content;
            }

            $plain = trim((string) $response->body());
            if ($plain !== '' && ! str_starts_with($plain, '{')) {
                return $plain;
            }
        } catch (\Throwable $e) {
            Log::warning('OpenAI-compatible Auto AI failed', [
                'url' => $url,
                'model' => $model,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Anthropic / Claude Messages API implementation (Claude 3.5 Haiku / Sonnet).
     */
    protected function callClaude(
        string $apiKey,
        string $model,
        string $system,
        array $messages,
        int $timeoutSeconds = 12
    ): ?string {
        try {
            $anthropicMessages = [];
            foreach ($messages as $msg) {
                $role = $msg['role'] ?? 'user';
                if ($role === 'system') {
                    continue;
                }
                $anthropicMessages[] = [
                    'role' => in_array($role, ['user', 'assistant'], true) ? $role : 'user',
                    'content' => (string) ($msg['content'] ?? ''),
                ];
            }

            if (empty($anthropicMessages)) {
                return null;
            }

            $response = Http::timeout($timeoutSeconds)
                ->connectTimeout(4)
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                    'User-Agent' => 'WhatsAppBulkAutoAI/2.5',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $model ?: 'claude-3-5-haiku-20241022',
                    'system' => $system,
                    'messages' => $anthropicMessages,
                    'max_tokens' => 500,
                    'temperature' => 0.7,
                ]);

            if (! $response->successful()) {
                Log::warning('Claude / Anthropic API failed', [
                    'model' => $model,
                    'status' => $response->status(),
                    'error' => Str::limit((string) data_get($response->json(), 'error.message', $response->body()), 250),
                ]);

                return null;
            }

            $contentParts = data_get($response->json(), 'content', []);
            $textParts = [];
            if (is_array($contentParts)) {
                foreach ($contentParts as $part) {
                    if (($part['type'] ?? '') === 'text') {
                        $textParts[] = (string) ($part['text'] ?? '');
                    }
                }
            }

            $result = trim(implode("\n", $textParts));

            return $result !== '' ? $result : null;
        } catch (\Throwable $e) {
            Log::warning('Claude Auto AI failed', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function historyAsText(array $history): string
    {
        $lines = [];
        foreach (array_slice($history, -8) as $row) {
            $who = ($row['role'] ?? '') === 'assistant' ? 'Business' : 'Customer';
            $lines[] = $who.': '.Str::limit((string) ($row['content'] ?? ''), 180);
        }

        return implode("\n", $lines);
    }

    /**
     * Casual human-like fallback (real WhatsApp talk, Hinglish when user uses Hinglish).
     * Always stays inside the saved business category purpose.
     */
    protected function generateKnowledgeFallback(
        Settings $settings,
        string $customerMessage,
        array $history,
        ?string $lastAiReply = null,
        ?WhatsappConversation $whatsappConversation = null
    ): string {
        $short = $this->shortBizName(trim((string) ($settings->business_name ?: 'our team')));
        $category = $this->resolveBusinessCategory($settings);
        $family = $this->categoryFamily($category);
        $details = trim((string) ($settings->business_details ?: ''));
        $hours = trim((string) ($settings->business_hours ?: ''));
        $style = $this->resolveReplyStyle($history, $customerMessage);
        $lower = mb_strtolower(trim($customerMessage));
        $lead = $whatsappConversation
            ? $this->resolveLeadState($whatsappConversation, $history, $customerMessage)
            : $this->extractLeadState(
                mb_strtolower($this->sessionUserText($history, $customerMessage)),
                $lower,
                $customerMessage
            );

        if ($this->isPureGreeting($lower)) {
            if ($family === 'finance') {
                return $this->pick([
                    $this->byStyle($style, [
                        'english' => "Hi, {$short} here 👋 Looking for mutual funds, insurance, or investment help?",
                        'hindi' => "नमस्ते, {$short} से बात हो रही है। म्यूचुअल फंड, इंश्योरेंस या इन्वेस्टमेंट में मदद चाहिए?",
                        'hinglish' => "Hi, {$short} se bol raha hu 👋 Mutual funds, insurance ya investment mein help chahiye?",
                    ]),
                    $this->byStyle($style, [
                        'english' => "Hey from {$short}. Tell me — SIP, insurance cover, or something else?",
                        'hindi' => "हेलो, {$short}. बताइए — SIP, इंश्योरेंस, या कुछ और?",
                        'hinglish' => "Hey, {$short}. Bataiye — SIP, insurance, ya kuch aur?",
                    ]),
                ]);
            }

            return $this->pick([
                $this->byStyle($style, [
                    'english' => "Hi, {$short} here 👋 what do you need help with?",
                    'hindi' => "नमस्ते, {$short} से बात हो रही है। बताइए कैसे मदद करें?",
                    'hinglish' => "Hi, {$short} se bol raha hu. Bataiye kaise help karu?",
                ]),
                $this->byStyle($style, [
                    'english' => "Hey, thanks for messaging {$short}. Tell me what you need?",
                    'hindi' => "हेलो, {$short} में मैसेज करने के लिए धन्यवाद। क्या चाहिए?",
                    'hinglish' => "Hey, {$short} pe message kiya thanks. Kya chahiye aapko?",
                ]),
                $this->byStyle($style, [
                    'english' => "Hi from {$short} ({$category}). How can I help?",
                    'hindi' => "नमस्ते, {$short} ({$category}). कैसे मदद करूँ?",
                    'hinglish' => "Hi, {$short} ({$category}). Kaise help kar sakta hu?",
                ]),
            ]);
        }

        // User complaining about repeats → apologize and close/advance
        if ($this->isRepeatComplaint($lower)) {
            return $this->pick([
                $this->byStyle($style, [
                    'english' => "Sorry about that — got it now. I’ll take this forward from here.",
                    'hindi' => "सॉरी, अब समझ गया। मैं आगे से हैंडल कर लेता हूँ।",
                    'hinglish' => "Sorry yaar, ab clear hai. Main aage se handle kar leta hu.",
                ]),
                $this->closingLeadReply($lead, $style, $short),
            ]);
        }

        // High-Priority: Check verified business FAQs and Knowledge Base items
        $matchedFaq = $this->findMatchingFaq($customerMessage, $settings, 45.0);
        if ($matchedFaq !== null && !empty($matchedFaq['answer'])) {
            return $matchedFaq['answer'];
        }

        if ($this->containsAny($lower, ['hour', 'timing', 'open', 'close', 'kab khulta', 'kab band']) && $hours !== '') {
            return $this->byStyle($style, [
                'english' => "We’re usually around {$hours}. Anything else?",
                'hindi' => "आमतौर पर {$hours} रहते हैं। और कुछ?",
                'hinglish' => "Usually {$hours} available rehte hain. Aur kuch?",
            ]);
        }

        // Who are you / what do you do → answer with category purpose (not brochure dump)
        if ($this->containsAny($lower, ['who are you', 'what do you do', 'kya karte', 'services', 'service', 'company', 'about'])) {
            if ($family === 'finance') {
                return $this->byStyle($style, [
                    'english' => "We’re {$short} — mutual funds, equity, life/health insurance & credit cards. What are you looking for?",
                    'hindi' => "हम {$short} हैं — म्यूचुअल फंड, इक्विटी, लाइफ/हेल्थ इंश्योरेंस और क्रेडिट कार्ड। आपको क्या चाहिए?",
                    'hinglish' => "Hum {$short} hain — mutual funds, equity, life/health insurance aur credit cards. Aapko kya chahiye?",
                ]);
            }

            return $this->byStyle($style, [
                'english' => "This is {$short} — we handle {$category}. What do you need?",
                'hindi' => "यह {$short} है — हम {$category} करते हैं। आपको क्या चाहिए?",
                'hinglish' => "Yeh {$short} hai — hum {$category} handle karte hain. Aapko kya chahiye?",
            ]);
        }

        // Soft thanks / sure after details → don't restart funnel
        if ($this->isSoftFollowUp($lower) && ($lead['isComplete'] || $lead['budget'] || $lead['phone'] || $lead['topic'])) {
            return $this->softAckReply($style);
        }

        // Finance: natural product talk using business details — never website budget/pages
        if ($family === 'finance') {
            return $this->financeLocalReply($short, $style, $lower, $details, $hours, $lead);
        }

        // IT / software category: step-by-step lead flow (never re-ask known fields)
        if ($family === 'it' && ($lead['wantsDev'] || $lead['hasPages'] || $lead['projectName'] || $lead['topic'])) {
            return $this->itLeadReply($lead, $style, $short, $category, $lastAiReply);
        }

        // Non-IT categories: stay in category purpose (don't invent website-sales talk)
        if ($family !== 'it') {
            $categoryReply = $this->categoryLocalReply($family, $category, $short, $style, $lower, '', $lead['budget']);
            if ($categoryReply !== null) {
                return $categoryReply;
            }
        }

        $relevant = $this->findRelevantKnowledge($details, $lower, $settings);
        if ($relevant !== [] && mb_strlen($relevant[0]) <= 140) {
            $fact = Str::limit($relevant[0], 130);

            return $this->byStyle($style, [
                'english' => "Yep — {$fact} Want me to check anything else?",
                'hindi' => "हाँ — {$fact} और कुछ चेक करूँ?",
                'hinglish' => "Haan — {$fact} Aur kuch check karu?",
            ]);
        }

        return $this->byStyle($style, [
            'english' => "Sure, tell me what you need for {$category}.",
            'hindi' => "ठीक है, {$category} में क्या चाहिए बताइए।",
            'hinglish' => "Theek hai, {$category} mein kya chahiye bataiye.",
        ]);
    }

    /**
     * Human finance/finserve fallback when live AI is unavailable.
     */
    protected function financeLocalReply(
        string $short,
        string $style,
        string $lower,
        string $details,
        string $hours,
        array $lead
    ): string {
        if ($this->containsAny($lower, ['mutual', 'mf', 'sip', 'invest', 'equity', 'share', 'stock'])) {
            return $this->pick([
                $this->byStyle($style, [
                    'english' => "Sure — we help with mutual funds & equity. Goal kya hai: wealth create, child plan, ya retirement?",
                    'hindi' => "ज़रूर — हम म्यूचुअल फंड और इक्विटी में मदद करते हैं। लक्ष्य क्या है: वेल्थ, चाइल्ड प्लान, या रिटायरमेंट?",
                    'hinglish' => "Zaroor — hum mutual funds & equity help karte hain. Goal kya hai: wealth, child plan, ya retirement?",
                ]),
                $this->byStyle($style, [
                    'english' => "Investment side pe help kar sakte hain. Monthly SIP roughly kitna soch rahe ho?",
                    'hindi' => "इन्वेस्टमेंट में मदद कर सकते हैं। मंथली SIP लगभग कितना सोच रहे हो?",
                    'hinglish' => "Investment mein help kar sakte hain. Monthly SIP roughly kitna soch rahe ho?",
                ]),
            ]);
        }

        if ($this->containsAny($lower, ['insurance', 'insur', 'policy', 'life', 'health', 'mediclaim', 'term'])) {
            return $this->byStyle($style, [
                'english' => "Insurance cover ke liye help karenge. Life, health, ya general — kaunsa dekh rahe ho?",
                'hindi' => "इंश्योरेंस कवर में मदद करेंगे। लाइफ, हेल्थ या जनरल — कौन सा देख रहे हो?",
                'hinglish' => "Insurance cover mein help karenge. Life, health, ya general — kaunsa dekh rahe ho?",
            ]);
        }

        if ($this->containsAny($lower, ['credit card', 'card', 'loan', 'emi'])) {
            return $this->byStyle($style, [
                'english' => "Credit cards / related products pe guide kar sakte hain. Kis cheez ke liye dekh rahe ho?",
                'hindi' => "क्रेडिट कार्ड आदि में गाइड कर सकते हैं। किसके लिए देख रहे हो?",
                'hinglish' => "Credit cards wagaira pe guide kar sakte hain. Kis cheez ke liye dekh rahe ho?",
            ]);
        }

        if ($hours !== '' && $this->containsAny($lower, ['hour', 'timing', 'open', 'close', 'kab'])) {
            return $this->byStyle($style, [
                'english' => "We’re usually available {$hours}. Call/message anytime in that window.",
                'hindi' => "आमतौर पर {$hours} उपलब्ध रहते हैं। उसी समय मैसेज/कॉल कर सकते हैं।",
                'hinglish' => "Usually {$hours} available rehte hain. Us time pe message/call kar sakte ho.",
            ]);
        }

        $relevant = $this->findRelevantKnowledge($details, $lower);
        if ($relevant !== []) {
            $fact = Str::limit($relevant[0], 120);

            return $this->byStyle($style, [
                'english' => "{$fact} Want me to explain any product in simple words?",
                'hindi' => "{$fact} कोई प्रोडक्ट आसान भाषा में समझाऊँ?",
                'hinglish' => "{$fact} Koi product simple words mein samjhaun?",
            ]);
        }

        if (! ($lead['topic'] ?? null)) {
            return $this->byStyle($style, [
                'english' => "{$short} pe finance help milti hai — MF, insurance, equity, credit cards. Aapko kis area mein help chahiye?",
                'hindi' => "{$short} पर फाइनेंस हेल्प मिलती है — MF, इंश्योरेंस, इक्विटी, क्रेडिट कार्ड। किसमें मदद चाहिए?",
                'hinglish' => "{$short} pe finance help milti hai — MF, insurance, equity, credit cards. Kis area mein help chahiye?",
            ]);
        }

        return $this->byStyle($style, [
            'english' => "Got it. Thoda aur batao — goal ya amount — taaki main sahi suggest kar saku.",
            'hindi' => "समझ गया। थोड़ा और बताओ — लक्ष्य या अमाउंट — ताकि सही सुझाव दे सकूँ।",
            'hinglish' => "Samajh gaya. Thoda aur batao — goal ya amount — taaki sahi suggest kar saku.",
        ]);
    }

    /**
     * Track what the customer already shared in THIS session (after last greeting).
     */
    /**
     * Merge persisted lead + current session text (history window can drop early messages).
     */
    protected function resolveLeadState(WhatsappConversation $whatsapp, array $history, string $latest): array
    {
        $fromChat = $this->extractLeadState(
            mb_strtolower($this->sessionUserText($history, $latest)),
            mb_strtolower($latest),
            $latest
        );
        $stored = $this->readStoredLead($whatsapp);

        $merged = [
            'budget' => $fromChat['budget'] ?: ($stored['budget'] ?? null),
            'topic' => $fromChat['topic'] ?: ($stored['topic'] ?? null),
            'projectName' => $fromChat['projectName'] ?: ($stored['projectName'] ?? null),
            'hasPages' => (bool) ($fromChat['hasPages'] || ($stored['hasPages'] ?? false)),
            'pagesSummary' => $fromChat['pagesSummary'] ?: ($stored['pagesSummary'] ?? null),
            'phone' => $fromChat['phone'] ?: ($stored['phone'] ?? null),
            'timeline' => $fromChat['timeline'] ?: ($stored['timeline'] ?? null),
            'wantsDev' => (bool) ($fromChat['wantsDev'] || ($stored['wantsDev'] ?? false)),
            'fromScratch' => (bool) ($fromChat['fromScratch'] || ($stored['fromScratch'] ?? false)),
            'isComplete' => false,
        ];

        $merged['isComplete'] = (bool) (
            ($stored['complete'] ?? false)
            || (
                $merged['budget']
                && ($merged['projectName'] || $merged['hasPages'] || $merged['topic'])
                && $merged['phone']
                && $merged['timeline']
            )
        );

        return $merged;
    }

    protected function readStoredLead(WhatsappConversation $whatsapp): array
    {
        $meta = is_array($whatsapp->metadata) ? $whatsapp->metadata : [];

        return is_array($meta['auto_ai_lead'] ?? null) ? $meta['auto_ai_lead'] : [];
    }

    protected function persistLeadState(WhatsappConversation $whatsapp, array $lead): void
    {
        $meta = is_array($whatsapp->metadata) ? $whatsapp->metadata : [];
        $prev = is_array($meta['auto_ai_lead'] ?? null) ? $meta['auto_ai_lead'] : [];
        $meta['auto_ai_lead'] = array_filter([
            'budget' => $lead['budget'] ?? ($prev['budget'] ?? null),
            'topic' => $lead['topic'] ?? ($prev['topic'] ?? null),
            'projectName' => $lead['projectName'] ?? ($prev['projectName'] ?? null),
            'hasPages' => (bool) (($lead['hasPages'] ?? false) || ($prev['hasPages'] ?? false)),
            'pagesSummary' => $lead['pagesSummary'] ?? ($prev['pagesSummary'] ?? null),
            'phone' => $lead['phone'] ?? ($prev['phone'] ?? null),
            'timeline' => $lead['timeline'] ?? ($prev['timeline'] ?? null),
            'wantsDev' => (bool) (($lead['wantsDev'] ?? false) || ($prev['wantsDev'] ?? false)),
            'fromScratch' => (bool) (($lead['fromScratch'] ?? false) || ($prev['fromScratch'] ?? false)),
            'complete' => (bool) (($lead['isComplete'] ?? false) || ($prev['complete'] ?? false)),
        ], fn ($v) => $v !== null && $v !== false && $v !== '');

        // Keep boolean false flags that matter
        $meta['auto_ai_lead']['hasPages'] = (bool) (($lead['hasPages'] ?? false) || ($prev['hasPages'] ?? false));
        $meta['auto_ai_lead']['wantsDev'] = (bool) (($lead['wantsDev'] ?? false) || ($prev['wantsDev'] ?? false));
        $meta['auto_ai_lead']['complete'] = (bool) (($lead['isComplete'] ?? false) || ($prev['complete'] ?? false));

        $whatsapp->update(['metadata' => $meta]);
        $whatsapp->refresh();
    }

    protected function markLeadComplete(WhatsappConversation $whatsapp, array $lead): void
    {
        $lead['isComplete'] = true;
        $this->persistLeadState($whatsapp, $lead);
        $meta = is_array($whatsapp->metadata) ? $whatsapp->metadata : [];
        $meta['auto_ai_lead']['complete'] = true;
        $whatsapp->update(['metadata' => $meta]);
    }

    protected function clearLeadState(WhatsappConversation $whatsapp): void
    {
        $meta = is_array($whatsapp->metadata) ? $whatsapp->metadata : [];
        unset($meta['auto_ai_lead']);
        $whatsapp->update(['metadata' => $meta]);
    }

    protected function isLeadMarkedComplete(WhatsappConversation $whatsapp): bool
    {
        $stored = $this->readStoredLead($whatsapp);

        return (bool) ($stored['complete'] ?? false);
    }

    protected function isSoftFollowUp(string $lower): bool
    {
        $lower = trim($lower);
        if ($lower === '') {
            return false;
        }

        if (preg_match('/^(sure|ok|okay|thanks|thank you|thx|haan|han|ji|yes|done|fine|great|perfect|alright)(\s+sir)?[\s!.]*$/iu', $lower)) {
            return true;
        }

        return $this->containsAny($lower, [
            'please do fast', 'do fast', 'jaldi', 'as soon as', 'please fast',
            'this is my wp', 'this is my whatsapp', 'my wp number', 'my whatsapp number',
            'my number', 'yeh mera number', 'mera number',
        ]) && mb_strlen($lower) <= 80;
    }

    protected function softAckReply(string $style): string
    {
        return $this->pick([
            $this->byStyle($style, [
                'english' => 'Sure, on it — I’ll update you soon.',
                'hindi' => 'ठीक है, कर रहा हूँ — जल्द अपडेट दूँगा।',
                'hinglish' => 'Theek hai, kar raha hu — jaldi update dunga.',
            ]),
            $this->byStyle($style, [
                'english' => 'Got it. Working on this.',
                'hindi' => 'समझ गया। इसपे काम कर रहा हूँ।',
                'hinglish' => 'Samajh gaya. Ispe kaam kar raha hu.',
            ]),
            $this->byStyle($style, [
                'english' => 'Okay, noted. I’ll move fast on this.',
                'hindi' => 'ओके, नोट किया। जल्दी करता हूँ।',
                'hinglish' => 'Ok note hai. Jaldi karta hu ispe.',
            ]),
        ]);
    }

    protected function looksLikeClosingReply(string $reply): bool
    {
        $lower = mb_strtolower($reply);

        return $this->containsAny($lower, [
            'locked', 'get back to you', 'connect with you soon', 'working on this',
            'on it', 'sab mil gaya', 'sab set hai', 'update dunga', 'wapas aata',
        ]);
    }

    protected function recentClosingSent(WhatsappConversation $whatsapp, int $withinMinutes = 15): bool
    {
        $recent = WhatsappMessage::query()
            ->where('conversation_id', $whatsapp->id)
            ->where('direction', 'outgoing')
            ->where('metadata->auto_ai', true)
            ->where('created_at', '>=', now()->subMinutes($withinMinutes))
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        foreach ($recent as $msg) {
            $meta = is_array($msg->metadata) ? $msg->metadata : [];
            if (! empty($meta['auto_ai_closing']) || $this->looksLikeClosingReply((string) $msg->message)) {
                return true;
            }
        }

        return false;
    }

    protected function extractLeadState(string $fullUser, string $lower, string $latest): array
    {
        $blob = mb_strtolower($fullUser.' '.$lower.' '.$latest);
        $budget = $this->extractBudget($blob);
        $topic = $this->extractTopic($blob);
        $projectName = $this->extractProjectName($blob);
        $pagesSummary = $this->extractPagesSummary($blob);
        $phone = $this->extractPhone($blob);
        $timeline = $this->extractTimeline($blob);
        $hasPages = $pagesSummary !== null
            || $this->containsAny($blob, [
                'landing page', 'landing', 'home page', 'about page', 'contact page',
                'plans page', 'free trial', 'book free', 'gallery', 'services page',
            ]);
        $wantsDev = $this->containsAny($blob, [
            'website', 'web site', 'webiste', 'webpage', 'site', 'app', 'software', 'portal', 'landing',
        ]);
        $fromScratch = $this->containsAny($blob, ['from scratch', 'scratch', 'scratch se']);
        $isComplete = (bool) ($budget && ($projectName || $hasPages || $topic) && $phone && $timeline);

        return [
            'budget' => $budget,
            'topic' => $topic,
            'projectName' => $projectName,
            'hasPages' => $hasPages,
            'pagesSummary' => $pagesSummary,
            'phone' => $phone,
            'timeline' => $timeline,
            'wantsDev' => $wantsDev,
            'fromScratch' => $fromScratch,
            'isComplete' => $isComplete,
        ];
    }

    /**
     * Only use user messages after the latest greeting so old leads don't leak.
     */
    protected function sessionUserText(array $history, string $latest): string
    {
        $parts = [];
        $started = false;
        foreach ($history as $row) {
            if (($row['role'] ?? '') !== 'user') {
                continue;
            }
            $content = trim((string) ($row['content'] ?? ''));
            if ($content === '') {
                continue;
            }
            if ($this->isPureGreeting(mb_strtolower($content))) {
                $parts = [];
                $started = true;

                continue;
            }
            $parts[] = $content;
            $started = true;
        }

        $latestTrim = trim($latest);
        if ($latestTrim !== '' && ! $this->isPureGreeting(mb_strtolower($latestTrim))) {
            // Avoid duplicate if history already ends with same message
            if ($parts === [] || mb_strtolower(end($parts)) !== mb_strtolower($latestTrim)) {
                $parts[] = $latestTrim;
            }
        }

        if (! $started && $parts === []) {
            return mb_strtolower($this->allUserText($history, $latest));
        }

        return implode(' ', $parts);
    }

    protected function extractPhone(string $text): ?string
    {
        if (preg_match('/(?:\+?91[\s-]*)?([6-9]\d{9})\b/u', $text, $m)) {
            return $m[1];
        }
        if (preg_match('/\b(\d{10,12})\b/u', $text, $m)) {
            return $m[1];
        }

        return null;
    }

    protected function extractTimeline(string $text): ?string
    {
        if (preg_match('/\b(\d+)\s*(days?|din|weeks?|months?)\b/iu', $text, $m)) {
            $n = $m[1];
            $unit = strtolower($m[2]);
            if (str_starts_with($unit, 'din') || str_starts_with($unit, 'day')) {
                return $n.' days';
            }
            if (str_starts_with($unit, 'week')) {
                return $n.' weeks';
            }

            return $n.' months';
        }

        return null;
    }

    protected function isRepeatComplaint(string $lower): bool
    {
        return $this->containsAny($lower, [
            'same same', 'repete', 'repeat', 'barabar', 'baar baar', 'same reply',
            'same message', 'phir se', 'dobara', 'again again', 'why same', 'kyu same',
        ]);
    }

    protected function extractProjectName(string $text): ?string
    {
        if (preg_match('/(?:gym\s*name|business\s*name|company\s*name|project\s*name|name\s*(?:is|:))\s*([a-z0-9][a-z0-9\s&.\'-]{0,40})/iu', $text, $m)) {
            $name = trim(preg_replace('/\s+(i\s+want|and|with|for|on|i\s+wna).*$/iu', '', $m[1]) ?: $m[1]);
            $name = trim($name, " \t\n\r\0\x0B.,!");
            if (mb_strlen($name) >= 2 && ! in_array(mb_strtolower($name), ['my', 'our', 'the', 'a'], true)) {
                return Str::limit($name, 40, '');
            }
        }

        if (preg_match('/\b([a-z0-9]{1,16}\s+gym)\b/iu', $text, $m)) {
            $cand = mb_strtolower(trim($m[1]));
            if (! in_array($cand, ['my gym', 'our gym', 'a gym', 'the gym', 'for gym', 'gym gym'], true)) {
                return trim($m[1]);
            }
        }

        return null;
    }

    protected function extractPagesSummary(string $text): ?string
    {
        $found = [];
        $map = [
            'landing page' => 'landing page',
            'landing' => 'landing page',
            'home page' => 'home',
            'about page' => 'about',
            'contact page' => 'contact',
            'plans page' => 'plans',
            'plans' => 'plans',
            'free trial' => 'book free trial',
            'book free' => 'book free trial',
            'gallery' => 'gallery',
            'services page' => 'services',
            'pricing' => 'pricing',
        ];
        // Do not treat "contact number" as a website contact page
        $scan = preg_replace('/contact\s*(number|no|num|phone|mobile)/iu', ' ', $text) ?: $text;

        foreach ($map as $needle => $label) {
            if (str_contains($scan, $needle)) {
                $found[$label] = $label;
            }
        }

        if ($found === [] && preg_match('/\b(\d+)\s*pages?\b/u', $scan, $m)) {
            return $m[1].' pages';
        }

        if ($found === []) {
            return null;
        }

        return implode(', ', array_values($found));
    }

    protected function itLeadReply(array $lead, string $style, string $short, string $category, ?string $lastAiReply = null): string
    {
        $topic = $lead['topic'] ?: 'project';
        $budget = $lead['budget'];
        $name = $lead['projectName'];
        $pages = $lead['pagesSummary'];
        $phone = $lead['phone'];
        $timeline = $lead['timeline'];

        if (! empty($lead['isComplete']) || ($budget && ($name || $lead['hasPages']) && $phone && $timeline)) {
            return $this->closingLeadReply($lead, $style, $short);
        }

        if ($budget && ! $name && ! $lead['hasPages'] && $phone && $timeline) {
            return $this->closingLeadReply($lead, $style, $short);
        }

        if ($budget && ($name || $lead['hasPages'])) {
            if ($phone && ! $timeline) {
                return $this->pick([
                    $this->byStyle($style, [
                        'english' => 'Number mila. Kab tak chahiye — 10 days, 15 days?',
                        'hindi' => 'नंबर मिल गया। कब तक चाहिए — 10 दिन, 15 दिन?',
                        'hinglish' => 'Number mil gaya. Kab tak chahiye — 10 days, 15 days?',
                    ]),
                    $this->byStyle($style, [
                        'english' => 'Got it. Kitne din mein deliver karna hai?',
                        'hindi' => 'समझ गया। कितने दिन में डिलीवर करना है?',
                        'hinglish' => 'Samajh gaya. Kitne din mein deliver karna hai?',
                    ]),
                ]);
            }

            if ($timeline && ! $phone) {
                return $this->pick([
                    $this->byStyle($style, [
                        'english' => "{$timeline} ok hai. Apna number bhej do, main note kar lu.",
                        'hindi' => "{$timeline} ठीक है। अपना नंबर भेज दो।",
                        'hinglish' => "{$timeline} ok hai. Apna number bhej do, main note kar lu.",
                    ]),
                    $this->byStyle($style, [
                        'english' => 'Timeline clear. WhatsApp number ek baar share kar dena.',
                        'hindi' => 'टाइमलाइन क्लियर। व्हाट्सऐप नंबर शेयर कर देना।',
                        'hinglish' => 'Timeline clear. WhatsApp number ek baar share kar dena.',
                    ]),
                ]);
            }

            $who = $name ?: $topic;

            return $this->pick([
                $this->byStyle($style, [
                    'english' => "{$who} + {$budget} samajh gaya. Timeline aur number bata do.",
                    'hindi' => "{$who} और {$budget} समझ गया। टाइमलाइन और नंबर बता दो।",
                    'hinglish' => "{$who} + {$budget} samajh gaya. Timeline aur number bata do.",
                ]),
                $this->byStyle($style, [
                    'english' => "Theek, {$who} ke liye {$budget}. Kab tak chahiye aur number?",
                    'hindi' => "ठीक, {$who} के लिए {$budget}. कब तक चाहिए और नंबर?",
                    'hinglish' => "Theek, {$who} ke liye {$budget}. Kab tak chahiye aur number?",
                ]),
            ]);
        }

        if ($budget && ! $name && ! $lead['hasPages']) {
            return $this->pick([
                $this->byStyle($style, [
                    'english' => "{$budget} ok. Naam kya rakhna hai aur kaunse pages?",
                    'hindi' => "{$budget} ठीक। नाम क्या रखना है और कौन से पेज?",
                    'hinglish' => "{$budget} ok. Naam kya rakhna hai aur kaunse pages chahiye?",
                ]),
                $this->byStyle($style, [
                    'english' => 'Budget clear. Business name + pages list bhej dena.',
                    'hindi' => 'बजट क्लियर। बिजनेस नाम और पेज लिस्ट भेज देना।',
                    'hinglish' => 'Budget clear. Business name + pages list bhej dena.',
                ]),
            ]);
        }

        if (($name || $lead['hasPages']) && ! $budget) {
            $hint = $name ?: ($pages ?: $topic);

            return $this->pick([
                $this->byStyle($style, [
                    'english' => "{$hint} ke liye rough budget kitna hai?",
                    'hindi' => "{$hint} के लिए लगभग बजट कितना है?",
                    'hinglish' => "{$hint} ke liye rough budget kitna hai?",
                ]),
                $this->byStyle($style, [
                    'english' => 'Samajh gaya. Budget roughly bata do?',
                    'hindi' => 'समझ गया। बजट लगभग बता दो?',
                    'hinglish' => 'Samajh gaya. Budget roughly bata do?',
                ]),
            ]);
        }

        if ($lead['fromScratch'] && ! $budget) {
            return $this->byStyle($style, [
                'english' => 'Scratch se bana sakte hain. Budget roughly?',
                'hindi' => 'शुरुआत से बना सकते हैं। बजट लगभग?',
                'hinglish' => 'Scratch se bana sakte hain. Budget roughly?',
            ]);
        }

        return $this->pick([
            $this->byStyle($style, [
                'english' => "Haan, {$topic} website bana sakte hain. Budget kya soch rahe ho?",
                'hindi' => "हाँ, {$topic} वेबसाइट बना सकते हैं। बजट क्या सोच रहे हो?",
                'hinglish' => "Haan, {$topic} website bana sakte hain. Budget kya soch rahe ho?",
            ]),
            $this->byStyle($style, [
                'english' => "Sure, {$topic} site possible. Rough budget bata dena?",
                'hindi' => "ज़रूर, {$topic} साइट हो सकती है। लगभग बजट बता देना?",
                'hinglish' => "Zaroor, {$topic} site ho sakti hai. Rough budget bata dena?",
            ]),
        ]);
    }

    protected function closingLeadReply(array $lead, string $style, string $short): string
    {
        $name = $lead['projectName'] ?? null;
        $topic = $lead['topic'] ?? null;
        $label = $name ?: ($topic ?: 'project');

        return $this->pick([
            $this->byStyle($style, [
                'english' => "Done, {$label} details mil gaye. Main check karke jaldi wapas aata hu.",
                'hindi' => "हो गया, {$label} डिटेल मिल गई। मैं चेक करके जल्द वापस आता हूँ।",
                'hinglish' => "Done, {$label} details mil gaye. Main check karke jaldi wapas aata hu.",
            ]),
            $this->byStyle($style, [
                'english' => "Perfect. {$short} team se jaldi connect karte hain ispe.",
                'hindi' => "परफेक्ट। {$short} टीम से जल्दी कनेक्ट करते हैं इसपे।",
                'hinglish' => "Perfect. {$short} team se jaldi connect karte hain ispe.",
            ]),
            $this->byStyle($style, [
                'english' => 'Sab clear hai. Main ispe kaam start karta hu — update dunga.',
                'hindi' => 'सब क्लियर है। मैं इसपे काम स्टार्ट करता हूँ — अपडेट दूँगा।',
                'hinglish' => 'Sab clear hai. Main ispe kaam start karta hu — update dunga.',
            ]),
        ]);
    }

    protected function asksForAlreadyKnown(
        string $reply,
        string $customerMessage,
        array $history,
        ?WhatsappConversation $whatsapp = null
    ): bool {
        $lead = $whatsapp
            ? $this->resolveLeadState($whatsapp, $history, $customerMessage)
            : $this->extractLeadState(
                mb_strtolower($this->sessionUserText($history, $customerMessage)),
                mb_strtolower($customerMessage),
                $customerMessage
            );
        $lower = mb_strtolower($reply);

        if ($lead['isComplete'] && preg_match('/(timeline|contact number|budget|required pages|rough budget)/u', $lower)) {
            return true;
        }

        if ($lead['budget'] && preg_match('/(approximate\s+budget|rough budget|budget\s+share|share.{0,20}budget|budget\s+bataye|what.?s your (rough )?budget|बजट)/u', $lower)) {
            return true;
        }

        if (($lead['projectName'] || $lead['hasPages']) && preg_match('/(name and required pages|naam aur required pages|name and pages|आवश्यक पेज)/u', $lower)) {
            return true;
        }

        if ($lead['phone'] && preg_match('/(contact number|phone number|whatsapp number|संपर्क नंबर|apna number)/u', $lower)
            && ! preg_match('/noted|got your number|number mil|save kar/u', $lower)) {
            return true;
        }

        if ($lead['timeline'] && preg_match('/(preferred timeline|timeline share|kitne din|टाइमलाइन)/u', $lower)
            && ! preg_match('/works|noted|theek|ok on/u', $lower)) {
            return true;
        }

        if (($lead['phone'] || $lead['timeline']) && preg_match('/timeline or (a )?contact number|timeline aur (contact )?number|timeline ya contact/u', $lower)) {
            return true;
        }

        return false;
    }

    protected function nextStepAfterRepeat(
        Settings $settings,
        string $customerMessage,
        array $history,
        string $lastAiReply,
        ?WhatsappConversation $whatsapp = null
    ): string {
        $style = $this->resolveReplyStyle($history, $customerMessage);
        $category = $this->resolveBusinessCategory($settings);
        $short = $this->shortBizName(trim((string) ($settings->business_name ?: 'our team')));
        $lead = $whatsapp
            ? $this->resolveLeadState($whatsapp, $history, $customerMessage)
            : $this->extractLeadState(
                mb_strtolower($this->sessionUserText($history, $customerMessage)),
                mb_strtolower($customerMessage),
                $customerMessage
            );

        if ($lead['isComplete'] || ($lead['phone'] && $lead['timeline'] && $lead['budget'])) {
            if ($this->looksLikeClosingReply($lastAiReply)) {
                return $this->softAckReply($style);
            }

            return $this->closingLeadReply($lead, $style, $short);
        }

        if ($this->isSoftFollowUp(mb_strtolower($customerMessage))) {
            return $this->softAckReply($style);
        }

        $family = $this->categoryFamily($category);
        if ($family === 'finance') {
            return $this->financeLocalReply(
                $short,
                $style,
                mb_strtolower($customerMessage),
                trim((string) ($settings->business_details ?: '')),
                trim((string) ($settings->business_hours ?: '')),
                $lead
            );
        }

        if ($family === 'it' && ($lead['wantsDev'] || $lead['hasPages'] || $lead['projectName'] || $lead['topic'])) {
            $reply = $this->itLeadReply($lead, $style, $short, $category, $lastAiReply);
            if (! $this->isTooSimilar($reply, $lastAiReply) && ! $this->asksForAlreadyKnown($reply, $customerMessage, $history, $whatsapp)) {
                return $reply;
            }

            return $this->softAckReply($style);
        }

        return $this->generateKnowledgeFallback($settings, $customerMessage, $history, $lastAiReply, $whatsapp);
    }

    protected function categoryLocalReply(
        string $family,
        string $category,
        string $short,
        string $style,
        string $lower,
        string $fullUser,
        ?string $budget
    ): ?string {
        if ($this->containsAny($lower, ['price', 'rate', 'cost', 'kitna', 'fees', 'charge', 'budget']) || $budget) {
            return $this->byStyle($style, [
                'english' => $budget
                    ? "Okay around {$budget}. What exactly do you need in {$category}?"
                    : "Depends on what you need — tell me for {$category}?",
                'hindi' => $budget
                    ? "ठीक है लगभग {$budget}. {$category} में exactly क्या चाहिए?"
                    : "ज़रूरत पर निर्भर करता है — {$category} में क्या चाहिए?",
                'hinglish' => $budget
                    ? "Theek, around {$budget}. {$category} mein exactly kya chahiye?"
                    : "Depend karta hai requirement pe — {$category} mein kya chahiye?",
            ]);
        }

        if ($this->containsAny($lower, ['book', 'appointment', 'slot', 'visit', 'order', 'delivery', 'menu', 'course', 'admission'])) {
            return $this->byStyle($style, [
                'english' => "Sure. Send date/time or a quick detail for {$category}.",
                'hindi' => "ठीक है। {$category} के लिए डेट/टाइम या डिटेल भेज दें।",
                'hinglish' => "Theek. {$category} ke liye date/time ya detail bhej dena.",
            ]);
        }

        if (mb_strlen($lower) >= 8) {
            return $this->byStyle($style, [
                'english' => "Got it. Can you add a bit more detail?",
                'hindi' => "समझ गया। थोड़ी और डिटेल दे सकते हैं?",
                'hinglish' => "Samajh gaya. Thodi aur detail de sakte ho?",
            ]);
        }

        return null;
    }

    protected function casualFallback(Settings $settings, string $customerMessage, array $history): string
    {
        $style = $this->resolveReplyStyle($history, $customerMessage);
        $category = $this->resolveBusinessCategory($settings);
        $short = $this->shortBizName(trim((string) ($settings->business_name ?: 'our team')));
        $lower = mb_strtolower(trim($customerMessage));

        if ($this->isPureGreeting($lower)) {
            return $this->byStyle($style, [
                'english' => "Hi, {$short} here. What do you need?",
                'hindi' => "नमस्ते, {$short} से। क्या चाहिए?",
                'hinglish' => "Hi, {$short} se. Kya chahiye?",
            ]);
        }

        return $this->byStyle($style, [
            'english' => "Tell me what you need for {$category}.",
            'hindi' => "{$category} में क्या चाहिए बताइए।",
            'hinglish' => "{$category} mein kya chahiye bataiye.",
        ]);
    }

    protected function extractBudget(string $text): ?string
    {
        // Strip phone numbers so they never get treated as budget
        $scan = preg_replace('/(?:\+?91[\s-]*)?[6-9]\d{9}\b/u', ' ', $text) ?: $text;
        $scan = preg_replace('/\b\d{10,13}\b/u', ' ', $scan) ?: $scan;

        if (preg_match('/\b(\d{1,3})\s*k\b/i', $scan, $m)) {
            return ((int) $m[1]).'k';
        }
        if (preg_match('/\b(\d{1,3})\s*j\b/i', $scan, $m)) {
            return ((int) $m[1]).'k';
        }
        if (preg_match('/(?:budget|budgget|budjet)[^0-9]{0,12}(\d{1,7})/i', $scan, $m)) {
            $n = (int) $m[1];

            return $n >= 1000 ? $n.' approx' : $n.'k';
        }
        // Standalone amount like 15000 (not a phone)
        if (preg_match('/\b(\d{4,6})\b/', $scan, $m)) {
            $n = (int) $m[1];
            if ($n >= 1000 && $n <= 500000) {
                return (string) $n;
            }
        }

        return null;
    }

    protected function extractTopic(string $text): ?string
    {
        foreach ([
            'mutual fund' => 'mutual funds',
            'mutual' => 'mutual funds',
            'sip' => 'SIP',
            'insurance' => 'insurance',
            'life insurance' => 'life insurance',
            'health insurance' => 'health insurance',
            'equity' => 'equity',
            'credit card' => 'credit card',
            'investment' => 'investment',
            'gym' => 'gym',
            'salon' => 'salon',
            'clinic' => 'clinic',
            'restaurant' => 'restaurant',
            'shop' => 'shop',
            'hotel' => 'hotel',
            'website' => 'website',
            'app' => 'app',
        ] as $needle => $label) {
            if (str_contains($text, $needle)) {
                return $label;
            }
        }

        return null;
    }

    protected function shortBizName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return 'hum';
        }

        // Prefer a short first word, avoid long legal names in every reply
        $parts = preg_split('/\s+/', $name) ?: [$name];
        $first = (string) ($parts[0] ?? $name);

        return mb_strlen($first) >= 3 ? $first : Str::limit($name, 18, '');
    }

    protected function pick(array $options): string
    {
        $options = array_values(array_filter($options));
        if ($options === []) {
            return 'Hello! Kaise madad kar sakta hoon?';
        }

        return $options[array_rand($options)];
    }

    protected function askNextStepReply(Settings $settings, string $customerMessage, array $history): string
    {
        return $this->casualFallback($settings, $customerMessage, $history);
    }

    protected function allUserText(array $history, string $latest): string
    {
        $parts = [];
        foreach ($history as $row) {
            if (($row['role'] ?? '') === 'user') {
                $parts[] = (string) $row['content'];
            }
        }
        $parts[] = $latest;

        return implode(' ', $parts);
    }

    protected function isPureGreeting(string $lower): bool
    {
        return (bool) preg_match('/^(hi|hello|hey|hii|helo|namaste|good morning|good evening|good afternoon)[\s!.]*$/u', trim($lower));
    }

    protected function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, mb_strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    protected function findRelevantKnowledge(string $details, string $query, ?Settings $settings = null): array
    {
        $lines = [];
        if (trim($details) !== '') {
            $lines = preg_split('/[\r\n•|;]+/', $details) ?: [];
        }

        if ($settings) {
            try {
                $faqs = $this->loadAllUserFaqs($settings);
                foreach ($faqs as $f) {
                    $lines[] = ($f['question'] ?? '') . ': ' . ($f['answer'] ?? '');
                }
            } catch (\Throwable $e) {
            }
        }

        if (empty($lines)) {
            return [];
        }
        $qTokens = $this->tokens($query);
        $scored = [];

        foreach ($lines as $line) {
            $line = trim(preg_replace('/\s+/', ' ', $line) ?: '');
            if ($line === '' || mb_strlen($line) < 4) {
                continue;
            }

            $score = 0;
            foreach ($this->tokens($line) as $token) {
                if (mb_strlen($token) < 3) {
                    continue;
                }
                if (in_array($token, $qTokens, true)) {
                    $score += mb_strlen($token) >= 5 ? 2 : 1;
                }
            }

            if ($score > 0) {
                $scored[] = ['score' => $score, 'line' => $line];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_values(array_map(fn ($row) => $row['line'], array_slice($scored, 0, 3)));
    }

    protected function briefNeedFromHistory(string $allUserText): string
    {
        $all = mb_strtolower($allUserText);
        $topics = [
            'website', 'web site', 'app', 'android', 'ios', 'logo', 'design', 'seo',
            'delivery', 'order', 'price', 'pricing', 'appointment', 'booking',
            'service', 'product', 'package', 'demo', 'quotation', 'quote',
        ];

        $found = [];
        foreach ($topics as $topic) {
            if (str_contains($all, $topic)) {
                $found[] = $topic;
            }
        }

        // Keep a few key nouns from the latest long user ask
        if ($found === []) {
            return '';
        }

        return implode('/', array_slice(array_unique($found), 0, 3));
    }

    protected function isBadAiReply(string $reply, Settings $settings): bool
    {
        return $this->isObviouslyBrokenAi($reply);
    }

    /**
     * Reject IT website-sales scripts when this business is not an IT agency.
     */
    protected function isOffCategoryScript(string $reply, Settings $settings): bool
    {
        $family = $this->categoryFamily($this->resolveBusinessCategory($settings));
        if ($family === 'it') {
            return false;
        }

        $lower = mb_strtolower($reply);

        // Non-IT businesses should never ask website-funnel questions
        if (preg_match('/(rough budget|approximate budget|kaunse pages|required pages|website bana|landing page|kitne pages)/u', $lower)) {
            return true;
        }

        return false;
    }

    /**
     * Only reject clearly broken AI output — do not reject normal sales WhatsApp replies.
     */
    protected function isObviouslyBrokenAi(string $reply): bool
    {
        $lower = mb_strtolower(trim($reply));
        if ($lower === '' || mb_strlen($lower) < 2) {
            return true;
        }

        $bad = [
            'i am an ai',
            'as an ai',
            'as a language model',
            'i cannot assist',
            'i can\'t assist',
            'got your message',
            'budget around your budget',
            '1) exact',
            '2) budget',
            'please share your approximate',
        ];

        foreach ($bad as $phrase) {
            if (str_contains($lower, $phrase)) {
                return true;
            }
        }

        // Reject robotic numbered sales scripts
        if (preg_match('/\b1\)\s*.+\b2\)\s*/u', $reply)) {
            return true;
        }

        if (mb_strlen($reply) > 1000) {
            return true;
        }

        return false;
    }

    protected function isTooSimilar(string $a, string $b): bool
    {
        $normalize = function (string $text): string {
            $text = mb_strtolower($text);
            $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text) ?: '';
            $text = preg_replace('/\s+/', ' ', $text) ?: '';

            return trim($text);
        };

        $a = $normalize($a);
        $b = $normalize($b);
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }

        similar_text($a, $b, $percent);

        return $percent >= 70;
    }

    protected function byStyle(string $style, array $variants): string
    {
        return $variants[$style] ?? $variants['hinglish'] ?? $variants['english'];
    }

    protected function tokens(string $text): array
    {
        $text = mb_strtolower($text);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $text) ?: [];

        return array_values(array_filter($parts, fn ($t) => $t !== ''));
    }

    protected function sanitizeReply(string $reply): string
    {
        $reply = trim($reply);
        $reply = preg_replace('/^(assistant|ai|bot)\s*:\s*/i', '', $reply) ?: $reply;
        $reply = preg_replace('/^["“]|["”]$/u', '', $reply) ?: $reply;
        // Strip markdown images: ![alt](url)
        $reply = preg_replace('/!\[.*?\]\([^\)]+\)/u', '', $reply) ?: $reply;
        // Strip hallucinated image URLs (imgur, unsplash, placeholder)
        $reply = preg_replace('/https?:\/\/(?:i\.)?imgur\.com\/\S+/iu', '', $reply) ?: $reply;
        $reply = preg_replace('/https?:\/\/(?:images\.unsplash\.com|via\.placeholder\.com)\/\S+/iu', '', $reply) ?: $reply;
        $reply = preg_replace("/[ \t]+/", ' ', $reply) ?: $reply;
        $reply = preg_replace("/\n{3,}/", "\n\n", $reply) ?: $reply;

        $reply = $this->trimIncompleteTail($reply);

        return Str::limit(trim($reply), 900, '');
    }

    /**
     * Prevents mid-sentence or mid-word cutoffs (e.g. "- फॉर्मल प" or "- राजपूताना")
     */
    protected function trimIncompleteTail(string $text): string
    {
        $trimmed = trim($text);
        if ($trimmed === '') {
            return '';
        }

        $lines = explode("\n", $trimmed);
        if (count($lines) > 1) {
            $lastLine = trim(end($lines));
            // If the last line is a dangling bullet item lacking sentence punctuation
            if (preg_match('/^[-•*]\s*[^\.!\?\|।\x{1F300}-\x{1F9FF}]+$/u', $lastLine) && mb_strlen($lastLine) < 35) {
                array_pop($lines);
                return trim(implode("\n", $lines));
            }
        }

        return $trimmed;
    }

    /**
     * Check if user has permission to use the WhatsApp Catalog module.
     */
    protected function hasCatalogPermission(?int $userId): bool
    {
        if (!$userId || $userId <= 0) {
            return false;
        }

        $user = \App\Models\User::find($userId);
        if (!$user) {
            return false;
        }

        return $user->hasPermission('catalog');
    }

    /**
     * Determine if customer message is explicitly shopping/browsing for physical catalog products,
     * or naming a specific product from the store catalog.
     * Prevents service inquiries (app/web development, house broker, consulting, etc.) from falsely triggering catalog cards.
     */
    protected function hasExplicitCatalogOrShoppingIntent(string $text, ?Settings $settings = null, ?int $userId = null): bool
    {
        $effectiveUserId = $userId ?: (int) ($settings->userId ?? 0);
        if (!$this->hasCatalogPermission($effectiveUserId)) {
            return false;
        }

        $lower = mb_strtolower(trim($text));
        if ($lower === '') {
            return false;
        }

        // 1. Explicit SKU or referred product context from Meta catalog
        if (preg_match('/(?:\[Product Inquiry:\s*#?|#)([A-Za-z0-9_-]{2,30})/iu', $text)) {
            return true;
        }

        // 2. Strict exclusion: Non-retail / service / development / consulting / real estate queries
        $serviceExclusions = [
            '/\b(develop|development|developer|build|building|create|creating|make\s+a\s+website|app|website|software|portal|crm|erp|cms|seo|marketing|maintenance|bug|api|source\s+code|solution)\b/iu',
            '/\b(broker|brokerage|house|flat|apartment|villa|plot|land|rent|tenant|bhk|sqft|square\s+feet|commercial\s+space|property|real\s+estate)\b/iu',
            '/\b(consult|consulting|consultation|legal|ca|audit|accounting|advisory|doctor|patient|appointment|treatment|clinic|hospital)\b/iu',
            '/\b(support|issue|problem|not\s+working|error|help\s+with|meeting|call\s+me|contact\s+number|office|location|address|map)\b/iu',
        ];

        foreach ($serviceExclusions as $exclusion) {
            if (preg_match($exclusion, $lower)) {
                // If they explicitly mention the exact word "catalog" or "catalogue", still allow catalog
                if (!preg_match('/\b(catalog|catalogue|catalogues|catalogs)\b/iu', $lower)) {
                    return false;
                }
            }
        }

        // 3. Check business family: if it's IT, Real Estate, Finance, Clinic, or Consulting, default to NO catalog unless explicitly requested
        if ($settings) {
            $family = $this->categoryFamily($this->resolveBusinessCategory($settings));
            if (in_array($family, ['it', 'realty', 'finance', 'clinic'], true)) {
                if (!preg_match('/\b(catalog|catalogue|catalogues|catalogs|menu|product\s+list|rate\s+list)\b/iu', $lower)) {
                    return false;
                }
            }
        }

        // 4. Explicit catalog browsing words
        if (preg_match('/\b(catalog|catalogue|catalogs|catalogues|brochure|collection|collections|all\s+products?|rate\s+list|price\s+list)\b/iu', $lower)) {
            return true;
        }

        // 5. Explicit product shopping intent (e.g. buying or asking price/photos of physical items)
        if (preg_match('/\b(buy|purchase|order|kharidna|price\s+of|rate\s+of|cost\s+of|photo\s+of|pic\s+of|tasveer|dikhao|bhejo)\b/iu', $lower)) {
            return true;
        }

        // 6. Direct Product Name Match in business catalog
        if ($effectiveUserId > 0) {
            try {
                $catalogService = app(\App\Services\WhatsAppCatalogService::class);
                $catalogId = $catalogService->getDefaultCatalogId($effectiveUserId);
                if ($catalogId) {
                    $search = $catalogService->searchCatalogProducts($text, $catalogId, $effectiveUserId);
                    if (!empty($search['exact_match']) || !empty($search['matches'])) {
                        return true;
                    }
                }
            } catch (\Throwable $e) {
                // Ignore and fall through to false
            }
        }

        return false;
    }

    /**
     * Intelligently handle catalog product queries:
     * - Exact matched product -> Single Product Message (SPM) or rich product card.
     * - Variety / multiple matches -> Multi-Product Message (MPM) or rich variety list.
     * - Catalog browse -> Storefront Catalog message or featured MPM.
     */
    protected function handleCatalogAutoReply(
        WhatsappConversation $whatsappConversation,
        Conversation $legacyConversation,
        string $text,
        ?string $inboundMetaMessageId,
        Settings $settings,
        array $history = []
    ): bool {
        $userId = (int) ($legacyConversation->user_id ?: ($settings->userId ?? 0));
        if (!$this->hasCatalogPermission($userId)) {
            return false;
        }

        // Only proceed if query demonstrates explicit catalog or product shopping intent
        if (!$this->hasExplicitCatalogOrShoppingIntent($text, $settings, $userId)) {
            return false;
        }

        $catalogService = app(\App\Services\WhatsAppCatalogService::class);
        $catalogId = $catalogService->getDefaultCatalogId($userId);

        // If no catalog is linked, let standard conversational AI reply
        if (!$catalogId) {
            return false;
        }

        $search = $catalogService->searchCatalogProducts($text, $catalogId, $userId);

        $lower = mb_strtolower(trim($text));
        $isFollowUpMediaOrCatalog = (bool) preg_match(
            '/\b(image|images|photo|photos|pic|pics|picture|pictures|tasveer|dikhao|dekho|bhejo|send|share|properly|wrong|galat|link|de do|details?|price|rate|cost|chahiye|batao|kya hai)\b/iu',
            $lower
        );

        // If this is a follow-up query explicitly asking for image / photos / details of previously discussed product:
        if ($isFollowUpMediaOrCatalog && empty($search['exact_match']) && !empty($history)) {
            $contextProduct = $this->findContextualProductFromHistory($history, $catalogService, $catalogId, $userId);
            if ($contextProduct) {
                $search['exact_match'] = $contextProduct;
                $search['matches'] = [$contextProduct];
                $search['is_variety_query'] = false;
                $search['is_catalog_query'] = false;
                $search['search_term'] = $contextProduct['name'] ?? 'Product';
            }
        }

        // If no products matched and not a catalog browse query, return false to let standard AI reply
        if (empty($search['matches']) && empty($search['exact_match']) && empty($search['is_catalog_query'])) {
            return false;
        }

        $toPhone = (string) $legacyConversation->phone_number;
        $bizName = $settings->business_name ?? 'our store';

        // ---------------------------------------------------------------------
        // Case 1: EXACT MATCH / SPECIFIC PRODUCT (User gave a product name or 1 item matched)
        // Evaluated FIRST so specific product requests take absolute priority over generic catalog browsing.
        // ---------------------------------------------------------------------
        $isExplicitVarietyOrBrowse = (bool) preg_match(
            '/\b(catalog|catalogue|catalogs|catalogues|variety|varieties|collection|collections|all\s+products?|options?|different|designs?|types?|list)\b/iu',
            $text
        );

        $hasExactProduct = !empty($search['exact_match']);
        $hasSingleMatch = count($search['matches']) === 1;

        if (($hasExactProduct && !$isExplicitVarietyOrBrowse) || ($hasSingleMatch && !$isExplicitVarietyOrBrowse)) {
            $product = $search['exact_match'] ?: $search['matches'][0];
            $retailerId = (string) ($product['retailer_id'] ?? '');
            $prodName = $product['name'] ?: ($retailerId !== '' ? "Product #{$retailerId}" : 'Product');
            $priceStr = $product['formatted_price'] ?: (!empty($product['price']) ? ($product['currency'] . ' ' . number_format($product['price'], 2)) : '');
            $priceLine = $priceStr ? "\n💰 Price: {$priceStr}" : '';
            $availability = !empty($product['availability']) ? "\n📦 Status: " . ucfirst(str_replace('_', ' ', $product['availability'])) : '';
            $desc = !empty($product['description']) ? "\n\n" . trim(Str::limit($product['description'], 300)) : '';

            // 1. Try sending WhatsApp's native interactive Single Product Message (SPM)
            if ($retailerId !== '') {
                try {
                    $spmRes = $catalogService->sendSingleProductMessage(
                        $toPhone,
                        $retailerId,
                        $catalogId,
                        "Here are the details for *{$prodName}*. Tap 'View' to see photos, pricing, and order directly on WhatsApp.",
                        $bizName,
                        $userId,
                        $legacyConversation->id,
                        true // isAutoAi
                    );

                    if (!empty($spmRes['success'])) {
                        Log::info('Auto AI sent Single Product Message (SPM)', [
                            'retailer_id' => $retailerId,
                            'conversation_id' => $legacyConversation->id,
                        ]);
                        return true;
                    }
                } catch (\Throwable $spmEx) {
                    Log::warning('Auto AI SPM dispatch failed: ' . $spmEx->getMessage());
                }
            }

            // 2. Safe Fallback: Rich media product card with image URL
            $replyText = "🛍️ *{$prodName}*{$priceLine}{$availability}{$desc}\n\nWould you like to place an order or know more details? Our team is here to assist you! 🙏";
            $this->sendAutoAiMessage(
                $whatsappConversation,
                $legacyConversation,
                $replyText,
                $inboundMetaMessageId,
                'product_catalog_exact_match',
                $product['image_url'] ?? null
            );
            return true;
        }

        // ---------------------------------------------------------------------
        // Case 2: VARIETY / MULTIPLE PRODUCTS (User asked for variety, category, or multiple items matched)
        // ---------------------------------------------------------------------
        if (!empty($search['matches']) && (count($search['matches']) >= 2 || $search['is_variety_query'])) {
            $realMatches = array_values(array_filter($search['matches'], fn ($p) => !$this->isDummyProduct($p)));
            $effectiveMatches = !empty($realMatches) ? $realMatches : $search['matches'];
            $matches = array_slice($effectiveMatches, 0, 10);
            $rawTerm = !empty($search['search_term']) ? trim($search['search_term']) : '';
            $searchTerm = ($rawTerm !== '' && $rawTerm !== 'variety' && $rawTerm !== 'catalog') ? ucwords($rawTerm) : 'Featured';

            // Prepare sections for Multi-Product Message (MPM)
            $productItems = [];
            foreach ($matches as $p) {
                if (!empty($p['retailer_id'])) {
                    $productItems[] = ['product_retailer_id' => (string) $p['retailer_id']];
                }
            }

            $sectionTitle = Str::limit($searchTerm . ' Collection', 24, '');
            $headerTitle = Str::limit($searchTerm . ' Varieties', 60, '');
            $bodyText = "We have " . count($productItems) . " varieties available! Tap 'View items' below to browse styles, check prices, and add directly to your WhatsApp cart.";

            // 1. Try sending WhatsApp's native interactive Multi-Product Message (MPM)
            if (!empty($productItems)) {
                try {
                    $sections = [
                        [
                            'title' => $sectionTitle,
                            'product_items' => $productItems
                        ]
                    ];

                    $mpmRes = $catalogService->sendMultiProductMessage(
                        $toPhone,
                        $sections,
                        $catalogId,
                        $headerTitle,
                        $bodyText,
                        $bizName,
                        $userId,
                        $legacyConversation->id,
                        true // isAutoAi
                    );

                    if (!empty($mpmRes['success'])) {
                        Log::info('Auto AI sent Multi-Product Message (MPM)', [
                            'count' => count($productItems),
                            'conversation_id' => $legacyConversation->id,
                        ]);
                        return true;
                    }
                } catch (\Throwable $mpmEx) {
                    Log::warning('Auto AI MPM dispatch failed: ' . $mpmEx->getMessage());
                }
            }

            // 2. Safe Fallback: Rich variety text list with top product image
            $lines = ["🛍️ *Available {$searchTerm} Varieties:*\n"];
            $topImage = null;
            foreach (array_slice($matches, 0, 6) as $idx => $p) {
                if (!$topImage && !empty($p['image_url'])) {
                    $topImage = $p['image_url'];
                }
                $pName = $p['name'] ?? 'Product';
                $pPrice = $p['formatted_price'] ?? '';
                $pSku = !empty($p['retailer_id']) ? " (#{$p['retailer_id']})" : '';
                $lines[] = ($idx + 1) . ". *{$pName}*{$pSku}" . ($pPrice ? " — {$pPrice}" : '');
                if (!empty($p['description']) && !str_contains(mb_strtolower($p['description']), 'reiciendis')) {
                    $lines[] = "   " . Str::limit(trim($p['description']), 80);
                }
            }
            $lines[] = "\nReply with the product name or #SKU to view full details or place an order! 🙏";
            $fallbackReply = implode("\n", $lines);

            $this->sendAutoAiMessage(
                $whatsappConversation,
                $legacyConversation,
                $fallbackReply,
                $inboundMetaMessageId,
                'product_catalog_variety',
                $topImage
            );
            return true;
        }

        // ---------------------------------------------------------------------
        // Case 3: CATALOG BROWSE (User asked for "catalog", "catalogue", "menu", "store", "shop" with no specific product)
        // ---------------------------------------------------------------------
        if (!empty($search['is_catalog_query'])) {
            // 1. First try Storefront Catalog Message
            try {
                $catRes = $catalogService->sendCatalogMessage(
                    $toPhone,
                    "Explore our full product catalog and latest collections directly inside WhatsApp! Tap the button below to browse.",
                    $bizName,
                    null,
                    $userId,
                    $legacyConversation->id,
                    true // isAutoAi
                );

                if (!empty($catRes['success'])) {
                    Log::info('Auto AI sent Storefront Catalog Message', [
                        'conversation_id' => $legacyConversation->id,
                    ]);
                    return true;
                }
            } catch (\Throwable $catEx) {
                Log::warning('Auto AI Catalog Message dispatch failed: ' . $catEx->getMessage());
            }

            // 2. Fallback: If products exist, send MPM with featured items
            $allProds = $catalogService->getCachedCatalogProducts($catalogId, $userId);
            $realProds = array_values(array_filter($allProds, fn ($p) => !$this->isDummyProduct($p)));
            $effectiveProds = !empty($realProds) ? $realProds : $allProds;

            if (!empty($effectiveProds)) {
                $topItems = [];
                foreach (array_slice($effectiveProds, 0, 10) as $p) {
                    if (!empty($p['retailer_id'])) {
                        $topItems[] = ['product_retailer_id' => (string) $p['retailer_id']];
                    }
                }
                if (!empty($topItems)) {
                    try {
                        $mpmRes = $catalogService->sendMultiProductMessage(
                            $toPhone,
                            [['title' => 'Featured Collection', 'product_items' => $topItems]],
                            $catalogId,
                            'Store Catalog',
                            "Welcome to {$bizName}! Tap 'View items' below to browse our collection and add to cart.",
                            $bizName,
                            $userId,
                            $legacyConversation->id,
                            true // isAutoAi
                        );
                        if (!empty($mpmRes['success'])) {
                            return true;
                        }
                    } catch (\Throwable $t) {
                        // ignore and proceed to text intro
                    }
                }
            }

            // 3. Secondary fallback: Send welcoming text with prompt
            $this->sendAutoAiMessage(
                $whatsappConversation,
                $legacyConversation,
                "Welcome to {$bizName}! 🛍️\n\nOur full collection is available in our WhatsApp Catalog. Tell us what you are looking for (e.g., Kurtas, Sherwanis, Shirts, or specific styles), and we will show you the exact options right away! 🙏",
                $inboundMetaMessageId,
                'catalog_intro'
            );
            return true;
        }

        return false;
    }

    /**
     * Inspect recent conversation turns to identify the specific catalog product being discussed.
     * Extracts product names from bold terms (*...*), previous bot recommendations, or customer inquiries.
     */
    protected function findContextualProductFromHistory(
        array $history,
        WhatsAppCatalogService $catalogService,
        ?string $catalogId,
        int $userId
    ): ?array {
        if (empty($history) || !$catalogId) {
            return null;
        }

        // Scan recent 10 messages from latest to oldest
        $recentTurns = array_slice($history, -10);
        $recentTurns = array_reverse($recentTurns);

        // Pass 1: Look for highlighted/bold product names like *हंटिंग कुर्ता* or *Hunting Kurta*
        foreach ($recentTurns as $msg) {
            $content = (string) ($msg['content'] ?? '');
            if ($content === '') {
                continue;
            }

            if (preg_match_all('/\*([^\*\n]{2,50})\*/u', $content, $m)) {
                foreach ($m[1] as $candidate) {
                    $candidate = trim($candidate);
                    // Skip generic status/pricing/order words
                    if (preg_match('/^(note|price|order|total|status|delivery|shipping|size|color|colour|offer|discount)[\s:]*$/iu', $candidate)) {
                        continue;
                    }
                    $candidateSearch = $catalogService->searchCatalogProducts($candidate, $catalogId, $userId);
                    if (!empty($candidateSearch['exact_match'])) {
                        return $candidateSearch['exact_match'];
                    }
                    if (!empty($candidateSearch['matches']) && count($candidateSearch['matches']) === 1) {
                        return $candidateSearch['matches'][0];
                    }
                }
            }

            // Also check for explicit SKU mentions: #SKU or [Product Inquiry: #SKU]
            if (preg_match('/(?:\[Product Inquiry:\s*#?|#)([A-Za-z0-9_-]{2,30})/iu', $content, $skuM)) {
                $skuProd = $catalogService->resolveProductDetails($skuM[1], $catalogId, $userId);
                if ($skuProd) {
                    return $skuProd;
                }
            }
        }

        // Pass 2: Check cached catalog products against recent conversation text
        try {
            $allProducts = $catalogService->getCachedCatalogProducts($catalogId, $userId);
            $realProducts = array_values(array_filter($allProducts, fn ($p) => !$this->isDummyProduct($p)));
            $effective = !empty($realProducts) ? $realProducts : $allProducts;

            foreach ($recentTurns as $msg) {
                $contentLower = mb_strtolower((string) ($msg['content'] ?? ''));
                if ($contentLower === '') {
                    continue;
                }

                foreach ($effective as $p) {
                    $pName = mb_strtolower($p['name'] ?? '');
                    if (mb_strlen($pName) >= 3 && str_contains($contentLower, $pName)) {
                        return $p;
                    }

                    // Check synonyms of this product
                    $syns = $catalogService->resolveVernacularSynonyms($pName, $userId);
                    foreach ($syns as $syn) {
                        $syn = mb_strtolower(trim($syn));
                        if (mb_strlen($syn) >= 3 && str_contains($contentLower, $syn)) {
                            return $p;
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // Graceful fallback
        }

        return null;
    }

    /**
     * Compact summary of available catalog products to ground AI answers.
     */
    protected function resolveCatalogKnowledgeSummary(Settings $settings, ?string $customerMessage = null): string
    {
        try {
            $userId = (int) ($settings->userId ?? 0);
            if (!$this->hasCatalogPermission($userId)) {
                return '';
            }

            $catalogService = app(\App\Services\WhatsAppCatalogService::class);
            $catalogId = $catalogService->getDefaultCatalogId($userId);
            if (!$catalogId) {
                return '';
            }

            $products = $catalogService->getCachedCatalogProducts($catalogId, $userId);
            if (empty($products)) {
                return '';
            }

            // Filter out placeholder Latin dummy products
            $realProducts = array_values(array_filter($products, fn ($p) => !$this->isDummyProduct($p)));
            $effectiveProducts = !empty($realProducts) ? $realProducts : $products;

            $items = [];
            $seenIds = [];

            // 1. If customer message is provided, dynamically search for matching catalog products across the entire catalog
            if ($customerMessage !== null && trim($customerMessage) !== '') {
                try {
                    $search = $catalogService->searchCatalogProducts($customerMessage, $catalogId, $userId);
                    $matchedList = [];
                    if (!empty($search['exact_match'])) {
                        $matchedList[] = $search['exact_match'];
                    }
                    if (!empty($search['matches']) && is_array($search['matches'])) {
                        foreach ($search['matches'] as $m) {
                            $matchedList[] = $m;
                        }
                    }

                    foreach ($matchedList as $p) {
                        $pKey = (string) ($p['retailer_id'] ?? $p['id'] ?? $p['name'] ?? '');
                        if ($pKey !== '' && !isset($seenIds[$pKey])) {
                            $seenIds[$pKey] = true;
                            $name = $p['name'] ?? '';
                            $price = $p['formatted_price'] ?? '';
                            $desc = !empty($p['description']) && !str_contains(mb_strtolower($p['description']), 'reiciendis')
                                ? ' - ' . Str::limit(trim($p['description']), 100)
                                : '';
                            if ($name !== '') {
                                $items[] = "• " . $name . ($price ? " ({$price})" : '') . $desc . " [Matches customer inquiry]";
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    // Ignore search errors and fallback to general products
                }
            }

            // 2. Add general catalog products up to 25 items so AI knows store catalog breadth
            foreach ($effectiveProducts as $p) {
                if (count($items) >= 25) {
                    break;
                }
                $pKey = (string) ($p['retailer_id'] ?? $p['id'] ?? $p['name'] ?? '');
                if ($pKey !== '' && isset($seenIds[$pKey])) {
                    continue;
                }
                if ($pKey !== '') {
                    $seenIds[$pKey] = true;
                }
                $name = $p['name'] ?? '';
                $price = $p['formatted_price'] ?? '';
                if ($name !== '') {
                    $items[] = "• " . $name . ($price ? " ({$price})" : '');
                }
            }

            return implode("\n", $items);
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Check if product is an auto-generated or dummy placeholder.
     */
    protected function isDummyProduct(array $p): bool
    {
        $name = mb_strtolower($p['name'] ?? '');
        $dummyWords = ['reiciendis', 'voluptate', 'cupiditate', 'lorem', 'ipsum', 'dolor', 'consectetur', 'adipiscing'];
        foreach ($dummyWords as $word) {
            if (str_contains($name, $word)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Intelligently handle store address, location, and map queries:
     * - Detects if customer is asking for map, location, address, directions, or store visit.
     * - Generates clean store address with real Google Maps search & navigation link.
     * - If coordinates (latitude, longitude) exist, sends native WhatsApp interactive location pin.
     */
    protected function handleLocationAutoReply(
        WhatsappConversation $whatsappConversation,
        Conversation $legacyConversation,
        string $text,
        ?string $inboundMetaMessageId,
        Settings $settings
    ): bool {
        if (! $this->isLocationQuery($text)) {
            return false;
        }

        $address = trim((string) ($settings->business_address ?: ''));
        $coverage = trim((string) ($settings->location ?: ''));
        $bizName = trim((string) ($settings->business_name ?: 'our store'));
        $hours = trim((string) ($settings->business_hours ?: ''));
        $phone = trim((string) ($settings->support_phone ?: ''));

        // If neither address nor location is configured, let standard AI reply
        if ($address === '' && $coverage === '') {
            return false;
        }

        $fullAddress = $address !== '' ? $address : $coverage;

        // Check if fullAddress contains explicit latitude and longitude coordinates: e.g. "26.9124, 75.7873"
        $lat = null;
        $lng = null;
        if (preg_match('/(-?\d{1,2}\.\d{3,8})\s*,\s*(-?\d{1,3}\.\d{3,8})/', $fullAddress, $coordM)) {
            $lat = (float) $coordM[1];
            $lng = (float) $coordM[2];
        }

        // Try sending native WhatsApp Location Message if coordinates are present
        if ($lat !== null && $lng !== null) {
            try {
                $err = null;
                $locMsg = $this->inboxService->sendLocationMessage(
                    $whatsappConversation,
                    $lat,
                    $lng,
                    $bizName,
                    $fullAddress,
                    null,
                    $err
                );
                if ($locMsg) {
                    Log::info('Auto AI sent native WhatsApp Location Message', [
                        'conversation_id' => $legacyConversation->id,
                        'lat' => $lat,
                        'lng' => $lng,
                    ]);
                    return true;
                }
            } catch (\Throwable $locEx) {
                Log::warning('Auto AI sendLocationMessage failed: ' . $locEx->getMessage());
            }
        }

        // Format clean WhatsApp text message with Google Maps navigation link
        $encodedQuery = urlencode(($bizName !== '' ? $bizName . ', ' : '') . $fullAddress);
        $mapUrl = "https://www.google.com/maps/search/?api=1&query={$encodedQuery}";

        $lines = [];
        $lines[] = "📍 *{$bizName}*";
        $lines[] = "";
        $lines[] = "🏢 *Address:*";
        $lines[] = $fullAddress;
        $lines[] = "";
        $lines[] = "🗺️ *Google Maps Location:*";
        $lines[] = $mapUrl;

        if ($hours !== '') {
            $lines[] = "";
            $lines[] = "🕒 *Timings:* {$hours}";
        }
        if ($phone !== '') {
            $lines[] = "📞 *Contact:* {$phone}";
        }
        $lines[] = "";
        $lines[] = "You are warmly welcome to visit us! Please let us know if you need any help with directions. 🙏";

        $replyText = implode("\n", $lines);

        $this->sendAutoAiMessage(
            $whatsappConversation,
            $legacyConversation,
            $replyText,
            $inboundMetaMessageId,
            'store_location_map'
        );

        return true;
    }

    /**
     * Detect if message is asking for map, location, address, directions, or store visit.
     */
    protected function isLocationQuery(string $text): bool
    {
        $lower = mb_strtolower(trim($text));

        $patterns = [
            '/\b(map|maps|google\s*maps?|live\s*location|current\s*location|pin\s*location)\b/iu',
            '/\b(address|location|direction|directions|landmark|navigation)\b.*\b(bhejo|send|share|give|de do|dikhao|batao|chahiye|please|link)\b/iu',
            '/\b(bhejo|send|share|give|de do|dikhao|batao)\b.*\b(address|location|map|maps|direction|directions)\b/iu',
            '/\b(kahan|kidhar|where)\b.*\b(hai|ho|located|situated|store|shop|showroom|office|outlet|dukaan|factory)\b/iu',
            '/\b(store|shop|showroom|office|outlet|dukaan)\b.*\b(kahan|kidhar|where|address|location|map)\b/iu',
            '/^(location|map|address|google map|dukaan kahan hai|showroom kahan hai|store address)[\s!.\?]*$/iu',
            '/\b(how\s+to\s+reach|reach\s+your\s+store|visit\s+your\s+shop|store\s+visit|showroom\s+visit)\b/iu',
            '/\b(map\s+location|location\s+map|store\s+location|shop\s+location|showroom\s+location|office\s+location)\b/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower)) {
                return true;
            }
        }

        return false;
    }

    /**
     * High-priority smart FAQ and Knowledge Base auto-reply.
     * When incoming customer query matches an active FAQ or its keywords with high confidence,
     * immediately replies with the verified business answer.
     */
    protected function handleFaqAutoReply(
        WhatsappConversation $whatsappConversation,
        Conversation $legacyConversation,
        string $text,
        ?string $inboundMetaMessageId,
        Settings $settings
    ): bool {
        $match = $this->findMatchingFaq($text, $settings, 55.0);
        if (! $match || empty($match['answer'])) {
            return false;
        }

        $faq = $match['faq'] ?? [];
        $answer = trim((string) $match['answer']);
        if ($answer === '') {
            return false;
        }

        Log::info('Auto AI matched verified FAQ / Knowledge Base item', [
            'conversation_id' => $legacyConversation->id,
            'user_id' => $settings->userId,
            'question' => $faq['question'] ?? '',
            'score' => $match['score'] ?? 0,
            'type' => $match['type'] ?? '',
        ]);

        $this->sendAutoAiMessage(
            $whatsappConversation,
            $legacyConversation,
            $answer,
            $inboundMetaMessageId,
            'faq_knowledge_response'
        );

        return true;
    }

    /**
     * Load all active FAQs for this business (Knowledge Base table + Settings parsed FAQs).
     */
    protected function loadAllUserFaqs(Settings $settings): array
    {
        $faqs = [];
        $seen = [];

        // 1. Database KnowledgeBase items for this user
        try {
            $ownerId = (int) ($settings->userId ?: 0);
            if ($ownerId > 0) {
                $kbItems = \App\Models\KnowledgeBase::where('user_id', $ownerId)
                    ->where('is_published', true)
                    ->where('is_enabled', true)
                    ->get();
                foreach ($kbItems as $kb) {
                    $q = trim((string) $kb->question);
                    $a = trim((string) $kb->answer);
                    $qKey = mb_strtolower($q);
                    if ($q !== '' && $a !== '' && !isset($seen[$qKey])) {
                        $seen[$qKey] = true;
                        $faqs[] = [
                            'question' => $q,
                            'answer' => $a,
                            'category' => (string) ($kb->category ?: 'General'),
                            'keywords' => is_array($kb->keywords) ? $kb->keywords : [],
                            'source' => 'knowledge_base_db',
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // Table or DB fallback safe
        }

        // 2. Settings JSON / text FAQs (if configured)
        try {
            $settingFaqs = $settings->getParsedFaqs();
            foreach ($settingFaqs as $sf) {
                $q = trim((string) ($sf['question'] ?? ''));
                $a = trim((string) ($sf['answer'] ?? ''));
                $qKey = mb_strtolower($q);
                if ($q !== '' && $a !== '' && !isset($seen[$qKey])) {
                    $seen[$qKey] = true;
                    $faqs[] = [
                        'question' => $q,
                        'answer' => $a,
                        'category' => (string) ($sf['category'] ?? 'General'),
                        'keywords' => is_array($sf['keywords'] ?? null) ? $sf['keywords'] : [],
                        'source' => 'settings_faqs',
                    ];
                }
            }
        } catch (\Throwable $e) {
        }

        return $faqs;
    }

    /**
     * Tokenize text into normalized keywords (ignoring common conversational stop words).
     */
    protected function extractFaqTokens(string $text): array
    {
        $stopWords = [
            'a', 'an', 'the', 'is', 'are', 'was', 'were', 'do', 'does', 'did', 'you', 'we', 'i',
            'in', 'on', 'at', 'to', 'for', 'of', 'and', 'or', 'your', 'our', 'my', 'can', 'could',
            'me', 'us', 'it', 'its', 'this', 'that', 'these', 'those', 'please', 'tell', 'batao',
            'kya', 'hai', 'hain', 'ho', 'ko', 'se', 'me', 'mein', 'par', 'pe', 'ke', 'ki', 'ka',
            'mil', 'sakta', 'sakte', 'chahiye', 'karna', 'karo', 'any', 'some', 'give', 'share'
        ];

        $clean = mb_strtolower(trim($text));
        $clean = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $clean);
        $words = preg_split('/\s+/u', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $tokens = [];
        foreach ($words as $w) {
            if (mb_strlen($w) >= 2 && !in_array($w, $stopWords, true)) {
                $tokens[] = $w;
            }
        }

        return array_values(array_unique($tokens));
    }

    /**
     * Find best matching FAQ for a customer query against active business FAQs.
     */
    protected function findMatchingFaq(string $query, Settings $settings, float $threshold = 55.0): ?array
    {
        $qTrim = trim($query);
        if ($qTrim === '') {
            return null;
        }

        $qClean = mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]+/u', '', $qTrim)));
        if ($qClean === '') {
            return null;
        }

        $faqs = $this->loadAllUserFaqs($settings);
        if (empty($faqs)) {
            return null;
        }

        $qTokens = $this->extractFaqTokens($qTrim);
        $bestScore = 0.0;
        $bestFaq = null;
        $bestType = null;

        foreach ($faqs as $faq) {
            $fQ = trim((string) $faq['question']);
            $fQClean = mb_strtolower(trim(preg_replace('/[^\p{L}\p{N}\s]+/u', '', $fQ)));
            if ($fQClean === '') {
                continue;
            }

            // 1. Exact match (100 points)
            if ($qClean === $fQClean) {
                return [
                    'faq' => $faq,
                    'score' => 100.0,
                    'type' => 'exact_match',
                    'answer' => $faq['answer'],
                ];
            }

            // 2. Substring containment (for meaningful phrases >= 12 chars)
            if (mb_strlen($qClean) >= 12 && mb_strlen($fQClean) >= 12) {
                if (str_contains($qClean, $fQClean) || str_contains($fQClean, $qClean)) {
                    $score = 95.0;
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestFaq = $faq;
                        $bestType = 'substring_match';
                    }
                }
            }

            // 3. Token overlap with FAQ Question
            $fTokens = $this->extractFaqTokens($fQ);
            $matchedTokens = array_intersect($qTokens, $fTokens);
            $countMatched = count($matchedTokens);
            $countTotal = count($fTokens);

            if ($countTotal > 0 && $countMatched > 0) {
                $ratio = $countMatched / $countTotal;
                $qRatio = !empty($qTokens) ? ($countMatched / count($qTokens)) : 0;
                $tokenScore = ($ratio * 60.0) + ($qRatio * 30.0);

                if ($countMatched === $countTotal) {
                    $tokenScore += 15.0;
                }

                if ($tokenScore > $bestScore) {
                    $bestScore = $tokenScore;
                    $bestFaq = $faq;
                    $bestType = 'token_overlap';
                }
            }

            // 4. Keyword tags matching
            if (!empty($faq['keywords']) && is_array($faq['keywords'])) {
                $kwMatches = 0;
                foreach ($faq['keywords'] as $kw) {
                    $kwClean = mb_strtolower(trim((string) $kw));
                    if ($kwClean === '') {
                        continue;
                    }
                    if (str_contains($qClean, $kwClean)) {
                        $kwMatches++;
                    }
                }
                if ($kwMatches > 0) {
                    $kwScore = min(88.0, 50.0 + ($kwMatches * 20.0));
                    if ($kwScore > $bestScore) {
                        $bestScore = $kwScore;
                        $bestFaq = $faq;
                        $bestType = 'keyword_tag_match';
                    }
                }
            }
        }

        if ($bestScore >= $threshold && $bestFaq !== null) {
            return [
                'faq' => $bestFaq,
                'score' => round($bestScore, 1),
                'type' => $bestType,
                'answer' => $bestFaq['answer'],
            ];
        }

        return null;
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($phone) === 10 && preg_match('/^[6-9]/', $phone)) {
            return '91' . $phone;
        }
        return $phone;
    }
}

