import { NextRequest, NextResponse } from 'next/server';
import { getServiceSupabase, isServerSupabaseConfigured } from '@/lib/supabase/server';
import { normalizePhoneNumber } from '@/lib/whatsapp/phone';
import { mockContacts } from '@/lib/mock-contacts';

export async function POST(request: NextRequest) {
  try {
    const { contacts, groupId } = await request.json();

    if (!Array.isArray(contacts) || contacts.length === 0) {
      return NextResponse.json(
        { error: 'contacts array is required.' },
        { status: 400 }
      );
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    const normalizedRows: {
      name: string;
      phone: string;
      email: string | null;
      tags: string[];
      custom_attributes: Record<string, any>;
      status: string;
    }[] = [];

    for (const c of contacts) {
      const phone = normalizePhoneNumber(c.phone || '');
      if (!phone || phone.length < 8) continue;

      normalizedRows.push({
        name: c.name?.trim() || 'Valued Customer',
        phone,
        email: c.email?.trim() || null,
        tags: Array.isArray(c.tags) ? c.tags : (c.tags ? [String(c.tags)] : []),
        custom_attributes: c.custom_attributes || {},
        status: 'active',
      });
    }

    if (normalizedRows.length === 0) {
      return NextResponse.json(
        { error: 'No valid contacts found with valid phone numbers.' },
        { status: 400 }
      );
    }

    if (isConfigured) {
      const { data: upsertedContacts, error } = await supabase
        .from('contacts')
        .upsert(normalizedRows, { onConflict: 'phone' })
        .select('id');

      if (error) throw error;

      if (groupId && upsertedContacts && upsertedContacts.length > 0) {
        const groupMemberships = upsertedContacts.map((c: any) => ({
          group_id: groupId,
          contact_id: c.id,
        }));

        await supabase
          .from('contact_group_members')
          .upsert(groupMemberships, { onConflict: 'group_id,contact_id' });
      }

      return NextResponse.json({
        success: true,
        importedCount: normalizedRows.length,
        groupId: groupId || null,
      });
    }

    for (const r of normalizedRows) {
      mockContacts.unshift({
        id: `cnt-${Date.now()}-${Math.random().toString(36).substring(2, 7)}`,
        ...r,
        group_ids: groupId ? [groupId] : [],
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      });
    }

    return NextResponse.json({
      success: true,
      source: 'mock',
      importedCount: normalizedRows.length,
      groupId: groupId || null,
    });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error importing contacts batch' },
      { status: 500 }
    );
  }
}
