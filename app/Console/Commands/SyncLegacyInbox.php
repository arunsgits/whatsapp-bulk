<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Services\WhatsAppInboxService;
use Illuminate\Console\Command;

class SyncLegacyInbox extends Command
{
    protected $signature = 'whatsapp:sync-legacy-inbox';

    protected $description = 'Copy legacy conversations/messages into whatsapp_conversations / whatsapp_messages';

    public function handle(WhatsAppInboxService $inboxService): int
    {
        $legacyConversations = Conversation::with('messages')->orderBy('id')->get();

        if ($legacyConversations->isEmpty()) {
            $this->info('No legacy conversations found.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($legacyConversations->count());
        $bar->start();

        $syncedMessages = 0;

        foreach ($legacyConversations as $legacy) {
            $conversation = $inboxService->ensureWhatsappConversationForLegacy($legacy);

            foreach ($legacy->messages as $legacyMessage) {
                $inboxService->syncLegacyMessageToWhatsapp($legacyMessage, $conversation);
                $syncedMessages++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Synced {$legacyConversations->count()} conversations and {$syncedMessages} messages.");

        return self::SUCCESS;
    }
}
