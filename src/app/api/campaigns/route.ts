import { NextRequest, NextResponse } from 'next/server';
import { getServiceSupabase, isServerSupabaseConfigured } from '@/lib/supabase/server';
import { mockCampaigns } from '@/lib/mock-data';

export async function GET() {
  const supabase = getServiceSupabase();
  const isConfigured = isServerSupabaseConfigured();

  if (!isConfigured) {
    return NextResponse.json({
      success: true,
      source: 'mock',
      campaigns: mockCampaigns,
    });
  }

  try {
    const { data, error } = await supabase
      .from('campaigns')
      .select('*, template:whatsapp_templates(*)')
      .order('created_at', { ascending: false });

    if (error) throw error;

    return NextResponse.json({
      success: true,
      source: 'supabase',
      campaigns: data && data.length > 0 ? data : mockCampaigns,
    });
  } catch (err: any) {
    return NextResponse.json({
      success: true,
      source: 'fallback',
      campaigns: mockCampaigns,
      error: err.message,
    });
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const {
      name,
      direct_message,
      template_id,
      throttle_delay_sec,
      recipients,
    } = body;

    if (!name || (!direct_message && !template_id)) {
      return NextResponse.json(
        { error: 'Campaign name and message or template are required.' },
        { status: 400 }
      );
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    const campaignData = {
      name,
      direct_message: direct_message || null,
      template_id: template_id || null,
      throttle_delay_sec: throttle_delay_sec || 2.0,
      total_recipients: Array.isArray(recipients) ? recipients.length : 0,
      status: 'draft',
      sent_count: 0,
      failed_count: 0,
      delivered_count: 0,
      read_count: 0,
    };

    if (isConfigured) {
      const { data: campaign, error } = await supabase
        .from('campaigns')
        .insert(campaignData)
        .select()
        .single();

      if (error) throw error;

      if (Array.isArray(recipients) && recipients.length > 0 && campaign) {
        const recipientRows = recipients.map((r: any) => ({
          campaign_id: campaign.id,
          phone: r.phone,
          name: r.name || null,
          variables: r.variables || {},
          status: 'pending',
        }));

        await supabase.from('campaign_recipients').insert(recipientRows);
      }

      return NextResponse.json({ success: true, campaign });
    }

    // Mock fallback response
    const mockCreated = {
      id: `camp-${Date.now()}`,
      ...campaignData,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    return NextResponse.json({ success: true, campaign: mockCreated });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error creating campaign' },
      { status: 500 }
    );
  }
}
