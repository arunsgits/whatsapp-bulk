'use client';

import React, { useState } from 'react';
import {
  MessageSquare,
  Send,
  Bot,
  UserCheck,
  Check,
  CheckCheck,
  Search,
  Sparkles,
  Phone,
} from 'lucide-react';
import { mockConversations } from '@/lib/mock-data';
import { formatPhoneDisplay } from '@/lib/whatsapp/phone';

export default function InboxPage() {
  const [conversations, setConversations] = useState(mockConversations);
  const [activeConvId, setActiveConvId] = useState(mockConversations[0].id);
  const [replyText, setReplyText] = useState('');
  const [isSending, setIsSending] = useState(false);
  const [searchFilter, setSearchFilter] = useState('');

  const activeConv =
    conversations.find((c) => c.id === activeConvId) || conversations[0];

  const filteredConversations = conversations.filter(
    (c) =>
      c.contact_name?.toLowerCase().includes(searchFilter.toLowerCase()) ||
      c.phone_number.includes(searchFilter) ||
      c.last_message_text?.toLowerCase().includes(searchFilter.toLowerCase())
  );

  const handleSendManualReply = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!replyText.trim() || !activeConv) return;

    const messageText = replyText.trim();
    setIsSending(true);

    try {
      const res = await fetch('/api/whatsapp/send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          phone: activeConv.phone_number,
          message: messageText,
          conversationId: activeConv.id,
        }),
      });

      const data = await res.json();

      const newMsg = {
        id: `msg-${Date.now()}`,
        conversation_id: activeConv.id,
        direction: 'outgoing' as const,
        message_type: 'text' as const,
        message: messageText,
        meta_message_id: data.messageId || null,
        status: (data.success !== false ? 'sent' : 'failed') as any,
        sender_type: 'human_agent' as const,
        created_at: new Date().toISOString(),
      };

      setConversations((prev) =>
        prev.map((c) => {
          if (c.id === activeConv.id) {
            return {
              ...c,
              last_message_text: messageText,
              last_message_at: new Date().toISOString(),
              messages: [...c.messages, newMsg],
            };
          }
          return c;
        })
      );

      setReplyText('');
    } catch (err) {
      console.error(err);
    } finally {
      setIsSending(false);
    }
  };

  const toggleBotForChat = (convId: string) => {
    setConversations((prev) =>
      prev.map((c) => {
        if (c.id === convId) {
          const newStatus = !c.ai_auto_reply_enabled;
          return {
            ...c,
            ai_auto_reply_enabled: newStatus,
            status: newStatus ? 'bot_handled' : 'human_agent',
          };
        }
        return c;
      })
    );
  };

  return (
    <div className="space-y-4 animate-fadeIn h-[calc(100vh-7.5rem)] flex flex-col">
      {/* Header */}
      <div className="flex items-center justify-between pb-3 border-b border-slate-200/80 shrink-0">
        <div className="flex items-center gap-2.5">
          <div className="p-2 rounded-xl bg-emerald-50 border border-emerald-200/60 text-emerald-600">
            <MessageSquare className="w-5 h-5" />
          </div>
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-xl font-bold tracking-tight text-slate-900">
                Live WhatsApp Inbox
              </h1>
              <span className="text-[11px] font-medium px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 border border-slate-200 font-mono">
                {conversations.length} Active Chats
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-0.5">
              Two-way live WhatsApp conversations with AI bot handover and manual human agent replies.
            </p>
          </div>
        </div>

        <div className="flex items-center gap-2">
          <span className="flex items-center gap-1.5 text-xs px-3 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium">
            <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
            Meta Webhook Connected
          </span>
        </div>
      </div>

      {/* Two-Column Chat Workspace */}
      <div className="flex-1 grid grid-cols-1 md:grid-cols-12 gap-5 min-h-0">
        {/* Left 4 Cols: Conversations List */}
        <div className="md:col-span-4 enterprise-card p-0 flex flex-col overflow-hidden">
          {/* Search bar */}
          <div className="p-3 border-b border-slate-100">
            <div className="relative">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
              <input
                type="text"
                value={searchFilter}
                onChange={(e) => setSearchFilter(e.target.value)}
                placeholder="Search chats, name, phone..."
                className="enterprise-input w-full pl-8 pr-3 text-xs h-8"
              />
            </div>
          </div>

          {/* Conversations Scroll Area */}
          <div className="flex-1 overflow-y-auto divide-y divide-slate-100">
            {filteredConversations.map((conv) => {
              const isSelected = conv.id === activeConv.id;

              return (
                <button
                  key={conv.id}
                  onClick={() => setActiveConvId(conv.id)}
                  className={`w-full p-3.5 text-left transition-colors flex items-start gap-3 cursor-pointer ${
                    isSelected
                      ? 'bg-emerald-50/70 border-l-4 border-emerald-600'
                      : 'hover:bg-slate-50'
                  }`}
                >
                  <div className="w-9 h-9 rounded-full bg-slate-100 border border-slate-200 text-slate-700 flex items-center justify-center font-bold text-xs shrink-0">
                    {conv.contact_name?.[0] || 'W'}
                  </div>

                  <div className="flex-1 min-w-0">
                    <div className="flex items-center justify-between">
                      <span className="font-semibold text-xs text-slate-900 truncate">
                        {conv.contact_name || formatPhoneDisplay(conv.phone_number)}
                      </span>
                      <span className="text-[10px] text-slate-400 font-medium">
                        {new Date(conv.last_message_at).toLocaleTimeString([], {
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </span>
                    </div>

                    <div className="text-[11px] text-slate-500 font-mono mt-0.5">
                      {formatPhoneDisplay(conv.phone_number)}
                    </div>

                    <p className="text-xs text-slate-600 truncate mt-1">
                      {conv.last_message_text || 'No messages yet'}
                    </p>

                    <div className="flex items-center gap-1.5 mt-2">
                      {conv.ai_auto_reply_enabled ? (
                        <span className="flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-md bg-purple-50 text-purple-700 border border-purple-200 font-medium">
                          <Bot className="w-3 h-3 text-purple-600" />
                          AI Bot On
                        </span>
                      ) : (
                        <span className="flex items-center gap-1 text-[10px] px-2 py-0.5 rounded-md bg-amber-50 text-amber-700 border border-amber-200 font-medium">
                          <UserCheck className="w-3 h-3 text-amber-600" />
                          Human Agent
                        </span>
                      )}

                      {conv.unread_count > 0 && (
                        <span className="w-4 h-4 rounded-full bg-emerald-600 text-white text-[10px] font-bold flex items-center justify-center ml-auto">
                          {conv.unread_count}
                        </span>
                      )}
                    </div>
                  </div>
                </button>
              );
            })}
          </div>
        </div>

        {/* Right 8 Cols: Active Chat Thread */}
        <div className="md:col-span-8 enterprise-card p-0 flex flex-col overflow-hidden border border-slate-200">
          {/* Active Chat Header */}
          <div className="p-3.5 border-b border-slate-200 flex items-center justify-between bg-white shrink-0">
            <div className="flex items-center gap-3">
              <div className="w-9 h-9 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200 flex items-center justify-center font-bold text-xs">
                {activeConv.contact_name?.[0] || 'W'}
              </div>
              <div>
                <h3 className="font-bold text-xs text-slate-900 flex items-center gap-2">
                  {activeConv.contact_name}
                  <span className="text-[11px] text-slate-500 font-mono font-normal">
                    ({formatPhoneDisplay(activeConv.phone_number)})
                  </span>
                </h3>
                <p className="text-[11px] text-slate-500">
                  Status:{' '}
                  <span className="text-emerald-700 font-medium capitalize">
                    {activeConv.status.replace('_', ' ')}
                  </span>
                </p>
              </div>
            </div>

            {/* AI Bot Toggle */}
            <div className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => toggleBotForChat(activeConv.id)}
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium border transition-colors cursor-pointer ${
                  activeConv.ai_auto_reply_enabled
                    ? 'bg-purple-50 border-purple-200 text-purple-700'
                    : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'
                }`}
              >
                <Bot className="w-3.5 h-3.5" />
                <span>
                  {activeConv.ai_auto_reply_enabled ? 'AI Auto-Reply: Active' : 'AI Auto-Reply: Paused'}
                </span>
              </button>
            </div>
          </div>

          {/* Messages Scroll Area */}
          <div className="flex-1 overflow-y-auto p-4 space-y-3 bg-slate-100/75">
            {activeConv.messages.map((msg) => {
              const isOutgoing = msg.direction === 'outgoing';

              return (
                <div
                  key={msg.id}
                  className={`flex flex-col ${
                    isOutgoing ? 'items-end' : 'items-start'
                  }`}
                >
                  {/* Message sender tag */}
                  {isOutgoing && (
                    <span className="text-[10px] text-slate-500 mb-1 flex items-center gap-1">
                      {msg.sender_type === 'bot_ai' && (
                        <span className="text-purple-700 font-medium bg-purple-50 border border-purple-200 px-1.5 py-0.2 rounded flex items-center gap-1">
                          <Sparkles className="w-3 h-3 text-purple-600" /> AI Bot Reply
                        </span>
                      )}
                      {msg.sender_type === 'bot_rule' && (
                        <span className="text-teal-700 font-medium bg-teal-50 border border-teal-200 px-1.5 py-0.2 rounded flex items-center gap-1">
                          <Bot className="w-3 h-3 text-teal-600" /> Rule Match
                        </span>
                      )}
                      {msg.sender_type === 'human_agent' && (
                        <span className="text-slate-600 font-medium bg-slate-100 border border-slate-200 px-1.5 py-0.2 rounded">
                          Agent Manual
                        </span>
                      )}
                    </span>
                  )}

                  {/* Message bubble */}
                  <div
                    className={`max-w-[75%] p-3 rounded-xl text-xs leading-relaxed ${
                      isOutgoing
                        ? 'wa-bubble-outgoing shadow-xs'
                        : 'wa-bubble-incoming shadow-xs'
                    }`}
                  >
                    <p className="whitespace-pre-wrap">{msg.message}</p>

                    <div
                      className={`flex items-center justify-end gap-1 mt-1 text-[10px] ${
                        isOutgoing ? 'text-emerald-800/70' : 'text-slate-400'
                      }`}
                    >
                      <span>
                        {new Date(msg.created_at).toLocaleTimeString([], {
                          hour: '2-digit',
                          minute: '2-digit',
                        })}
                      </span>

                      {isOutgoing && (
                        <span>
                          {msg.status === 'read' ? (
                            <CheckCheck className="w-3.5 h-3.5 text-sky-600 inline" />
                          ) : msg.status === 'delivered' ? (
                            <CheckCheck className="w-3.5 h-3.5 text-slate-400 inline" />
                          ) : (
                            <Check className="w-3.5 h-3.5 text-slate-400 inline" />
                          )}
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          {/* Chat Reply Input Bar */}
          <form
            onSubmit={handleSendManualReply}
            className="p-3 bg-white border-t border-slate-200 flex items-center gap-2.5 shrink-0"
          >
            <input
              type="text"
              value={replyText}
              onChange={(e) => setReplyText(e.target.value)}
              placeholder={`Send WhatsApp reply to ${
                activeConv.contact_name || activeConv.phone_number
              }...`}
              className="enterprise-input flex-1 text-xs h-9"
            />

            <button
              type="submit"
              disabled={isSending || !replyText.trim()}
              className="px-4 h-9 rounded-lg bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white text-xs font-semibold flex items-center gap-1.5 shadow-sm shadow-emerald-600/20 transition-all cursor-pointer shrink-0"
            >
              {isSending ? (
                <span className="animate-spin w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full" />
              ) : (
                <Send className="w-3.5 h-3.5" />
              )}
              <span>Send</span>
            </button>
          </form>
        </div>
      </div>
    </div>
  );
}
