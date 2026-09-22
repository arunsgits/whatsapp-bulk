import { NextRequest, NextResponse } from 'next/server';
import { getServiceSupabase, isServerSupabaseConfigured } from '@/lib/supabase/server';
import { mockContactGroups } from '@/lib/mock-contacts';

export async function GET() {
  const supabase = getServiceSupabase();
  const isConfigured = isServerSupabaseConfigured();

  if (!isConfigured) {
    return NextResponse.json({
      success: true,
      source: 'mock',
      groups: mockContactGroups,
    });
  }

  try {
    const { data: groups, error } = await supabase
      .from('contact_groups')
      .select('*')
      .order('created_at', { ascending: false });

    if (error) throw error;

    // Calculate member counts for each group
    const groupsWithCounts = await Promise.all(
      (groups || []).map(async (g: any) => {
        const { count } = await supabase
          .from('contact_group_members')
          .select('*', { count: 'exact', head: true })
          .eq('group_id', g.id);

        return {
          ...g,
          member_count: count || 0,
        };
      })
    );

    return NextResponse.json({
      success: true,
      source: 'supabase',
      groups: groupsWithCounts.length > 0 ? groupsWithCounts : mockContactGroups,
    });
  } catch (err: any) {
    return NextResponse.json({
      success: true,
      source: 'fallback',
      groups: mockContactGroups,
      error: err.message,
    });
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { name, description } = body;

    if (!name) {
      return NextResponse.json(
        { error: 'Group name is required.' },
        { status: 400 }
      );
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    const groupData = {
      name,
      description: description || null,
    };

    if (isConfigured) {
      const { data, error } = await supabase
        .from('contact_groups')
        .insert(groupData)
        .select()
        .single();

      if (error) throw error;
      return NextResponse.json({
        success: true,
        group: { ...data, member_count: 0 },
      });
    }

    const mockCreated = {
      id: `grp-${Date.now()}`,
      ...groupData,
      member_count: 0,
      created_at: new Date().toISOString(),
    };

    mockContactGroups.unshift(mockCreated);
    return NextResponse.json({ success: true, group: mockCreated });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error creating group' },
      { status: 500 }
    );
  }
}

export async function PATCH(request: NextRequest) {
  try {
    const body = await request.json();
    const { id, name, description } = body;

    if (!id || !name) {
      return NextResponse.json(
        { error: 'Group ID and Name are required.' },
        { status: 400 }
      );
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    if (isConfigured) {
      const { data, error } = await supabase
        .from('contact_groups')
        .update({ name, description })
        .eq('id', id)
        .select()
        .single();

      if (error) throw error;
      return NextResponse.json({ success: true, group: data });
    }

    const idx = mockContactGroups.findIndex((g) => g.id === id);
    if (idx !== -1) {
      mockContactGroups[idx] = { ...mockContactGroups[idx], name, description };
    }

    return NextResponse.json({ success: true, id, name, description });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error updating group' },
      { status: 500 }
    );
  }
}

export async function DELETE(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const id = searchParams.get('id');

    if (!id) {
      return NextResponse.json({ error: 'Group ID is required.' }, { status: 400 });
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    if (isConfigured) {
      const { error } = await supabase.from('contact_groups').delete().eq('id', id);
      if (error) throw error;
    } else {
      const idx = mockContactGroups.findIndex((g) => g.id === id);
      if (idx !== -1) mockContactGroups.splice(idx, 1);
    }

    return NextResponse.json({ success: true, deletedId: id });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error deleting group' },
      { status: 500 }
    );
  }
}
