'use client';

import React, { useState, useEffect, useRef } from 'react';
import Link from 'next/link';
import {
  Users,
  Search,
  Plus,
  UploadCloud,
  FileSpreadsheet,
  Tag,
  Phone,
  Mail,
  Building,
  Trash2,
  Edit2,
  FolderPlus,
  Send,
  CheckCircle2,
  AlertCircle,
  Filter,
  Check,
  X,
} from 'lucide-react';
import { Contact, ContactGroup } from '@/types/database';
import { formatPhoneDisplay } from '@/lib/whatsapp/phone';
import { mockContacts, mockContactGroups } from '@/lib/mock-contacts';

export default function ContactsPage() {
  const [contacts, setContacts] = useState<(Contact & { group_ids?: string[] })[]>(mockContacts);
  const [groups, setGroups] = useState<(ContactGroup & { member_count: number })[]>(mockContactGroups);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedTag, setSelectedTag] = useState<string>('all');
  const [selectedGroupId, setSelectedGroupId] = useState<string>('all');
  const [selectedContactIds, setSelectedContactIds] = useState<string[]>([]);

  // Modals
  const [showAddModal, setShowAddModal] = useState(false);
  const [showImportModal, setShowImportModal] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [feedback, setFeedback] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  // New Contact Form
  const [newName, setNewName] = useState('');
  const [newPhone, setNewPhone] = useState('');
  const [newEmail, setNewEmail] = useState('');
  const [newTags, setNewTags] = useState('');
  const [newCompany, setNewCompany] = useState('');
  const [newAssignGroup, setNewAssignGroup] = useState('');

  // CSV Import Form
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [importText, setImportText] = useState('');
  const [importGroupId, setImportGroupId] = useState('');

  // Fetch live contacts and groups
  const loadData = async () => {
    try {
      const [cRes, gRes] = await Promise.all([
        fetch(
          `/api/contacts?q=${encodeURIComponent(searchQuery)}${
            selectedTag !== 'all' ? `&tag=${selectedTag}` : ''
          }${selectedGroupId !== 'all' ? `&groupId=${selectedGroupId}` : ''}`
        ),
        fetch('/api/groups'),
      ]);

      const cData = await cRes.json();
      const gData = await gRes.json();

      if (cData.contacts) setContacts(cData.contacts);
      if (gData.groups) setGroups(gData.groups);
    } catch (err) {
      console.error(err);
    }
  };

  useEffect(() => {
    loadData();
  }, [searchQuery, selectedTag, selectedGroupId]);

  // Available tags list
  const allTags = Array.from(new Set(contacts.flatMap((c) => c.tags || [])));

  const handleCreateContact = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!newName || !newPhone) return;

    setIsSubmitting(true);
    setFeedback(null);

    try {
      const res = await fetch('/api/contacts', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: newName,
          phone: newPhone,
          email: newEmail || null,
          tags: newTags
            .split(',')
            .map((t) => t.trim())
            .filter(Boolean),
          custom_attributes: newCompany ? { company: newCompany } : {},
          group_id: newAssignGroup || null,
        }),
      });

      const data = await res.json();
      if (data.success && data.contact) {
        setContacts([data.contact, ...contacts]);
        setShowAddModal(false);
        setNewName('');
        setNewPhone('');
        setNewEmail('');
        setNewTags('');
        setNewCompany('');
        setNewAssignGroup('');
        setFeedback({ type: 'success', message: 'Contact created successfully!' });
        setTimeout(() => setFeedback(null), 3000);
      } else {
        setFeedback({ type: 'error', message: data.error || 'Failed to create contact' });
      }
    } catch {
      setFeedback({ type: 'error', message: 'Network error creating contact' });
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleImportBatch = async (e: React.FormEvent) => {
    e.preventDefault();
    const rows = importText.split('\n').map((l) => l.trim()).filter(Boolean);
    if (rows.length === 0) return;

    setIsSubmitting(true);
    const parsedContacts = rows.map((r) => {
      const parts = r.split(',').map((p) => p.trim());
      return {
        phone: parts[0] || '',
        name: parts[1] || 'Valued Customer',
        email: parts[2] || undefined,
        tags: parts[3] ? parts[3].split(';') : [],
      };
    });

    try {
      const res = await fetch('/api/contacts/import', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          contacts: parsedContacts,
          groupId: importGroupId || undefined,
        }),
      });

      const data = await res.json();
      if (data.success) {
        setShowImportModal(false);
        setImportText('');
        setImportGroupId('');
        setFeedback({
          type: 'success',
          message: `Successfully imported ${data.importedCount} contacts!`,
        });
        setTimeout(() => setFeedback(null), 4000);
        loadData();
      } else {
        setFeedback({ type: 'error', message: data.error || 'Import failed' });
      }
    } catch {
      setFeedback({ type: 'error', message: 'Network error during batch import' });
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (event) => {
      const text = event.target?.result as string;
      if (!text) return;
      setImportText(text);
    };
    reader.readAsText(file);
  };

  const handleDeleteContact = async (id: string) => {
    if (!confirm('Are you sure you want to delete this contact?')) return;
    try {
      await fetch(`/api/contacts?id=${id}`, { method: 'DELETE' });
      setContacts((prev) => prev.filter((c) => c.id !== id));
    } catch (e) {
      console.error(e);
    }
  };

  const toggleSelectAll = () => {
    if (selectedContactIds.length === contacts.length) {
      setSelectedContactIds([]);
    } else {
      setSelectedContactIds(contacts.map((c) => c.id));
    }
  };

  const toggleSelectContact = (id: string) => {
    setSelectedContactIds((prev) =>
      prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
    );
  };

  return (
    <div className="space-y-6 animate-fadeIn pb-12">
      {/* Top Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-emerald-50 border border-emerald-200/60 text-emerald-600">
              <Users className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-xl font-bold tracking-tight text-slate-900">
                  Contacts Directory
                </h1>
                <span className="text-[11px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 font-mono">
                  {contacts.length} Contacts
                </span>
              </div>
              <p className="text-xs text-slate-500 mt-0.5">
                Centralized directory of customer numbers, tags, and audience groups.
              </p>
            </div>
          </div>
        </div>

        <div className="flex items-center gap-2.5">
          <button
            type="button"
            onClick={() => setShowImportModal(true)}
            className="flex items-center gap-2 px-3.5 py-2 rounded-lg bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-medium text-xs shadow-xs transition-colors cursor-pointer"
          >
            <UploadCloud className="w-4 h-4 text-slate-500" />
            <span>Import CSV</span>
          </button>

          <button
            type="button"
            onClick={() => setShowAddModal(true)}
            className="flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs shadow-sm shadow-emerald-600/20 transition-all cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span>Add Contact</span>
          </button>
        </div>
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

      {/* Search & Filter Controls */}
      <div className="enterprise-card p-4 flex flex-col md:flex-row items-center justify-between gap-3">
        <div className="relative flex-1 w-full">
          <Search className="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Search by name, WhatsApp number (+91...), or email..."
            className="enterprise-input w-full pl-9 pr-4 text-xs h-9"
          />
        </div>

        <div className="flex items-center gap-2.5 w-full md:w-auto">
          {/* Tag filter */}
          <select
            value={selectedTag}
            onChange={(e) => setSelectedTag(e.target.value)}
            className="enterprise-input text-xs h-9 bg-white text-slate-700 cursor-pointer"
          >
            <option value="all">All Tags</option>
            {allTags.map((tag) => (
              <option key={tag} value={tag}>
                Tag: {tag}
              </option>
            ))}
          </select>

          {/* Group filter */}
          <select
            value={selectedGroupId}
            onChange={(e) => setSelectedGroupId(e.target.value)}
            className="enterprise-input text-xs h-9 bg-white text-slate-700 cursor-pointer"
          >
            <option value="all">All Groups</option>
            {groups.map((g) => (
              <option key={g.id} value={g.id}>
                {g.name} ({g.member_count})
              </option>
            ))}
          </select>
        </div>
      </div>

      {/* Bulk Action Toolbar if selected */}
      {selectedContactIds.length > 0 && (
        <div className="p-3 px-4 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-between text-xs animate-fadeIn">
          <div className="flex items-center gap-2">
            <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
            <span className="text-emerald-950 font-medium">
              <strong>{selectedContactIds.length}</strong> contacts selected for action
            </span>
          </div>

          <div className="flex items-center gap-3">
            <Link
              href="/campaigns"
              className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-600 text-white font-medium hover:bg-emerald-700 shadow-xs transition-all"
            >
              <Send className="w-3.5 h-3.5" />
              <span>Broadcast Campaign</span>
            </Link>

            <button
              type="button"
              onClick={() => setSelectedContactIds([])}
              className="text-slate-500 hover:text-slate-800 font-medium cursor-pointer"
            >
              Clear selection
            </button>
          </div>
        </div>
      )}

      {/* Contacts Table */}
      <div className="enterprise-card p-0 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs text-slate-700">
            <thead className="bg-slate-50 text-slate-500 uppercase text-[10px] font-semibold tracking-wider border-b border-slate-200/80">
              <tr>
                <th className="p-3.5 pl-4 w-10">
                  <input
                    type="checkbox"
                    checked={
                      contacts.length > 0 &&
                      selectedContactIds.length === contacts.length
                    }
                    onChange={toggleSelectAll}
                    className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                  />
                </th>
                <th className="p-3.5">Customer / Contact</th>
                <th className="p-3.5">WhatsApp Number</th>
                <th className="p-3.5">Tags</th>
                <th className="p-3.5">Company / City</th>
                <th className="p-3.5">Added Date</th>
                <th className="p-3.5 pr-4 text-right">Actions</th>
              </tr>
            </thead>

            <tbody className="divide-y divide-slate-100">
              {contacts.length === 0 ? (
                <tr>
                  <td colSpan={7} className="p-12 text-center text-slate-400">
                    <Users className="w-8 h-8 text-slate-300 mx-auto mb-2" />
                    No contacts found matching your search criteria.
                  </td>
                </tr>
              ) : (
                contacts.map((contact) => {
                  const isChecked = selectedContactIds.includes(contact.id);

                  return (
                    <tr
                      key={contact.id}
                      className={`hover:bg-slate-50/70 transition-colors ${
                        isChecked ? 'bg-emerald-50/50' : ''
                      }`}
                    >
                      <td className="p-3.5 pl-4">
                        <input
                          type="checkbox"
                          checked={isChecked}
                          onChange={() => toggleSelectContact(contact.id)}
                          className="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                        />
                      </td>

                      <td className="p-3.5">
                        <div className="flex items-center gap-3">
                          <div className="w-8 h-8 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200 flex items-center justify-center font-bold text-xs shrink-0">
                            {contact.name?.[0] || 'C'}
                          </div>
                          <div>
                            <div className="font-semibold text-slate-900">
                              {contact.name}
                            </div>
                            {contact.email && (
                              <div className="text-[11px] text-slate-400">
                                {contact.email}
                              </div>
                            )}
                          </div>
                        </div>
                      </td>

                      <td className="p-3.5 font-mono text-slate-800 font-medium">
                        {formatPhoneDisplay(contact.phone)}
                      </td>

                      <td className="p-3.5">
                        <div className="flex flex-wrap gap-1">
                          {contact.tags && contact.tags.length > 0 ? (
                            contact.tags.map((t, idx) => (
                              <span
                                key={idx}
                                className="text-[10px] font-medium px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200 text-slate-700"
                              >
                                {t}
                              </span>
                            ))
                          ) : (
                            <span className="text-slate-300 text-[11px]">—</span>
                          )}
                        </div>
                      </td>

                      <td className="p-3.5 text-slate-600">
                        {contact.custom_attributes?.company ||
                          contact.custom_attributes?.city ||
                          '—'}
                      </td>

                      <td className="p-3.5 text-slate-400 text-[11px]">
                        {new Date(contact.created_at).toLocaleDateString()}
                      </td>

                      <td className="p-3.5 pr-4 text-right">
                        <div className="flex items-center justify-end gap-1.5">
                          <Link
                            href={`/inbox`}
                            className="p-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200/60 transition-all"
                            title="Message on WhatsApp"
                          >
                            <Send className="w-3.5 h-3.5" />
                          </Link>

                          <button
                            type="button"
                            onClick={() => handleDeleteContact(contact.id)}
                            className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition-all cursor-pointer"
                            title="Delete Contact"
                          >
                            <Trash2 className="w-3.5 h-3.5" />
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* Add Contact Modal */}
      {showAddModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
          <form
            onSubmit={handleCreateContact}
            className="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full space-y-4 animate-fadeIn"
          >
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2">
                <Plus className="w-4 h-4 text-emerald-600" />
                Add New WhatsApp Contact
              </h3>
              <button
                type="button"
                onClick={() => setShowAddModal(false)}
                className="text-slate-400 hover:text-slate-600 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Full Name <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  value={newName}
                  onChange={(e) => setNewName(e.target.value)}
                  placeholder="e.g. Rahul Sharma"
                  className="enterprise-input w-full text-xs"
                  required
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  WhatsApp Phone Number <span className="text-rose-500">*</span>
                </label>
                <input
                  type="text"
                  value={newPhone}
                  onChange={(e) => setNewPhone(e.target.value)}
                  placeholder="e.g. 919876543210 (include country code)"
                  className="enterprise-input w-full font-mono text-xs"
                  required
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Email Address
                </label>
                <input
                  type="email"
                  value={newEmail}
                  onChange={(e) => setNewEmail(e.target.value)}
                  placeholder="name@company.com"
                  className="enterprise-input w-full text-xs"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Company / Organization
                </label>
                <input
                  type="text"
                  value={newCompany}
                  onChange={(e) => setNewCompany(e.target.value)}
                  placeholder="e.g. Sharma Enterprises"
                  className="enterprise-input w-full text-xs"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Tags (comma-separated)
                </label>
                <input
                  type="text"
                  value={newTags}
                  onChange={(e) => setNewTags(e.target.value)}
                  placeholder="e.g. vip, retail, lead"
                  className="enterprise-input w-full text-xs"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Assign to Group
                </label>
                <select
                  value={newAssignGroup}
                  onChange={(e) => setNewAssignGroup(e.target.value)}
                  className="enterprise-input w-full text-xs cursor-pointer"
                >
                  <option value="">None (Individual Contact)</option>
                  {groups.map((g) => (
                    <option key={g.id} value={g.id}>
                      {g.name}
                    </option>
                  ))}
                </select>
              </div>
            </div>

            <div className="pt-2 flex items-center justify-end gap-2">
              <button
                type="button"
                onClick={() => setShowAddModal(false)}
                className="px-4 py-2 rounded-lg text-slate-600 hover:bg-slate-100 font-medium text-xs transition-colors cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="py-2 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs shadow-sm shadow-emerald-600/20 transition-all cursor-pointer"
              >
                {isSubmitting ? 'Saving...' : 'Save Contact'}
              </button>
            </div>
          </form>
        </div>
      )}

      {/* CSV Import Modal */}
      {showImportModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/40 backdrop-blur-xs flex items-center justify-center p-4">
          <form
            onSubmit={handleImportBatch}
            className="bg-white p-6 rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full space-y-4 animate-fadeIn"
          >
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2">
                <UploadCloud className="w-4 h-4 text-emerald-600" />
                Import Contacts from CSV / Plain Text
              </h3>
              <button
                type="button"
                onClick={() => setShowImportModal(false)}
                className="text-slate-400 hover:text-slate-600 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Upload CSV File
                </label>
                <input
                  type="file"
                  accept=".csv,.txt"
                  ref={fileInputRef}
                  onChange={handleFileUpload}
                  className="w-full text-xs text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 file:text-slate-700 hover:file:bg-slate-200 cursor-pointer"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Assign All Imported Contacts to Group
                </label>
                <select
                  value={importGroupId}
                  onChange={(e) => setImportGroupId(e.target.value)}
                  className="enterprise-input w-full text-xs cursor-pointer"
                >
                  <option value="">None</option>
                  {groups.map((g) => (
                    <option key={g.id} value={g.id}>
                      {g.name}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Or Paste Numbers (Format: Phone, Name, Email)
                </label>
                <textarea
                  rows={5}
                  value={importText}
                  onChange={(e) => setImportText(e.target.value)}
                  placeholder="919876543210, Rahul Sharma, rahul@example.com&#10;919123456789, Priya Patel, priya@example.com"
                  className="enterprise-input w-full font-mono text-xs leading-relaxed"
                  required
                />
              </div>
            </div>

            <div className="pt-2 flex items-center justify-end gap-2">
              <button
                type="button"
                onClick={() => setShowImportModal(false)}
                className="px-4 py-2 rounded-lg text-slate-600 hover:bg-slate-100 font-medium text-xs transition-colors cursor-pointer"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={isSubmitting || !importText.trim()}
                className="py-2 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs shadow-sm shadow-emerald-600/20 transition-all cursor-pointer"
              >
                {isSubmitting ? 'Importing...' : 'Start Batch Import'}
              </button>
            </div>
          </form>
        </div>
      )}
    </div>
  );
}
