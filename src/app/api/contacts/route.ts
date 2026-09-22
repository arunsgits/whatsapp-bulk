import { NextRequest, NextResponse } from 'next/server';
import { getServiceSupabase, isServerSupabaseConfigured } from '@/lib/supabase/server';
import { normalizePhoneNumber } from '@/lib/whatsapp/phone';
import { mockContacts } from '@/lib/mock-contacts';

export async function GET(request: NextRequest) {
  const { searchParams } = new URL(request.url);
  const q = searchParams.get('q')?.toLowerCase();
  const tag = searchParams.get('tag');
  const groupId = searchParams.get('groupId');

  const supabase = getServiceSupabase();
  const isConfigured = isServerSupabaseConfigured();

  if (!isConfigured) {
    let filtered = [...mockContacts];

    if (q) {
      filtered = filtered.filter(
        (c) =>
          c.name.toLowerCase().includes(q) ||
          c.phone.includes(q) ||
          c.email?.toLowerCase().includes(q)
      );
    }

    if (tag) {
      filtered = filtered.filter((c) => c.tags?.includes(tag));
    }

    if (groupId) {
      filtered = filtered.filter((c) => c.group_ids?.includes(groupId));
    }

    return NextResponse.json({
      success: true,
      source: 'mock',
      contacts: filtered,
    });
  }

  try {
    let query = supabase.from('contacts').select('*').order('created_at', { ascending: false });

    if (q) {
      query = query.or(`name.ilike.%${q}%,phone.ilike.%${q}%,email.ilike.%${q}%`);
    }

    if (tag) {
      query = query.contains('tags', [tag]);
    }

    if (groupId) {
      // Join through contact_group_members
      const { data: memberRows } = await supabase
        .from('contact_group_members')
        .select('contact_id')
        .eq('group_id', groupId);

      const contactIds = (memberRows || []).map((m: any) => m.contact_id);
      if (contactIds.length === 0) {
        return NextResponse.json({ success: true, source: 'supabase', contacts: [] });
      }
      query = query.in('id', contactIds);
    }

    const { data, error } = await query;
    if (error) throw error;

    return NextResponse.json({
      success: true,
      source: 'supabase',
      contacts: data && data.length > 0 ? data : mockContacts,
    });
  } catch (err: any) {
    return NextResponse.json({
      success: true,
      source: 'fallback',
      contacts: mockContacts,
      error: err.message,
    });
  }
}

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { name, phone, email, tags, custom_attributes, group_id } = body;

    if (!name || !phone) {
      return NextResponse.json(
        { error: 'Name and Phone number are required.' },
        { status: 400 }
      );
    }

    const normalizedPhone = normalizePhoneNumber(phone);
    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    const contactPayload = {
      name,
      phone: normalizedPhone,
      email: email || null,
      tags: Array.isArray(tags) ? tags : [],
      custom_attributes: custom_attributes || {},
      status: 'active',
    };

    if (isConfigured) {
      const { data: contact, error } = await supabase
        .from('contacts')
        .upsert(contactPayload, { onConflict: 'phone' })
        .select()
        .single();

      if (error) throw error;

      // If group_id provided, add to group
      if (group_id && contact) {
        await supabase.from('contact_group_members').upsert({
          group_id,
          contact_id: contact.id,
        });
      }

      return NextResponse.json({ success: true, contact });
    }

    const mockCreated = {
      id: `cnt-${Date.now()}`,
      ...contactPayload,
      group_ids: group_id ? [group_id] : [],
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    mockContacts.unshift(mockCreated);
    return NextResponse.json({ success: true, contact: mockCreated });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error creating contact' },
      { status: 500 }
    );
  }
}

export async function PATCH(request: NextRequest) {
  try {
    const body = await request.json();
    const { id, name, phone, email, tags, custom_attributes, status } = body;

    if (!id) {
      return NextResponse.json({ error: 'Contact ID is required.' }, { status: 400 });
    }

    const updates: any = {};
    if (name) updates.name = name;
    if (phone) updates.phone = normalizePhoneNumber(phone);
    if (email !== undefined) updates.email = email;
    if (tags !== undefined) updates.tags = tags;
    if (custom_attributes !== undefined) updates.custom_attributes = custom_attributes;
    if (status) updates.status = status;
    updates.updated_at = new Date().toISOString();

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    if (isConfigured) {
      const { data, error } = await supabase
        .from('contacts')
        .update(updates)
        .eq('id', id)
        .select()
        .single();

      if (error) throw error;
      return NextResponse.json({ success: true, contact: data });
    }

    const idx = mockContacts.findIndex((c) => c.id === id);
    if (idx !== -1) {
      mockContacts[idx] = { ...mockContacts[idx], ...updates };
    }

    return NextResponse.json({ success: true, id, ...updates });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error updating contact' },
      { status: 500 }
    );
  }
}

export async function DELETE(request: NextRequest) {
  try {
    const { searchParams } = new URL(request.url);
    const id = searchParams.get('id');

    if (!id) {
      return NextResponse.json({ error: 'Contact ID is required.' }, { status: 400 });
    }

    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    if (isConfigured) {
      const { error } = await supabase.from('contacts').delete().eq('id', id);
      if (error) throw error;
    } else {
      const idx = mockContacts.findIndex((c) => c.id === id);
      if (idx !== -1) mockContacts.splice(idx, 1);
    }

    return NextResponse.json({ success: true, deletedId: id });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error deleting contact' },
      { status: 500 }
    );
  }
}
