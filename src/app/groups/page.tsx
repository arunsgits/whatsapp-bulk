'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import {
  FolderKanban,
  Plus,
  Users,
  Send,
  Trash2,
  CheckCircle2,
  AlertCircle,
  X,
  ExternalLink,
} from 'lucide-react';
import { ContactGroup, Contact } from '@/types/database';
import { mockContactGroups, mockContacts } from '@/lib/mock-contacts';
import { formatPhoneDisplay } from '@/lib/whatsapp/phone';

export default function GroupsPage() {
  const [groups, setGroups] = useState<(ContactGroup & { member_count: number })[]>(mockContactGroups);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [newGroupName, setNewGroupName] = useState('');
  const [newGroupDesc, setNewGroupDesc] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  // Manage members drawer
  const [activeGroup, setActiveGroup] = useState<(ContactGroup & { member_count: number }) | null>(null);
  const [groupMembers, setGroupMembers] = useState<Contact[]>([]);
  const [isLoadingMembers, setIsLoadingMembers] = useState(false);

  const loadGroups = async () => {
    try {
      const res = await fetch('/api/groups');
      const data = await res.json();
      if (data.groups) setGroups(data.groups);
    } catch (err) {
      console.error(err);
    }
  };

  useEffect(() => {
    loadGroups();
  }, []);

  const handleCreateGroup = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newGroupName) return;

    setIsSubmitting(true);
    setFeedback(null);

    try {
      const res = await fetch('/api/groups', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: newGroupName, description: newGroupDesc }),
      });

      const data = await res.json();
      if (data.success && data.group) {
        setGroups([data.group, ...groups]);
        setShowCreateModal(false);
        setNewGroupName('');
        setNewGroupDesc('');
        setFeedback({ type: 'success', message: 'Group created successfully!' });
        setTimeout(() => setFeedback(null), 3000);
      } else {
        setFeedback({ type: 'error', message: data.error || 'Failed to create group' });
      }
    } catch {
      setFeedback({ type: 'error', message: 'Network error creating group' });
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleDeleteGroup = async (id: string) => {
    if (!confirm('Are you sure you want to delete this group? Contacts will not be deleted.')) return;
    try {
      await fetch(`/api/groups?id=${id}`, { method: 'DELETE' });
      setGroups((prev) => prev.filter((g) => g.id !== id));
      if (activeGroup?.id === id) setActiveGroup(null);
    } catch (e) {
      console.error(e);
    }
  };

  const openGroupMembers = async (group: ContactGroup & { member_count: number }) => {
    setActiveGroup(group);
    setIsLoadingMembers(true);
    try {
      const res = await fetch(`/api/groups/members?groupId=${group.id}`);
      const data = await res.json();
      if (data.members) {
        setGroupMembers(data.members);
      } else {
        const fallback = mockContacts.filter((c) => c.group_ids?.includes(group.id));
        setGroupMembers(fallback);
      }
    } catch {
      const fallback = mockContacts.filter((c) => c.group_ids?.includes(group.id));
      setGroupMembers(fallback);
    } finally {
      setIsLoadingMembers(false);
    }
  };

  const handleRemoveMember = async (contactId: string) => {
    if (!activeGroup) return;
    try {
      await fetch(`/api/groups/members?groupId=${activeGroup.id}&contactId=${contactId}`, {
        method: 'DELETE',
      });
      setGroupMembers((prev) => prev.filter((m) => m.id !== contactId));
      setGroups((prev) =>
        prev.map((g) =>
          g.id === activeGroup.id ? { ...g, member_count: Math.max(0, g.member_count - 1) } : g
        )
      );
    } catch (e) {
      console.error(e);
    }
  };

  return (
    <div className="space-y-6 animate-fadeIn pb-12">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-emerald-50 border border-emerald-200/60 text-emerald-600">
              <FolderKanban className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-xl font-bold tracking-tight text-slate-900">
                  Group Master Module
                </h1>
                <span className="text-[11px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 font-mono">
                  {groups.length} Segments
                </span>
              </div>
              <p className="text-xs text-slate-500 mt-0.5">
                Organize contacts into custom marketing segments, audience lists, and one-click bulk broadcast targets.
              </p>
            </div>
          </div>
        </div>

        <button
          type="button"
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs shadow-sm shadow-emerald-600/20 transition-all cursor-pointer"
        >
          <Plus className="w-4 h-4" />
          <span>Create New Group</span>
        </button>
      </div>

      {feedback && (
        <div
          className={`p-3.5 rounded-xl text-xs flex items-center gap-2.5 border transition-all ${
            feedback.type === 'success'
              ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
              : 'bg-rose-50 text-rose-800 border-rose-200'
          }`}
        >
          {feedback.type === 'success' ? (
            <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
          ) : (
            <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
          )}
          <span className="font-medium">{feedback.message}</span>
        </div>
      )}

      {/* Group Cards Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        {groups.map((group) => (
          <div
            key={group.id}
            className="enterprise-card hover:border-slate-300 hover:shadow-md transition-all flex flex-col justify-between"
          >
            <div>
              <div className="flex items-center justify-between mb-3">
                <div className="w-9 h-9 rounded-xl bg-emerald-50 border border-emerald-200/70 flex items-center justify-center text-emerald-600">
                  <FolderKanban className="w-4 h-4" />
                </div>

                <span className="text-[11px] px-2.5 py-1 rounded-full bg-slate-100 text-slate-700 font-mono font-medium flex items-center gap-1.5 border border-slate-200">
                  <Users className="w-3 h-3 text-emerald-600" />
                  {group.member_count} contacts
                </span>
              </div>

              <h3 className="font-bold text-sm text-slate-900 group-hover:text-emerald-700 transition-colors">
                {group.name}
              </h3>

              <p className="text-xs text-slate-500 mt-1.5 leading-relaxed line-clamp-2">
                {group.description || 'No description provided for this customer segment.'}
              </p>
            </div>

            <div className="pt-4 mt-4 border-t border-slate-100 space-y-2.5">
              <div className="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  onClick={() => openGroupMembers(group)}
                  className="px-3 py-2 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs font-medium flex items-center justify-center gap-1.5 transition-all cursor-pointer"
                >
                  <Users className="w-3.5 h-3.5 text-slate-500" />
                  <span>View Members</span>
                </button>

                <Link
                  href={`/campaigns?groupId=${group.id}`}
                  className="px-3 py-2 rounded-lg bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200/80 text-xs font-medium flex items-center justify-center gap-1.5 transition-all"
                >
                  <Send className="w-3.5 h-3.5 text-emerald-600" />
                  <span>Broadcast</span>
                </Link>
              </div>

              <div className="flex justify-between items-center text-[10px] text-slate-400 px-0.5">
                <span>Created {new Date(group.created_at).toLocaleDateString()}</span>
                <button
                  type="button"
                  onClick={() => handleDeleteGroup(group.id)}
                  className="hover:text-rose-600 p-1 cursor-pointer transition-colors"
                  title="Delete Group"
                >
                  <Trash2 className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>

      {/* Create Group Modal */}
      {showCreateModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
          <form
            onSubmit={handleCreateGroup}
            className="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full space-y-4 animate-fadeIn"
          >
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2">
                <FolderKanban className="w-4 h-4 text-emerald-600" />
                Create New Contact Group
              </h3>
              <button
                type="button"
                onClick={() => setShowCreateModal(false)}
                className="text-slate-400 hover:text-slate-600 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Group / Segment Name <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  value={newGroupName}
                  onChange={(e) => setNewGroupName(e.target.value)}
                  placeholder="e.g. VIP High Ticket Clients"
                  className="enterprise-input w-full text-xs"
                  required
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Description / Purpose
                </label>
                <textarea
                  rows={3}
                  value={newGroupDesc}
                  onChange={(e) => setNewGroupDesc(e.target.value)}
                  placeholder="Audience segment description for marketing and follow-ups..."
                  className="enterprise-input w-full text-xs resize-none"
                />
              </div>
            </div>

            <div className="pt-2 flex items-center justify-end gap-2">
              <button
                type="button"
                onClick={() => setShowCreateModal(false)}
                className="px-4 py-2 rounded-lg text-slate-600 hover:bg-slate-100 font-medium text-xs transition-colors cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="py-2 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs shadow-sm shadow-emerald-600/20 transition-all cursor-pointer"
              >
                {isSubmitting ? 'Creating...' : 'Save Group'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* Group Members Drawer */}
      {activeGroup && (
        <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-end p-0">
          <div className="w-full max-w-md h-full bg-white border-l border-slate-200 shadow-2xl p-6 flex flex-col justify-between space-y-4 animate-fadeIn">
            <div className="flex-1 flex flex-col overflow-hidden">
              <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                <div>
                  <h3 className="font-bold text-base text-slate-900 flex items-center gap-2">
                    <FolderKanban className="w-4 h-4 text-emerald-600" />
                    {activeGroup.name}
                  </h3>
                  <p className="text-xs text-slate-500 mt-0.5">
                    {groupMembers.length} contacts currently assigned
                  </p>
                </div>

                <button
                  type="button"
                  onClick={() => setActiveGroup(null)}
                  className="p-1 rounded-lg text-slate-400 hover:text-slate-600 cursor-pointer"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>

              {/* Members List */}
              <div className="mt-4 space-y-2 flex-1 overflow-y-auto pr-1">
                {isLoadingMembers ? (
                  <div className="p-8 text-center text-xs text-slate-400">
                    Loading group members...
                  </div>
                ) : groupMembers.length === 0 ? (
                  <div className="p-8 text-center text-xs text-slate-500 space-y-2.5">
                    <Users className="w-8 h-8 text-slate-300 mx-auto" />
                    <p>No contacts assigned to this group yet.</p>
                    <Link
                      href="/contacts"
                      className="text-emerald-600 font-medium hover:underline inline-flex items-center gap-1"
                    >
                      Assign from Contacts Directory &rarr;
                    </Link>
                  </div>
                ) : (
                  groupMembers.map((member) => (
                    <div
                      key={member.id}
                      className="p-3 rounded-xl bg-slate-50 border border-slate-200/70 flex items-center justify-between text-xs hover:border-slate-300 transition-colors"
                    >
                      <div>
                        <div className="font-semibold text-slate-900">
                          {member.name}
                        </div>
                        <div className="font-mono text-emerald-700 text-[11px]">
                          {formatPhoneDisplay(member.phone)}
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        <button
                          type="button"
                          onClick={() => handleRemoveMember(member.id)}
                          className="p-1 text-slate-400 hover:text-rose-600 cursor-pointer transition-colors"
                          title="Remove from group"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>

            <div className="pt-4 border-t border-slate-100 flex items-center gap-2">
              <Link
                href={`/campaigns?groupId=${activeGroup.id}`}
                className="w-full py-2.5 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs flex items-center justify-center gap-2 shadow-sm shadow-emerald-600/20 transition-all"
              >
                <Send className="w-4 h-4" />
                <span>Launch Broadcast to this Group</span>
              </Link>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
