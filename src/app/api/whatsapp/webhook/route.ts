import { NextRequest, NextResponse } from 'next/server';
import { getServiceSupabase, isServerSupabaseConfigured } from '@/lib/supabase/server';
import { defaultAutomationEngine } from '@/lib/automation/engine';
import { normalizePhoneNumber } from '@/lib/whatsapp/phone';

/**
 * GET Handler: Meta Webhook Verification Challenge
 */
export async function GET(request: NextRequest) {
  const { searchParams } = new URL(request.url);

  const mode = searchParams.get('hub.mode');
  const token = searchParams.get('hub.verify_token');
  const challenge = searchParams.get('hub.challenge');

  const verifyToken = process.env.WHATSAPP_VERIFY_TOKEN || 'test1234';

  if (mode === 'subscribe' && token === verifyToken) {
    console.log('[Webhook Verification] Success: challenge accepted');
    return new NextResponse(challenge, {
      status: 200,
      headers: { 'Content-Type': 'text/plain' },
    });
  }

  console.warn('[Webhook Verification] Failed verification token mismatch');
  return new NextResponse('Forbidden: Token mismatch', { status: 403 });
}

/**
 * POST Handler: Ingestion of incoming messages and status receipts
 */
export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    const entries = body.entry || [];

    for (const entry of entries) {
      for (const change of entry.changes || []) {
        const value = change.value || {};

        // 1. Process Status Receipts (delivered, read, failed)
        if (value.statuses && Array.isArray(value.statuses)) {
          for (const statusObj of value.statuses) {
            const metaMessageId = statusObj.id;
            const status = statusObj.status; // sent, delivered, read, failed

            if (isConfigured && metaMessageId) {
              await supabase
                .from('messages')
                .update({ status })
                .eq('meta_message_id', metaMessageId);

              await supabase
                .from('campaign_recipients')
                .update({
                  status,
                  ...(status === 'delivered'
                    ? { delivered_at: new Date().toISOString() }
                    : {}),
                  ...(status === 'read'
                    ? { read_at: new Date().toISOString() }
                    : {}),
                })
                .eq('meta_message_id', metaMessageId);
            }
          }
        }

        // 2. Process Inbound Messages
        if (value.messages && Array.isArray(value.messages)) {
          const contacts = value.contacts || [];
          const contactProfile = contacts[0]?.profile?.name;

          for (const msg of value.messages) {
            const rawPhone = msg.from;
            const normalizedPhone = normalizePhoneNumber(rawPhone);
            const messageType = msg.type || 'text';
            const metaMessageId = msg.id;

            let messageText = '';
            let mediaUrl: string | undefined;

            if (messageType === 'text') {
              messageText = msg.text?.body || '';
            } else if (['image', 'video', 'document', 'audio'].includes(messageType)) {
              messageText = msg[messageType]?.caption || `[${messageType.toUpperCase()}]`;
            } else if (messageType === 'button') {
              messageText = msg.button?.text || '[Button]';
            } else if (messageType === 'interactive') {
              messageText =
                msg.interactive?.button_reply?.title ||
                msg.interactive?.list_reply?.title ||
                '[Interactive reply]';
            }

            let conversationId = '';
            let isFirstMessage = false;

            if (isConfigured) {
              // Find or create conversation
              const { data: existingConv } = await supabase
                .from('conversations')
                .select('*')
                .eq('phone_number', normalizedPhone)
                .single();

              if (!existingConv) {
                isFirstMessage = true;
                const { data: newConv } = await supabase
                  .from('conversations')
                  .insert({
                    phone_number: normalizedPhone,
                    contact_name: contactProfile || `Contact ${normalizedPhone}`,
                    unread_count: 1,
                    status: 'open',
                    last_message_text: messageText,
                    last_message_at: new Date().toISOString(),
                  })
                  .select()
                  .single();

                if (newConv) conversationId = newConv.id;
              } else {
                conversationId = existingConv.id;
                await supabase
                  .from('conversations')
                  .update({
                    unread_count: (existingConv.unread_count || 0) + 1,
                    last_message_text: messageText,
                    last_message_at: new Date().toISOString(),
                  })
                  .eq('id', existingConv.id);
              }

              // Store incoming message
              if (conversationId) {
                await supabase.from('messages').insert({
                  conversation_id: conversationId,
                  direction: 'incoming',
                  message_type: messageType,
                  message: messageText,
                  media_url: mediaUrl || null,
                  meta_message_id: metaMessageId,
                  status: 'delivered',
                  sender_type: 'user',
                });
              }
            }

            // 3. Trigger dynamic auto-reply engine asynchronously
            defaultAutomationEngine.processInboundMessage({
              phone: normalizedPhone,
              customerName: contactProfile,
              messageText,
              conversationId,
              isFirstMessage,
            }).catch((err) => {
              console.error('Auto-reply trigger failed:', err);
            });
          }
        }
      }
    }

    return NextResponse.json({ status: 'ok' }, { status: 200 });
  } catch (error: any) {
    console.error('Webhook error:', error);
    return NextResponse.json(
      { error: error.message || 'Error processing webhook' },
      { status: 500 }
    );
  }
}
