'use client';

import React, { useState, useEffect, useRef } from 'react';
import {
  Send,
  UploadCloud,
  FileSpreadsheet,
  Users,
  Clock,
  ShieldCheck,
  CheckCircle2,
  AlertCircle,
  Play,
  Pause,
  RefreshCw,
  Sliders,
  Sparkles,
  Save,
  Check,
  FolderKanban,
  FileText,
  X,
} from 'lucide-react';
import { mockCampaigns, mockTemplates } from '@/lib/mock-data';
import { mockContactGroups, mockContacts } from '@/lib/mock-contacts';
import { Campaign, WhatsappTemplate, ContactGroup } from '@/types/database';

export default function CampaignsPage() {
  const [campaignName, setCampaignName] = useState('');
  const [messageType, setMessageType] = useState<'direct' | 'template'>('direct');
  const [templates, setTemplates] = useState<WhatsappTemplate[]>(mockTemplates);
  const [selectedTemplateId, setSelectedTemplateId] = useState(mockTemplates[0]?.id || '');
  const [groups, setGroups] = useState<(ContactGroup & { member_count: number })[]>(mockContactGroups);
  const [selectedGroupId, setSelectedGroupId] = useState<string>('');
  const [directMessage, setDirectMessage] = useState(
    'Hello {{name}}! We are thrilled to share an exclusive update with you today. Reply YES to claim your gift.'
  );
  const [phoneListInput, setPhoneListInput] = useState(
    '919876543210, John Doe\n919812345678, Sarah Connor\n919988776655, Alex Murphy\n919123456789, Bruce Wayne\n919765432100, Diana Prince'
  );
  const [throttleDelay, setThrottleDelay] = useState(2.0);
  const [savedNotice, setSavedNotice] = useState(false);

  // File upload state
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [uploadedFileName, setUploadedFileName] = useState<string | null>(null);

  // Active Dispatch state
  const [isDispatching, setIsDispatching] = useState(false);
  const [dispatchProgress, setDispatchProgress] = useState<{
    total: number;
    sent: number;
    failed: number;
    logs: { phone: string; name: string; status: 'sent' | 'failed'; time: string }[];
  } | null>(null);

  useEffect(() => {
    fetch('/api/whatsapp/templates')
      .then((res) => res.json())
      .then((data) => {
        if (data.templates && data.templates.length > 0) {
          setTemplates(data.templates);
          if (!selectedTemplateId) {
            setSelectedTemplateId(data.templates[0].id);
          }
        }
      })
      .catch(() => {});

    fetch('/api/groups')
      .then((res) => res.json())
      .then((data) => {
        if (data.groups && data.groups.length > 0) {
          setGroups(data.groups);
        }
      })
      .catch(() => {});
  }, [selectedTemplateId]);

  const handleGroupSelect = async (groupId: string) => {
    setSelectedGroupId(groupId);
    if (!groupId) return;

    try {
      const res = await fetch(`/api/groups/members?groupId=${groupId}`);
      const data = await res.json();
      if (data.members && data.members.length > 0) {
        const lines = data.members
          .map((m: any) => `${m.phone}, ${m.name || 'Valued Customer'}`)
          .join('\n');
        setPhoneListInput(lines);
      } else {
        const fallback = mockContacts.filter((c) => c.group_ids?.includes(groupId));
        if (fallback.length > 0) {
          const lines = fallback
            .map((m) => `${m.phone}, ${m.name}`)
            .join('\n');
          setPhoneListInput(lines);
        }
      }
    } catch (e) {
      console.error(e);
    }
  };

  const selectedTemplate = templates.find((t) => t.id === selectedTemplateId) || templates[0];

  const parseRecipients = (): { phone: string; name: string }[] => {
    return phoneListInput
      .split('\n')
      .map((line) => line.trim())
      .filter(Boolean)
      .map((line) => {
        const parts = line.split(',');
        const phone = parts[0]?.trim() || '';
        const name = parts[1]?.trim() || 'Valued Customer';
        return { phone, name };
      })
      .filter((r) => r.phone.length >= 8);
  };

  const recipients = parseRecipients();

  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    setUploadedFileName(file.name);
    const reader = new FileReader();
    reader.onload = (event) => {
      const text = event.target?.result as string;
      if (!text) return;

      const lines = text.split(/\r?\n/).filter(Boolean);
      if (lines.length === 0) return;

      const firstLine = lines[0].toLowerCase();
      const hasHeader = firstLine.includes('phone') || firstLine.includes('mobile') || firstLine.includes('number');
      const dataRows = hasHeader ? lines.slice(1) : lines;

      const parsed = dataRows
        .map((row) => {
          const cols = row.split(',').map((c) => c.replace(/['"]+/g, '').trim());
          const phone = cols[0] || '';
          const name = cols[1] || 'Valued Customer';
          return `${phone}, ${name}`;
        })
        .filter((l) => l.length >= 8)
        .join('\n');

      setPhoneListInput(parsed);
    };
    reader.readAsText(file);
  };

  const handleSaveCampaignDraft = async () => {
    if (!campaignName) return;

    try {
      await fetch('/api/campaigns/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: campaignName,
          direct_message: messageType === 'direct' ? directMessage : undefined,
          template_id: messageType === 'template' ? selectedTemplateId : undefined,
          throttle_delay_sec: throttleDelay,
          recipients,
        }),
      });

      setSavedNotice(true);
      setTimeout(() => setSavedNotice(false), 3000);
    } catch (err) {
      console.error('Failed to save campaign draft:', err);
    }
  };

  const handleStartBroadcast = async (e: React.FormEvent) => {
    e.preventDefault();
    if (recipients.length === 0) return;

    setIsDispatching(true);
    const total = recipients.length;
    let sent = 0;
    let failed = 0;
    const initialLogs: { phone: string; name: string; status: 'sent' | 'failed'; time: string }[] = [];

    setDispatchProgress({
      total,
      sent: 0,
      failed: 0,
      logs: initialLogs,
    });

    handleSaveCampaignDraft();

    for (let i = 0; i < recipients.length; i++) {
      const recipient = recipients[i];

      try {
        const res = await fetch('/api/whatsapp/send', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            phone: recipient.phone,
            message:
              messageType === 'direct'
                ? directMessage.replace(/\{\{name\}\}/gi, recipient.name)
                : undefined,
            templateName:
              messageType === 'template' ? selectedTemplate?.name : undefined,
            language: selectedTemplate?.language || 'en',
          }),
        });

        const data = await res.json();
        const success = data.success !== false;

        if (success) {
          sent++;
        } else {
          failed++;
        }

        const newLog = {
          phone: recipient.phone,
          name: recipient.name,
          status: (success ? 'sent' : 'failed') as 'sent' | 'failed',
          time: new Date().toLocaleTimeString(),
        };

        setDispatchProgress((prev) =>
          prev
            ? {
                ...prev,
                sent,
                failed,
                logs: [newLog, ...prev.logs],
              }
            : null
        );
      } catch {
        failed++;
      }

      if (i < recipients.length - 1) {
        await new Promise((resolve) => setTimeout(resolve, throttleDelay * 1000));
      }
    }

    setIsDispatching(false);
  };

  return (
    <div className="space-y-8 animate-fadeIn">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-slate-900 flex items-center gap-2">
            <Send className="w-6 h-6 text-emerald-600" />
            WhatsApp Bulk Campaign Engine
          </h1>
          <p className="text-sm text-slate-600 mt-1">
            Dispatch personalized broadcasts with variable tags, CSV lists, and anti-ban delay throttling.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <div className="flex items-center gap-2 px-3 py-1.5 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs font-semibold">
            <ShieldCheck className="w-4 h-4 text-emerald-600" />
            <span>Anti-Ban Protection Active</span>
          </div>

          <button
            type="button"
            onClick={handleSaveCampaignDraft}
            className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-semibold shadow-xs transition-all cursor-pointer"
          >
            {savedNotice ? (
              <Check className="w-3.5 h-3.5 text-emerald-600" />
            ) : (
              <Save className="w-3.5 h-3.5" />
            )}
            <span>{savedNotice ? 'Saved!' : 'Save Draft'}</span>
          </button>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Left 7 Cols: Builder Form */}
        <div className="lg:col-span-7 space-y-6">
          <form onSubmit={handleStartBroadcast} className="enterprise-card p-6 space-y-5">
            <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Sliders className="w-4 h-4 text-emerald-600" />
              1. Campaign Setup
            </h2>

            <div>
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Campaign Name *
              </label>
              <input
                type="text"
                value={campaignName}
                onChange={(e) => setCampaignName(e.target.value)}
                placeholder="e.g. Diwali Flash Sale Announcement"
                className="w-full px-3 py-2 rounded-xl enterprise-input text-xs"
                required
              />
            </div>

            {/* Segmented Message Type Control */}
            <div className="space-y-1.5">
              <label className="text-xs font-semibold text-slate-700 block">
                Message Content Type
              </label>
              <div className="grid grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={() => setMessageType('direct')}
                  className={`p-3 rounded-xl border text-left transition-all cursor-pointer ${
                    messageType === 'direct'
                      ? 'bg-emerald-50/80 border-emerald-500 text-emerald-900 ring-2 ring-emerald-500/10'
                      : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                  }`}
                >
                  <div className="font-bold text-xs">Direct Custom Text</div>
                  <div className="text-[11px] text-slate-500 mt-0.5">
                    With dynamic tags like &#123;&#123;name&#125;&#125;
                  </div>
                </button>

                <button
                  type="button"
                  onClick={() => setMessageType('template')}
                  className={`p-3 rounded-xl border text-left transition-all cursor-pointer ${
                    messageType === 'template'
                      ? 'bg-emerald-50/80 border-emerald-500 text-emerald-900 ring-2 ring-emerald-500/10'
                      : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                  }`}
                >
                  <div className="font-bold text-xs">Meta Approved Template</div>
                  <div className="text-[11px] text-slate-500 mt-0.5">
                    Pre-approved 24hr marketing template
                  </div>
                </button>
              </div>
            </div>

            {messageType === 'direct' ? (
              <div className="space-y-1.5">
                <div className="flex justify-between items-center">
                  <label className="text-xs font-semibold text-slate-700">
                    Message Body
                  </label>
                  <span className="text-[11px] text-emerald-700 font-mono font-medium">
                    Tags: &#123;&#123;name&#125;&#125;, &#123;&#123;phone&#125;&#125;
                  </span>
                </div>
                <textarea
                  rows={4}
                  value={directMessage}
                  onChange={(e) => setDirectMessage(e.target.value)}
                  className="w-full p-3 rounded-xl enterprise-input text-xs font-sans"
                  required
                />
              </div>
            ) : (
              <div className="space-y-2">
                <label className="text-xs font-semibold text-slate-700 block">
                  Select Template
                </label>
                <select
                  value={selectedTemplateId}
                  onChange={(e) => setSelectedTemplateId(e.target.value)}
                  className="w-full p-2.5 rounded-xl enterprise-input text-xs"
                >
                  {templates.map((tmpl) => (
                    <option key={tmpl.id} value={tmpl.id}>
                      {tmpl.name} ({tmpl.language}) - {tmpl.category}
                    </option>
                  ))}
                </select>

                {selectedTemplate && (
                  <div className="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-1">
                    {selectedTemplate.header_text && (
                      <p className="font-bold text-slate-900">
                        {selectedTemplate.header_text}
                      </p>
                    )}
                    <p className="text-slate-700">{selectedTemplate.body_text}</p>
                    {selectedTemplate.footer_text && (
                      <p className="text-[10px] text-slate-500 italic">
                        {selectedTemplate.footer_text}
                      </p>
                    )}
                  </div>
                )}
              </div>
            )}

            {/* Anti-ban throttle delay slider */}
            <div className="space-y-2 pt-2 border-t border-slate-100">
              <div className="flex justify-between items-center text-xs">
                <label className="font-semibold text-slate-700 flex items-center gap-1.5">
                  <Clock className="w-3.5 h-3.5 text-amber-500" />
                  Anti-Ban Delay Throttle
                </label>
                <span className="text-emerald-700 font-mono font-bold bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">
                  {throttleDelay}s / message
                </span>
              </div>
              <input
                type="range"
                min="0.5"
                max="5"
                step="0.5"
                value={throttleDelay}
                onChange={(e) => setThrottleDelay(parseFloat(e.target.value))}
                className="w-full accent-emerald-600 cursor-pointer"
              />
              <p className="text-[11px] text-slate-500">
                Recommended: 2.0s to 3.0s to prevent account flag by WhatsApp Cloud API spam filters.
              </p>
            </div>

            {/* Recipients List with Group Selector & CSV Upload */}
            <div className="space-y-3 pt-2 border-t border-slate-100">
              <div className="flex justify-between items-center text-xs">
                <label className="font-semibold text-slate-700 flex items-center gap-1.5">
                  <Users className="w-3.5 h-3.5 text-cyan-600" />
                  Recipients List
                </label>
                <span className="text-cyan-700 font-mono font-bold bg-cyan-50 px-2 py-0.5 rounded border border-cyan-200">
                  {recipients.length} contacts ready
                </span>
              </div>

              {/* Action bar for CSV and Group Master */}
              <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <input
                  type="file"
                  accept=".csv,.txt"
                  ref={fileInputRef}
                  onChange={handleFileUpload}
                  className="hidden"
                />
                <button
                  type="button"
                  onClick={() => fileInputRef.current?.click()}
                  className="px-3 py-2 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 text-xs font-medium flex items-center justify-center gap-2 cursor-pointer transition-all"
                >
                  <FileSpreadsheet className="w-4 h-4 text-emerald-600" />
                  <span>{uploadedFileName ? `Imported: ${uploadedFileName}` : 'Upload CSV File'}</span>
                </button>

                <div className="flex items-center gap-2 flex-1">
                  <FolderKanban className="w-4 h-4 text-emerald-600 shrink-0" />
                  <select
                    value={selectedGroupId}
                    onChange={(e) => handleGroupSelect(e.target.value)}
                    className="w-full px-3 py-2 rounded-xl enterprise-input text-xs"
                  >
                    <option value="">Load from Group Master Segment...</option>
                    {groups.map((g) => (
                      <option key={g.id} value={g.id}>
                        {g.name} ({g.member_count} contacts)
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <textarea
                rows={5}
                value={phoneListInput}
                onChange={(e) => setPhoneListInput(e.target.value)}
                placeholder="919876543210, John Doe&#10;919123456789, Jane Doe"
                className="w-full p-3 rounded-xl enterprise-input font-mono text-xs"
                required
              />
            </div>

            <button
              type="submit"
              disabled={isDispatching || recipients.length === 0}
              className="w-full py-3 px-6 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-bold text-xs flex items-center justify-center gap-2 shadow-sm transition-all cursor-pointer"
            >
              {isDispatching ? (
                <>
                  <span className="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full" />
                  <span>Transmitting Bulk Broadcast...</span>
                </>
              ) : (
                <>
                  <Play className="w-4 h-4 fill-white" />
                  <span>Start Bulk WhatsApp Blast ({recipients.length} Recipients)</span>
                </>
              )}
            </button>
          </form>
        </div>

        {/* Right 5 Cols: Live Monitor */}
        <div className="lg:col-span-5 space-y-6">
          <div className="enterprise-card p-6 space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                <Sparkles className="w-4 h-4 text-emerald-600" />
                Live Broadcast Monitor
              </h2>
              {isDispatching && (
                <span className="flex items-center gap-1.5 text-xs text-amber-700 bg-amber-50 px-2.5 py-0.5 rounded-full border border-amber-200 font-semibold animate-pulse">
                  <RefreshCw className="w-3 h-3 animate-spin" />
                  Transmitting
                </span>
              )}
            </div>

            {dispatchProgress ? (
              <div className="space-y-4">
                <div className="space-y-1.5">
                  <div className="flex justify-between text-xs text-slate-700 font-medium">
                    <span>
                      Progress:{' '}
                      {Math.round(
                        ((dispatchProgress.sent + dispatchProgress.failed) /
                          dispatchProgress.total) *
                          100
                      )}
                      %
                    </span>
                    <span>
                      {dispatchProgress.sent + dispatchProgress.failed} / {dispatchProgress.total}
                    </span>
                  </div>
                  <div className="w-full h-2.5 bg-slate-100 rounded-full overflow-hidden border border-slate-200">
                    <div
                      className="h-full bg-emerald-500 rounded-full transition-all duration-300"
                      style={{
                        width: `${Math.min(
                          100,
                          ((dispatchProgress.sent + dispatchProgress.failed) /
                            dispatchProgress.total) *
                            100
                        )}%`,
                      }}
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3 text-center">
                  <div className="p-3 rounded-xl bg-emerald-50 border border-emerald-200">
                    <span className="text-xs text-slate-600 block">Sent Successfully</span>
                    <span className="text-2xl font-extrabold text-emerald-700">
                      {dispatchProgress.sent}
                    </span>
                  </div>
                  <div className="p-3 rounded-xl bg-rose-50 border border-rose-200">
                    <span className="text-xs text-slate-600 block">Failed / Skipped</span>
                    <span className="text-2xl font-extrabold text-rose-700">
                      {dispatchProgress.failed}
                    </span>
                  </div>
                </div>

                <div className="space-y-2">
                  <span className="text-xs font-bold text-slate-700 block">
                    Real-Time Dispatch Feed
                  </span>
                  <div className="max-h-72 overflow-y-auto space-y-1.5 p-3 rounded-xl bg-slate-50 border border-slate-200 font-mono text-xs">
                    {dispatchProgress.logs.map((log, idx) => (
                      <div
                        key={idx}
                        className="flex items-center justify-between text-[11px] py-1 border-b border-slate-200/60"
                      >
                        <div className="flex items-center gap-2 truncate">
                          {log.status === 'sent' ? (
                            <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600 shrink-0" />
                          ) : (
                            <AlertCircle className="w-3.5 h-3.5 text-rose-600 shrink-0" />
                          )}
                          <span className="text-slate-800 font-semibold">{log.name}</span>
                          <span className="text-slate-500">({log.phone})</span>
                        </div>
                        <span className="text-slate-500 shrink-0">{log.time}</span>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            ) : (
              <div className="py-12 text-center text-slate-400 space-y-3">
                <div className="w-12 h-12 rounded-full bg-slate-50 border border-slate-200 flex items-center justify-center mx-auto text-slate-400">
                  <Play className="w-5 h-5 ml-0.5 text-emerald-600" />
                </div>
                <p className="text-xs text-slate-500">
                  Configure campaign on the left and click &quot;Start Bulk WhatsApp Blast&quot; to inspect real-time transmission.
                </p>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
