import { NextRequest, NextResponse } from 'next/server';
import { defaultWhatsAppClient } from '@/lib/whatsapp/api';
import { getServiceSupabase, isServerSupabaseConfigured } from '@/lib/supabase/server';
import { normalizePhoneNumber } from '@/lib/whatsapp/phone';

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { phone, message, conversationId, templateName, language, components } =
      body;

    if (!phone) {
      return NextResponse.json(
        { success: false, error: 'Phone number is required.' },
        { status: 400 }
      );
    }

    const normalizedPhone = normalizePhoneNumber(phone);
    let sendResult;

    if (templateName) {
      sendResult = await defaultWhatsAppClient.sendTemplateMessage(
        normalizedPhone,
        templateName,
        language || 'en',
        components || []
      );
    } else {
      if (!message) {
        return NextResponse.json(
          { success: false, error: 'Message text is required.' },
          { status: 400 }
        );
      }
      sendResult = await defaultWhatsAppClient.sendTextMessage(
        normalizedPhone,
        message
      );
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    if (isConfigured && conversationId) {
      await supabase.from('messages').insert({
        conversation_id: conversationId,
        direction: 'outgoing',
        message_type: templateName ? 'template' : 'text',
        message: message || `Template: ${templateName}`,
        meta_message_id: sendResult.messageId || null,
        status: sendResult.success ? 'sent' : 'failed',
        sender_type: 'human_agent',
        error_message: sendResult.error || null,
      });

      await supabase
        .from('conversations')
        .update({
          last_message_text: message || `Template: ${templateName}`,
          last_message_at: new Date().toISOString(),
        })
        .eq('id', conversationId);
    }

    return NextResponse.json(sendResult);
  } catch (err: any) {
    return NextResponse.json(
      { success: false, error: err.message || 'Send error' },
      { status: 500 }
    );
  }
}
