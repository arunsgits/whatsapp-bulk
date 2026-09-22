import { NextRequest, NextResponse } from 'next/server';
import { getServiceSupabase, isServerSupabaseConfigured } from '@/lib/supabase/server';
import { mockAutomationRules } from '@/lib/mock-data';

export async function GET() {
  const supabase = getServiceSupabase();
  const isConfigured = isServerSupabaseConfigured();

  if (!isConfigured) {
    return NextResponse.json({
      success: true,
      source: 'mock',
      rules: mockAutomationRules,
    });
  }

  try {
    const { data, error } = await supabase
      .from('automation_rules')
      .select('*')
      .order('priority', { ascending: false });

    if (error) throw error;

    return NextResponse.json({
      success: true,
      source: 'supabase',
      rules: data && data.length > 0 ? data : mockAutomationRules,
    });
  } catch (err: any) {
    return NextResponse.json({
      success: true,
      source: 'fallback',
      rules: mockAutomationRules,
      error: err.message,
    });
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const {
      name,
      trigger_type,
      trigger_keywords,
      match_mode,
      response_message,
      priority,
    } = body;

    if (!name || !response_message) {
      return NextResponse.json(
        { error: 'Rule name and response message are required.' },
        { status: 400 }
      );
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    const ruleData = {
      name,
      trigger_type: trigger_type || 'keyword',
      trigger_keywords: trigger_type === 'welcome' ? null : trigger_keywords,
      match_mode: match_mode || 'contains',
      response_type: 'text',
      response_message,
      priority: priority || 50,
      is_active: true,
      execution_count: 0,
    };

    if (isConfigured) {
      const { data, error } = await supabase
        .from('automation_rules')
        .insert(ruleData)
        .select()
        .single();

      if (error) throw error;
      return NextResponse.json({ success: true, rule: data });
    }

    const mockRule = {
      id: `rule-${Date.now()}`,
      ...ruleData,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    return NextResponse.json({ success: true, rule: mockRule });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error creating rule' },
      { status: 500 }
    );
  }
}

export async function PATCH(request: NextRequest) {
  try {
    const { id, is_active, priority, response_message } = await request.json();

    if (!id) {
      return NextResponse.json({ error: 'Rule ID is required.' }, { status: 400 });
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    const updates: any = {};
    if (typeof is_active === 'boolean') updates.is_active = is_active;
    if (typeof priority === 'number') updates.priority = priority;
    if (response_message) updates.response_message = response_message;

    if (isConfigured) {
      const { data, error } = await supabase
        .from('automation_rules')
        .update(updates)
        .eq('id', id)
        .select()
        .single();

      if (error) throw error;
      return NextResponse.json({ success: true, rule: data });
    }

    return NextResponse.json({ success: true, id, ...updates });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error updating rule' },
      { status: 500 }
    );
  }
}

export async function DELETE(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const id = searchParams.get('id');

    if (!id) {
      return NextResponse.json({ error: 'Rule ID is required.' }, { status: 400 });
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    if (isConfigured) {
      const { error } = await supabase
        .from('automation_rules')
        .delete()
        .eq('id', id);

      if (error) throw error;
    }

    return NextResponse.json({ success: true, deletedId: id });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error deleting rule' },
      { status: 500 }
    );
  }
}
