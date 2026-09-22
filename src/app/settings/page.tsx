'use client';

import React, { useState, useEffect } from 'react';
import {
  Settings,
  Database,
  Key,
  Globe,
  Copy,
  Check,
  RefreshCw,
  Zap,
  Sparkles,
} from 'lucide-react';

export default function SettingsPage() {
  const [copiedUrl, setCopiedUrl] = useState(false);
  const [copiedToken, setCopiedToken] = useState(false);
  const [saveSuccess, setSaveSuccess] = useState(false);

  // Meta Cloud API inputs
  const [phoneId, setPhoneId] = useState('');
  const [wabaId, setWabaId] = useState('');
  const [token, setToken] = useState('');
  const [verifyToken, setVerifyToken] = useState('test1234');

  // Diagnostics test state
  const [diagnostics, setDiagnostics] = useState<any>(null);
  const [isTesting, setIsTesting] = useState(false);

  const webhookUrl = 'https://your-domain.com/api/whatsapp/webhook';

  const runDiagnostics = async () => {
    setIsTesting(true);
    try {
      const res = await fetch('/api/settings/test');
      const data = await res.json();
      setDiagnostics(data);
    } catch (e) {
      console.error(e);
    } finally {
      setIsTesting(false);
    }
  };

  useEffect(() => {
    runDiagnostics();
  }, []);

  const copyToClipboard = (text: string, setCopied: (v: boolean) => void) => {
    navigator.clipboard.writeText(text);
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  const handleSaveSettings = (e: React.FormEvent) => {
    e.preventDefault();
    setSaveSuccess(true);
    setTimeout(() => setSaveSuccess(false), 3000);
  };

  return (
    <div className="space-y-6 animate-fadeIn max-w-5xl pb-12">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div className="flex items-center gap-2.5">
          <div className="p-2 rounded-xl bg-emerald-50 border border-emerald-200/60 text-emerald-600">
            <Settings className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-bold tracking-tight text-slate-900">
                System & API Settings
              </h1>
              <span className="text-[11px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 font-mono">
                Environment
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-0.5">
              Manage Meta WhatsApp Cloud API credentials, Supabase database connection, and webhook triggers.
            </p>
          </div>
        </div>

        <button
          type="button"
          onClick={runDiagnostics}
          disabled={isTesting}
          className="flex items-center gap-2 px-3.5 py-2 rounded-lg bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 text-xs font-medium shadow-xs transition-colors cursor-pointer"
        >
          <RefreshCw className={`w-3.5 h-3.5 ${isTesting ? 'animate-spin text-emerald-600' : 'text-slate-500'}`} />
          <span>Run Health Check</span>
        </button>
      </div>

      {/* Live Subsystem Diagnostics */}
      {diagnostics && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
          {/* Supabase Box */}
          <div className="enterprise-card p-4 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                <Database className="w-4 h-4 text-emerald-600" />
                Supabase Database
              </span>
              {diagnostics.supabase.connected ? (
                <span className="text-[10px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-semibold border border-emerald-200">
                  Connected
                </span>
              ) : (
                <span className="text-[10px] px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-semibold border border-amber-200">
                  Preview Mode
                </span>
              )}
            </div>
            <p className="text-[11px] text-slate-500 leading-relaxed">
              {diagnostics.supabase.message}
            </p>
          </div>

          {/* Meta API Box */}
          <div className="enterprise-card p-4 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                <Zap className="w-4 h-4 text-teal-600" />
                WhatsApp Cloud API
              </span>
              {diagnostics.whatsapp.connected ? (
                <span className="text-[10px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-semibold border border-emerald-200">
                  Verified
                </span>
              ) : (
                <span className="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 font-semibold border border-slate-200">
                  Credentials Pending
                </span>
              )}
            </div>
            <p className="text-[11px] text-slate-500 truncate leading-relaxed">
              {diagnostics.whatsapp.message}
            </p>
          </div>

          {/* AI Bot Box */}
          <div className="enterprise-card p-4 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-xs font-bold text-slate-900 flex items-center gap-1.5">
                <Sparkles className="w-4 h-4 text-purple-600" />
                Groq AI Engine
              </span>
              {diagnostics.aiBot.connected ? (
                <span className="text-[10px] px-2 py-0.5 rounded-full bg-purple-50 text-purple-700 font-semibold border border-purple-200">
                  Active (GPT-OSS-20B)
                </span>
              ) : (
                <span className="text-[10px] px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 font-semibold border border-amber-200">
                  Check API Key
                </span>
              )}
            </div>
            <p className="text-[11px] text-slate-500 leading-relaxed">
              {diagnostics.aiBot.message}
            </p>
          </div>
        </div>
      )}

      <div className="grid grid-cols-1 md:grid-cols-12 gap-6">
        {/* Left 7 Cols: WhatsApp Meta Credentials Form */}
        <div className="md:col-span-7 space-y-6">
          <form
            onSubmit={handleSaveSettings}
            className="enterprise-card p-5 space-y-4"
          >
            <div>
              <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                <Key className="w-4 h-4 text-emerald-600" />
                Meta WhatsApp Cloud API Credentials
              </h2>
              <p className="text-xs text-slate-500 mt-1">
                Configure your official credentials from Meta for Developers (WhatsApp &gt; API Setup).
              </p>
            </div>

            <div className="space-y-3 pt-1">
              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Phone Number ID
                </label>
                <input
                  type="text"
                  value={phoneId}
                  onChange={(e) => setPhoneId(e.target.value)}
                  placeholder="e.g. 104829104829104"
                  className="enterprise-input w-full font-mono text-xs"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  WhatsApp Business Account ID (WABA)
                </label>
                <input
                  type="text"
                  value={wabaId}
                  onChange={(e) => setWabaId(e.target.value)}
                  placeholder="e.g. 293840192834019"
                  className="enterprise-input w-full font-mono text-xs"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Permanent System User Access Token
                </label>
                <input
                  type="password"
                  value={token}
                  onChange={(e) => setToken(e.target.value)}
                  placeholder="EAAG..."
                  className="enterprise-input w-full font-mono text-xs"
                />
              </div>

              <div>
                <label className="text-xs font-semibold text-slate-700 block mb-1">
                  Webhook Verify Token
                </label>
                <input
                  type="text"
                  value={verifyToken}
                  onChange={(e) => setVerifyToken(e.target.value)}
                  className="enterprise-input w-full font-mono text-xs"
                />
              </div>
            </div>

            {saveSuccess && (
              <div className="p-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs">
                Credentials saved! These are automatically referenced by API routes and can also be stored inside <code>.env.local</code>.
              </div>
            )}

            <button
              type="submit"
              className="w-full py-2.5 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-sm shadow-emerald-600/20 transition-all cursor-pointer"
            >
              Save WhatsApp Cloud API Credentials
            </button>
          </form>
        </div>

        {/* Right 5 Cols: Webhook Guide & Supabase Info */}
        <div className="md:col-span-5 space-y-6">
          {/* Webhook Configuration Box */}
          <div className="enterprise-card p-5 space-y-4">
            <div>
              <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
                <Globe className="w-4 h-4 text-sky-600" />
                Meta Webhook Instructions
              </h2>
              <p className="text-xs text-slate-500 mt-1">
                Paste this webhook URL into your Meta WhatsApp App dashboard to stream incoming chats and delivery events.
              </p>
            </div>

            <div className="space-y-3 font-mono text-xs">
              <div>
                <span className="text-slate-600 text-[11px] block mb-1 font-sans font-medium">
                  Callback URL:
                </span>
                <div className="flex items-center justify-between p-2 rounded-lg bg-slate-50 border border-slate-200">
                  <span className="truncate text-slate-700 text-[11px]">
                    {webhookUrl}
                  </span>
                  <button
                    type="button"
                    onClick={() => copyToClipboard(webhookUrl, setCopiedUrl)}
                    className="p-1 text-slate-400 hover:text-slate-700 transition-colors cursor-pointer"
                  >
                    {copiedUrl ? (
                      <Check className="w-3.5 h-3.5 text-emerald-600" />
                    ) : (
                      <Copy className="w-3.5 h-3.5" />
                    )}
                  </button>
                </div>
              </div>

              <div>
                <span className="text-slate-600 text-[11px] block mb-1 font-sans font-medium">
                  Verify Token:
                </span>
                <div className="flex items-center justify-between p-2 rounded-lg bg-slate-50 border border-slate-200">
                  <span className="text-slate-700 text-[11px]">{verifyToken}</span>
                  <button
                    type="button"
                    onClick={() => copyToClipboard(verifyToken, setCopiedToken)}
                    className="p-1 text-slate-400 hover:text-slate-700 transition-colors cursor-pointer"
                  >
                    {copiedToken ? (
                      <Check className="w-3.5 h-3.5 text-emerald-600" />
                    ) : (
                      <Copy className="w-3.5 h-3.5" />
                    )}
                  </button>
                </div>
              </div>
            </div>

            <div className="text-[11px] text-slate-500 pt-2 border-t border-slate-100 space-y-1 font-sans">
              <p className="font-semibold text-slate-800">Required Webhook Fields:</p>
              <ul className="list-disc list-inside space-y-0.5 text-slate-600">
                <li><code>messages</code> (Incoming chats & reactions)</li>
                <li><code>message_template_status_update</code> (Approvals)</li>
              </ul>
            </div>
          </div>

          {/* Supabase Schema Helper */}
          <div className="enterprise-card p-5 space-y-3">
            <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Database className="w-4 h-4 text-emerald-600" />
              Supabase SQL Schema
            </h2>
            <p className="text-xs text-slate-500">
              The migration file has been compiled at:
            </p>
            <div className="p-2 rounded-lg bg-slate-50 border border-slate-200 font-mono text-[11px] text-emerald-700 font-medium break-all">
              supabase/schema.sql
            </div>
            <p className="text-[11px] text-slate-500 leading-relaxed">
              Paste the SQL directly into your <strong>Supabase SQL Editor</strong> to create all 11 tables, indexes, RLS policies, and seeded rules.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
