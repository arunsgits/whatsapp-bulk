import { NextResponse } from 'next/server';
import { getServiceSupabase, isServerSupabaseConfigured } from '@/lib/supabase/server';
import { defaultWhatsAppClient } from '@/lib/whatsapp/api';
import { defaultAiResponder } from '@/lib/ai/responder';

export async function GET() {
  const results = {
    supabase: {
      configured: isServerSupabaseConfigured(),
      connected: false,
      message: '',
    },
    whatsapp: {
      configured: defaultWhatsAppClient.isConfigured(),
      connected: false,
      message: '',
    },
    aiBot: {
      configured: defaultAiResponder.isAvailable(),
      connected: false,
      message: '',
    },
  };

  // 1. Test Supabase
  if (results.supabase.configured) {
    try {
      const supabase = getServiceSupabase();
      const { data, error } = await supabase
        .from('whatsapp_configs')
        .select('id')
        .limit(1);

      if (error) {
        results.supabase.message = error.message;
      } else {
        results.supabase.connected = true;
        results.supabase.message = 'Successfully queried Supabase database tables.';
      }
    } catch (err: any) {
      results.supabase.message = err.message || 'Supabase query error';
    }
  } else {
    results.supabase.message =
      'Supabase URL or keys not configured in .env.local. Running in preview mode.';
  }

  // 2. Test WhatsApp Meta Cloud API
  const phoneId = process.env.WHATSAPP_PHONE_NUMBER_ID;
  const token = process.env.WHATSAPP_ACCESS_TOKEN;
  const apiVer = process.env.WHATSAPP_API_VERSION || 'v19.0';

  if (phoneId && token) {
    try {
      const res = await fetch(
        `https://graph.facebook.com/${apiVer}/${phoneId}`,
        {
          headers: { Authorization: `Bearer ${token}` },
        }
      );
      const data = await res.json();
      if (res.ok && data.id) {
        results.whatsapp.connected = true;
        results.whatsapp.message = `Verified Phone ID: ${data.id} (${data.display_phone_number || 'Meta Cloud API'})`;
      } else {
        results.whatsapp.message =
          data.error?.message || 'Meta API credentials rejected.';
      }
    } catch (e: any) {
      results.whatsapp.message = e.message || 'Network error calling Meta API';
    }
  } else {
    results.whatsapp.message =
      'Phone Number ID or Access Token not set in .env.local.';
  }

  // 3. Test AI Bot
  if (results.aiBot.configured) {
    try {
      const testPing = await defaultAiResponder.generateReply(
        'Ping',
        [],
        'Test'
      );
      if (testPing) {
        results.aiBot.connected = true;
        results.aiBot.message = 'Groq AI dynamic responder active and responsive.';
      } else {
        results.aiBot.message = 'AI API returned empty response.';
      }
    } catch (e: any) {
      results.aiBot.message = e.message || 'AI API connection error';
    }
  } else {
    results.aiBot.message = 'AI API key not configured.';
  }

  return NextResponse.json(results);
}
