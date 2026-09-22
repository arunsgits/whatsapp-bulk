'use client';

import React, { useState } from 'react';
import Link from 'next/link';
import {
  Send,
  CheckCheck,
  Eye,
  Bot,
  AlertCircle,
  TrendingUp,
  Clock,
  ArrowUpRight,
  Sparkles,
  Zap,
  Play,
  CheckCircle2,
  Users,
  FolderKanban,
  ShieldCheck,
} from 'lucide-react';
import { mockStats, mockCampaigns, mockConversations, mockAutomationRules } from '@/lib/mock-data';

export default function DashboardPage() {
  const [quickPhone, setQuickPhone] = useState('');
  const [quickMessage, setQuickMessage] = useState('');
  const [isSending, setIsSending] = useState(false);
  const [sendFeedback, setSendFeedback] = useState<{
    type: 'success' | 'error';
    text: string;
  } | null>(null);

  const handleQuickSend = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!quickPhone || !quickMessage) return;

    setIsSending(true);
    setSendFeedback(null);

    try {
      const res = await fetch('/api/whatsapp/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone: quickPhone, message: quickMessage }),
      });
      const data = await res.json();

      if (data.success) {
        setSendFeedback({
          type: 'success',
          text: `WhatsApp message dispatched! Meta ID: ${data.messageId || 'SENT_OK'}`,
        });
        setQuickMessage('');
      } else {
        setSendFeedback({
          type: 'error',
          text: data.error || 'Failed to dispatch WhatsApp message.',
        });
      }
    } catch {
      setSendFeedback({
        type: 'error',
        text: 'Network error connecting to WhatsApp API.',
      });
    } finally {
      setIsSending(false);
    }
  };

  return (
    <div className="space-y-8 animate-fadeIn">
      {/* Top Welcome & Actions Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-slate-200">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold tracking-tight text-slate-900">
              WhatsApp Broadcast & Automation Center
            </h1>
            <span className="hidden md:inline-flex items-center gap-1 text-xs px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-semibold border border-emerald-200">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" />
              Meta Cloud API Ready
            </span>
          </div>
          <p className="text-sm text-slate-600 mt-1">
            Enterprise bulk campaign broadcasting with anti-ban delay throttling & dynamic AI auto-replies.
          </p>
        </div>

        <div className="flex items-center gap-3">
          <Link
            href="/campaigns"
            className="flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs shadow-sm transition-all hover:shadow"
          >
            <Send className="w-4 h-4" />
            <span>Launch Bulk Blast</span>
          </Link>
          <Link
            href="/contacts"
            className="flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-200 font-medium text-xs shadow-xs transition-all"
          >
            <Users className="w-4 h-4 text-slate-500" />
            <span>Contacts</span>
          </Link>
        </div>
      </div>

      {/* Analytics KPI Metrics */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {/* Metric 1 */}
        <div className="enterprise-card enterprise-card-hover p-5 relative overflow-hidden">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              Total Messages Sent
            </span>
            <div className="w-8 h-8 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600 border border-emerald-100">
              <Send className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline gap-2">
            <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
              {mockStats.totalSent.toLocaleString()}
            </span>
            <span className="text-xs text-emerald-700 font-semibold flex items-center">
              <TrendingUp className="w-3 h-3 mr-0.5" /> +18.4%
            </span>
          </div>
          <p className="text-xs text-slate-500 mt-1">Across all broadcast campaigns & bot replies</p>
        </div>

        {/* Metric 2 */}
        <div className="enterprise-card enterprise-card-hover p-5 relative overflow-hidden">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              Delivery Success
            </span>
            <div className="w-8 h-8 rounded-lg bg-teal-50 flex items-center justify-center text-teal-600 border border-teal-100">
              <CheckCheck className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline gap-2">
            <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
              {mockStats.deliveryRate}
            </span>
            <span className="text-xs text-slate-500 font-medium">high deliverability</span>
          </div>
          <p className="text-xs text-slate-500 mt-1">Verified Meta Cloud API delivery receipts</p>
        </div>

        {/* Metric 3 */}
        <div className="enterprise-card enterprise-card-hover p-5 relative overflow-hidden">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              Read Engagement
            </span>
            <div className="w-8 h-8 rounded-lg bg-cyan-50 flex items-center justify-center text-cyan-600 border border-cyan-100">
              <Eye className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline gap-2">
            <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
              {mockStats.readRate}
            </span>
            <span className="text-xs text-cyan-700 font-semibold flex items-center">
              <TrendingUp className="w-3 h-3 mr-0.5" /> +5.2%
            </span>
          </div>
          <p className="text-xs text-slate-500 mt-1">Verified blue tick status callbacks</p>
        </div>

        {/* Metric 4 */}
        <div className="enterprise-card enterprise-card-hover p-5 relative overflow-hidden">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              Bot Auto-Replied
            </span>
            <div className="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center text-purple-600 border border-purple-100">
              <Bot className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline gap-2">
            <span className="text-3xl font-extrabold text-slate-900 tracking-tight">
              {mockStats.botHandledRate}
            </span>
            <span className="text-xs text-purple-700 font-semibold">automated</span>
          </div>
          <p className="text-xs text-slate-500 mt-1">Handled dynamically by rules or AI</p>
        </div>
      </div>

      {/* Two Column Layout: Recent Campaigns & Instant Dispatch */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Left 8 Cols: Active Bulk Campaigns */}
        <div className="lg:col-span-8 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
              <Zap className="w-4 h-4 text-emerald-600" />
              Recent Bulk Campaigns
            </h2>
            <Link
              href="/campaigns"
              className="text-xs text-emerald-600 hover:text-emerald-700 font-semibold flex items-center gap-1"
            >
              View all campaigns <ArrowUpRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          <div className="space-y-3">
            {mockCampaigns.map((camp) => {
              const progress = Math.min(
                100,
                Math.round((camp.sent_count / (camp.total_recipients || 1)) * 100)
              );

              return (
                <div
                  key={camp.id}
                  className="enterprise-card p-5 space-y-3 hover:border-slate-300 transition-all"
                >
                  <div className="flex items-center justify-between">
                    <div>
                      <h3 className="font-bold text-sm text-slate-900">{camp.name}</h3>
                      <p className="text-xs text-slate-500 mt-0.5">
                        {camp.template
                          ? `Template: ${camp.template.name} (${camp.template.language})`
                          : 'Direct Broadcast'}
                        {' • '}
                        <span className="font-mono text-slate-600 font-medium">
                          {camp.throttle_delay_sec}s delay
                        </span>
                      </p>
                    </div>

                    <span
                      className={`text-[11px] font-semibold px-2.5 py-1 rounded-full uppercase tracking-wide ${
                        camp.status === 'completed'
                          ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                          : camp.status === 'processing'
                          ? 'bg-amber-50 text-amber-700 border border-amber-200 animate-pulse'
                          : 'bg-slate-100 text-slate-700 border border-slate-200'
                      }`}
                    >
                      {camp.status}
                    </span>
                  </div>

                  {/* Progress Bar */}
                  <div className="space-y-1.5">
                    <div className="flex justify-between text-xs text-slate-600">
                      <span>Progress: {progress}%</span>
                      <span>
                        <strong className="text-slate-900">{camp.sent_count}</strong> of{' '}
                        {camp.total_recipients} dispatched
                      </span>
                    </div>
                    <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden border border-slate-200/60">
                      <div
                        className={`h-full rounded-full transition-all duration-500 ${
                          camp.status === 'completed'
                            ? 'bg-emerald-500'
                            : 'bg-amber-500'
                        }`}
                        style={{ width: `${progress}%` }}
                      />
                    </div>
                  </div>

                  {/* Sub Stats */}
                  <div className="grid grid-cols-3 gap-2 pt-2 border-t border-slate-100 text-center text-xs">
                    <div>
                      <span className="text-slate-500">Delivered:</span>{' '}
                      <span className="text-emerald-700 font-semibold">
                        {camp.delivered_count}
                      </span>
                    </div>
                    <div>
                      <span className="text-slate-500">Read:</span>{' '}
                      <span className="text-cyan-700 font-semibold">{camp.read_count}</span>
                    </div>
                    <div>
                      <span className="text-slate-500">Failed:</span>{' '}
                      <span className="text-rose-600 font-semibold">{camp.failed_count}</span>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Right 4 Cols: Quick Single Dispatcher & Quick Links */}
        <div className="lg:col-span-4 space-y-6">
          <div className="enterprise-card p-5 space-y-4">
            <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Send className="w-4 h-4 text-emerald-600" />
              Quick WhatsApp Dispatch
            </h2>
            <p className="text-xs text-slate-500">
              Instantly test message delivery with any verified phone number before launching a full blast.
            </p>

            <form onSubmit={handleQuickSend} className="space-y-3">
              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Recipient Number
                </label>
                <input
                  type="text"
                  value={quickPhone}
                  onChange={(e) => setQuickPhone(e.target.value)}
                  placeholder="e.g. 919876543210"
                  className="w-full px-3 py-2 rounded-xl enterprise-input text-xs font-mono"
                  required
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Message Body
                </label>
                <textarea
                  rows={3}
                  value={quickMessage}
                  onChange={(e) => setQuickMessage(e.target.value)}
                  placeholder="Type a test broadcast message..."
                  className="w-full px-3 py-2 rounded-xl enterprise-input text-xs resize-none"
                  required
                />
              </div>

              {sendFeedback && (
                <div
                  className={`p-3 rounded-xl text-xs flex items-start gap-2 ${
                    sendFeedback.type === 'success'
                      ? 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                      : 'bg-rose-50 text-rose-800 border border-rose-200'
                  }`}
                >
                  {sendFeedback.type === 'success' ? (
                    <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600 mt-0.5" />
                  ) : (
                    <AlertCircle className="w-4 h-4 shrink-0 text-rose-600 mt-0.5" />
                  )}
                  <span>{sendFeedback.text}</span>
                </div>
              )}

              <button
                type="submit"
                disabled={isSending}
                className="w-full py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white font-semibold text-xs flex items-center justify-center gap-2 shadow-sm transition-all cursor-pointer"
              >
                {isSending ? (
                  <span className="animate-spin w-4 h-4 border-2 border-white border-t-transparent rounded-full" />
                ) : (
                  <Send className="w-4 h-4" />
                )}
                <span>{isSending ? 'Dispatching...' : 'Send WhatsApp Message'}</span>
              </button>
            </form>
          </div>

          {/* Quick Segment Cards */}
          <div className="enterprise-card p-5 space-y-3">
            <div className="flex items-center justify-between text-xs">
              <span className="text-slate-800 font-bold flex items-center gap-1.5">
                <FolderKanban className="w-4 h-4 text-emerald-600" />
                Quick Target Segments
              </span>
              <Link
                href="/groups"
                className="text-emerald-600 hover:underline font-semibold"
              >
                Manage
              </Link>
            </div>

            <div className="space-y-2 text-xs">
              <Link
                href="/campaigns"
                className="p-2.5 rounded-xl bg-slate-50 hover:bg-emerald-50/60 border border-slate-200/60 flex items-center justify-between transition-colors block"
              >
                <span className="font-semibold text-slate-800">VIP Retail Clients</span>
                <span className="text-[11px] font-mono text-emerald-700 font-bold">145 contacts</span>
              </Link>

              <Link
                href="/campaigns"
                className="p-2.5 rounded-xl bg-slate-50 hover:bg-emerald-50/60 border border-slate-200/60 flex items-center justify-between transition-colors block"
              >
                <span className="font-semibold text-slate-800">Salon & Spa Leads</span>
                <span className="text-[11px] font-mono text-emerald-700 font-bold">320 contacts</span>
              </Link>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
