import { NextResponse } from 'next/server';
import { defaultWhatsAppClient } from '@/lib/whatsapp/api';
import { getServiceSupabase, isServerSupabaseConfigured } from '@/lib/supabase/server';
import { mockTemplates } from '@/lib/mock-data';

export async function GET() {
  const supabase = getServiceSupabase();
  const isConfigured = isServerSupabaseConfigured();

  // Try fetching approved templates directly from Meta Cloud API
  try {
    const metaTemplates = await defaultWhatsAppClient.fetchMetaTemplates();
    if (metaTemplates && metaTemplates.length > 0) {
      const formatted = metaTemplates.map((t: any) => ({
        id: t.id,
        name: t.name,
        language: t.language,
        category: t.category,
        status: t.status,
        body_text:
          t.components?.find((c: any) => c.type === 'BODY')?.text || '',
        header_text:
          t.components?.find((c: any) => c.type === 'HEADER')?.text || null,
        footer_text:
          t.components?.find((c: any) => c.type === 'FOOTER')?.text || null,
        variable_count: 0,
        components: t.components,
      }));

      // Optionally cache them in Supabase
      if (isConfigured) {
        await supabase
          .from('whatsapp_templates')
          .upsert(formatted, { onConflict: 'name,language' });
      }

      return NextResponse.json({
        success: true,
        source: 'meta',
        templates: formatted,
      });
    }
  } catch (metaErr) {
    console.warn('Could not sync directly from Meta API:', metaErr);
  }

  // Fallback to Supabase database
  if (isConfigured) {
    try {
      const { data, error } = await supabase
        .from('whatsapp_templates')
        .select('*')
        .order('created_at', { ascending: false });

      if (!error && data && data.length > 0) {
        return NextResponse.json({
          success: true,
          source: 'supabase',
          templates: data,
        });
      }
    } catch {}
  }

  // Fallback to mock templates
  return NextResponse.json({
    success: true,
    source: 'mock',
    templates: mockTemplates,
  });
}
