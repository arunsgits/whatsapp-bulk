import { NextRequest, NextResponse } from 'next/server';
import { getServiceSupabase, isServerSupabaseConfigured } from '@/lib/supabase/server';
import { mockContacts } from '@/lib/mock-contacts';

export async function GET(request: NextRequest) {
  const { searchParams } = new URL(request.url);
  const groupId = searchParams.get('groupId');

  if (!groupId) {
    return NextResponse.json({ error: 'Group ID is required.' }, { status: 400 });
  }

  const supabase = getServiceSupabase();
  const isConfigured = isServerSupabaseConfigured();

  if (!isConfigured) {
    const members = mockContacts.filter((c) => c.group_ids?.includes(groupId));
    return NextResponse.json({ success: true, source: 'mock', members });
  }

  try {
    const { data: memberRows, error } = await supabase
      .from('contact_group_members')
      .select('contact_id, contacts(*)')
      .eq('group_id', groupId);

    if (error) throw error;

    const members = (memberRows || []).map((r: any) => r.contacts).filter(Boolean);
    return NextResponse.json({ success: true, source: 'supabase', members });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error fetching group members' },
      { status: 500 }
    );
  }
}

export async function POST(request: NextRequest) {
  try {
    const { groupId, contactIds } = await request.json();

    if (!groupId || !Array.isArray(contactIds) || contactIds.length === 0) {
      return NextResponse.json(
        { error: 'Group ID and contactIds array are required.' },
        { status: 400 }
      );
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    if (isConfigured) {
      const rows = contactIds.map((cid: string) => ({
        group_id: groupId,
        contact_id: cid,
      }));

      const { error } = await supabase
        .from('contact_group_members')
        .upsert(rows, { onConflict: 'group_id,contact_id' });

      if (error) throw error;
    }

    return NextResponse.json({
      success: true,
      addedCount: contactIds.length,
      groupId,
    });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error adding group members' },
      { status: 500 }
    );
  }
}

export async function DELETE(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const groupId = searchParams.get('groupId');
    const contactId = searchParams.get('contactId');

    if (!groupId || !contactId) {
      return NextResponse.json(
        { error: 'Group ID and Contact ID are required.' },
        { status: 400 }
      );
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    if (isConfigured) {
      const { error } = await supabase
        .from('contact_group_members')
        .delete()
        .match({ group_id: groupId, contact_id: contactId });

      if (error) throw error;
    }

    return NextResponse.json({ success: true, removed: { groupId, contactId } });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error removing group member' },
      { status: 500 }
    );
  }
}
