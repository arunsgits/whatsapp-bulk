'use client';

import React, { useState } from 'react';
import {
  Bot,
  Sparkles,
  Plus,
  Play,
  CheckCircle2,
  Sliders,
  Trash2,
  HelpCircle,
  Cpu,
} from 'lucide-react';
import { mockAutomationRules } from '@/lib/mock-data';
import { AutomationRule } from '@/types/database';

export default function AutomationPage() {
  const [rules, setRules] = useState<AutomationRule[]>(mockAutomationRules);
  const [testInput, setTestInput] = useState('Hi, what are your pricing plans?');
  const [testResult, setTestResult] = useState<any>(null);
  const [isTesting, setIsTesting] = useState(false);

  // New Rule Form
  const [isCreatingRule, setIsCreatingRule] = useState(false);
  const [newRuleName, setNewRuleName] = useState('');
  const [newRuleType, setNewRuleType] = useState<'keyword' | 'welcome' | 'faq'>('keyword');
  const [newRuleKeywords, setNewRuleKeywords] = useState('');
  const [newRuleResponse, setNewRuleResponse] = useState('');

  // AI Settings State
  const [aiSystemPrompt, setAiSystemPrompt] = useState(
    'You are a polite, helpful WhatsApp assistant for our business. Keep your replies concise (under 2-3 sentences), friendly, professional, and directly answer questions. If you cannot answer, offer to connect them with a human agent.'
  );
  const [aiBusinessContext, setAiBusinessContext] = useState(
    'We provide WhatsApp automation, bulk messaging, and CRM solutions for businesses.'
  );
  const [aiModel, setAiModel] = useState('openai/gpt-oss-20b');
  const [aiSavedNotice, setAiSavedNotice] = useState(false);

  const handleTestSimulation = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!testInput.trim()) return;

    setIsTesting(true);
    setTestResult(null);

    try {
      const res = await fetch('/api/automation/test', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: testInput, customerName: 'Rahul' }),
      });
      const data = await res.json();
      setTestResult(data);
    } catch (err: any) {
      setTestResult({
        simulation: {
          triggered: false,
          error: err.message,
        },
      });
    } finally {
      setIsTesting(false);
    }
  };

  const handleCreateRule = (e: React.FormEvent) => {
    e.preventDefault();
    if (!newRuleName || !newRuleResponse) return;

    const newRule: AutomationRule = {
      id: `rule-${Date.now()}`,
      name: newRuleName,
      trigger_type: newRuleType,
      trigger_keywords: newRuleType === 'welcome' ? null : newRuleKeywords,
      match_mode: 'contains',
      response_type: 'text',
      response_message: newRuleResponse,
      priority: newRuleType === 'welcome' ? 100 : 50,
      is_active: true,
      execution_count: 0,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    setRules([newRule, ...rules]);
    setNewRuleName('');
    setNewRuleKeywords('');
    setNewRuleResponse('');
    setIsCreatingRule(false);
  };

  const toggleRuleActive = (id: string) => {
    setRules((prev) =>
      prev.map((r) => (r.id === id ? { ...r, is_active: !r.is_active } : r))
    );
  };

  const deleteRule = (id: string) => {
    setRules((prev) => prev.filter((r) => r.id !== id));
  };

  const handleSaveAiConfig = () => {
    setAiSavedNotice(true);
    setTimeout(() => setAiSavedNotice(false), 3000);
  };

  return (
    <div className="space-y-6 animate-fadeIn pb-12">
      {/* Header */}
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-200/80">
        <div>
          <div className="flex items-center gap-2.5">
            <div className="p-2 rounded-xl bg-purple-50 border border-purple-200/60 text-purple-600">
              <Bot className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center gap-2">
                <h1 className="text-xl font-bold tracking-tight text-slate-900">
                  Dynamic WhatsApp Auto-Reply & AI Bot
                </h1>
                <span className="text-[11px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 font-mono">
                  {rules.length} Active Rules
                </span>
              </div>
              <p className="text-xs text-slate-500 mt-0.5">
                Build keyword triggers, instant welcome messages, and dynamic AI persona fallbacks.
              </p>
            </div>
          </div>
        </div>

        <button
          onClick={() => setIsCreatingRule(!isCreatingRule)}
          className="flex items-center gap-2 px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs shadow-sm shadow-emerald-600/20 transition-all cursor-pointer"
        >
          <Plus className="w-4 h-4" />
          <span>Add Auto-Reply Rule</span>
        </button>
      </div>

      {/* Interactive Simulation Sandbox */}
      <div className="enterprise-card space-y-4">
        <div className="flex items-center justify-between">
          <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
            <Play className="w-4 h-4 text-emerald-600 fill-emerald-600" />
            Live Auto-Reply Simulation Sandbox
          </h2>
          <span className="text-xs text-slate-500">
            Simulate how incoming customer messages are processed by rules or AI in real time.
          </span>
        </div>

        <form onSubmit={handleTestSimulation} className="flex gap-2.5">
          <input
            type="text"
            value={testInput}
            onChange={(e) => setTestInput(e.target.value)}
            placeholder="Type a simulated WhatsApp message, e.g., 'What is your price?' or 'I need help'..."
            className="enterprise-input flex-1 text-xs h-9"
          />
          <button
            type="submit"
            disabled={isTesting}
            className="px-4 h-9 rounded-lg bg-purple-600 hover:bg-purple-700 disabled:opacity-50 text-white text-xs font-semibold flex items-center gap-2 shadow-sm shadow-purple-600/20 transition-all cursor-pointer shrink-0"
          >
            {isTesting ? (
              <span className="animate-spin w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full" />
            ) : (
              <Sparkles className="w-3.5 h-3.5" />
            )}
            <span>Run Test</span>
          </button>
        </form>

        {testResult && (
          <div className="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-3 font-mono text-xs animate-fadeIn">
            <div className="flex items-center justify-between border-b border-slate-200 pb-2">
              <span className="text-slate-500 font-medium">Evaluation Routing:</span>
              <span
                className={`px-2.5 py-0.5 rounded-full font-bold uppercase text-[10px] ${
                  testResult.simulation?.senderType === 'bot_ai'
                    ? 'bg-purple-100 text-purple-800 border border-purple-200'
                    : testResult.simulation?.senderType === 'bot_rule'
                    ? 'bg-emerald-100 text-emerald-800 border border-emerald-200'
                    : 'bg-slate-200 text-slate-700'
                }`}
              >
                {testResult.simulation?.senderType === 'bot_ai'
                  ? 'Dynamic AI Fallback Response'
                  : testResult.simulation?.senderType === 'bot_rule'
                  ? `Matched Rule: ${testResult.simulation.ruleName}`
                  : 'No Match'}
              </span>
            </div>

            <div>
              <span className="text-slate-500 block mb-1 text-[11px] font-sans">
                Outgoing Simulated Reply:
              </span>
              <div className="p-3 rounded-lg bg-white border border-slate-200 text-slate-800 font-sans text-xs whitespace-pre-wrap leading-relaxed shadow-xs">
                {testResult.simulation?.responseSent || 'No reply generated.'}
              </div>
            </div>
          </div>
        )}
      </div>

      {/* New Rule Inline Form */}
      {isCreatingRule && (
        <form
          onSubmit={handleCreateRule}
          className="enterprise-card border-emerald-300 bg-emerald-50/20 space-y-4 animate-fadeIn"
        >
          <div className="flex justify-between items-center pb-2 border-b border-slate-200/80">
            <h3 className="font-bold text-slate-900 text-sm flex items-center gap-2">
              <Plus className="w-4 h-4 text-emerald-600" />
              Create New Automation Rule
            </h3>
            <button
              type="button"
              onClick={() => setIsCreatingRule(false)}
              className="text-xs text-slate-500 hover:text-slate-800 cursor-pointer"
            >
              Cancel
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Rule Name <span className="text-rose-500">*</span>
              </label>
              <input
                type="text"
                value={newRuleName}
                onChange={(e) => setNewRuleName(e.target.value)}
                placeholder="e.g. Refund Policy Inquiry"
                className="enterprise-input w-full text-xs"
                required
              />
            </div>

            <div>
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Trigger Type
              </label>
              <select
                value={newRuleType}
                onChange={(e) => setNewRuleType(e.target.value as any)}
                className="enterprise-input w-full text-xs cursor-pointer"
              >
                <option value="keyword">Keyword Contains</option>
                <option value="faq">FAQ</option>
                <option value="welcome">First Message Welcome Greeting</option>
              </select>
            </div>
          </div>

          {newRuleType !== 'welcome' && (
            <div>
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Trigger Keywords (comma-separated)
              </label>
              <input
                type="text"
                value={newRuleKeywords}
                onChange={(e) => setNewRuleKeywords(e.target.value)}
                placeholder="e.g. refund, return, money back, policy"
                className="enterprise-input w-full font-mono text-xs"
                required
              />
            </div>
          )}

          <div>
            <label className="text-xs font-semibold text-slate-700 block mb-1">
              WhatsApp Reply Content
            </label>
            <textarea
              rows={3}
              value={newRuleResponse}
              onChange={(e) => setNewRuleResponse(e.target.value)}
              placeholder="Hi {{name}}, our refund window is 14 days without questions asked..."
              className="enterprise-input w-full text-xs resize-none"
              required
            />
          </div>

          <div className="flex justify-end gap-2">
            <button
              type="button"
              onClick={() => setIsCreatingRule(false)}
              className="px-4 py-2 rounded-lg text-slate-600 hover:bg-slate-100 font-medium text-xs transition-colors cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs shadow-sm shadow-emerald-600/20 transition-all cursor-pointer"
            >
              Save Automation Rule
            </button>
          </div>
        </form>
      )}

      {/* Two Column Layout: Rules List & Dynamic AI Persona Setup */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left 7 Cols: Rules List */}
        <div className="lg:col-span-7 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Sliders className="w-4 h-4 text-emerald-600" />
              Static Rules & Keyword Handlers ({rules.length})
            </h2>
          </div>

          <div className="space-y-3">
            {rules.map((rule) => (
              <div
                key={rule.id}
                className="enterprise-card p-4 space-y-2.5 hover:border-slate-300 transition-colors"
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span className="font-bold text-xs text-slate-900">
                      {rule.name}
                    </span>
                    <span
                      className={`text-[10px] px-2 py-0.5 rounded-md font-semibold uppercase ${
                        rule.trigger_type === 'welcome'
                          ? 'bg-amber-50 text-amber-800 border border-amber-200'
                          : 'bg-emerald-50 text-emerald-800 border border-emerald-200'
                      }`}
                    >
                      {rule.trigger_type}
                    </span>
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => toggleRuleActive(rule.id)}
                      className="text-xs transition-colors cursor-pointer"
                    >
                      {rule.is_active ? (
                        <span className="text-emerald-700 font-semibold text-[11px] px-2 py-0.5 rounded bg-emerald-50 border border-emerald-200">
                          Active
                        </span>
                      ) : (
                        <span className="text-slate-500 text-[11px] px-2 py-0.5 rounded bg-slate-100 border border-slate-200">
                          Paused
                        </span>
                      )}
                    </button>
                    <button
                      type="button"
                      onClick={() => deleteRule(rule.id)}
                      className="text-slate-400 hover:text-rose-600 p-1 transition-colors cursor-pointer"
                      title="Delete rule"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>

                {rule.trigger_keywords && (
                  <p className="text-[11px] text-slate-500 font-mono">
                    Keywords: <span className="text-slate-800 font-medium">{rule.trigger_keywords}</span>
                  </p>
                )}

                <div className="text-xs text-slate-700 p-2.5 rounded-lg bg-slate-50 border border-slate-200/80 leading-relaxed">
                  {rule.response_message}
                </div>

                <div className="flex justify-between text-[10px] text-slate-400 pt-0.5">
                  <span>Priority: {rule.priority}</span>
                  <span>Triggered: {rule.execution_count} times</span>
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Right 5 Cols: Dynamic AI Fallback Config */}
        <div className="lg:col-span-5 space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-sm font-bold text-slate-900 flex items-center gap-2">
              <Sparkles className="w-4 h-4 text-purple-600" />
              Dynamic AI Fallback Persona
            </h2>
          </div>

          <div className="enterprise-card p-5 space-y-4">
            <p className="text-xs text-slate-500 leading-relaxed">
              When a user message does not match any keyword rule, the dynamic AI bot takes over with instant, natural responses.
            </p>

            <div>
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                AI Model Engine
              </label>
              <input
                type="text"
                value={aiModel}
                onChange={(e) => setAiModel(e.target.value)}
                className="enterprise-input w-full font-mono text-xs"
              />
            </div>

            <div>
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                System Prompt & Persona Tone
              </label>
              <textarea
                rows={3}
                value={aiSystemPrompt}
                onChange={(e) => setAiSystemPrompt(e.target.value)}
                className="enterprise-input w-full text-xs resize-none"
              />
            </div>

            <div>
              <label className="text-xs font-semibold text-slate-700 block mb-1">
                Business Knowledge Base & FAQs
              </label>
              <textarea
                rows={4}
                value={aiBusinessContext}
                onChange={(e) => setAiBusinessContext(e.target.value)}
                placeholder="Describe your business offerings, pricing tiers, FAQs..."
                className="enterprise-input w-full text-xs resize-none"
              />
            </div>

            {aiSavedNotice && (
              <div className="p-2.5 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs flex items-center gap-2">
                <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                <span>AI Persona configuration updated!</span>
              </div>
            )}

            <button
              type="button"
              onClick={handleSaveAiConfig}
              className="w-full py-2.5 px-4 rounded-lg bg-purple-600 hover:bg-purple-700 text-white font-medium text-xs shadow-sm shadow-purple-600/20 transition-all cursor-pointer"
            >
              Save AI Persona Configuration
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
