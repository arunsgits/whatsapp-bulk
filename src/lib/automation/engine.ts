import { defaultWhatsAppClient } from '../whatsapp/api';
import { defaultAiResponder } from '../ai/responder';
import { getServiceSupabase, isServerSupabaseConfigured } from '../supabase/server';
import { AutomationRule } from '@/types/database';

export interface AutomationResult {
  triggered: boolean;
  ruleName?: string;
  triggerType?: string;
  responseSent?: string;
  senderType: 'bot_rule' | 'bot_ai' | 'none';
  success: boolean;
  error?: string;
}

export class AutomationEngine {
  /**
   * Check if a message matches any keyword or regex rule
   */
  public matchRule(rule: AutomationRule, text: string): boolean {
    if (!rule.trigger_keywords) return false;

    const lowerText = text.toLowerCase().trim();
    const keywords = rule.trigger_keywords
      .split(',')
      .map((k) => k.trim().toLowerCase())
      .filter(Boolean);

    if (rule.match_mode === 'exact') {
      return keywords.some((k) => lowerText === k);
    }

    if (rule.match_mode === 'starts_with') {
      return keywords.some((k) => lowerText.startsWith(k));
    }

    if (rule.match_mode === 'regex') {
      try {
        const regex = new RegExp(rule.trigger_keywords, 'i');
        return regex.test(text);
      } catch {
        return false;
      }
    }

    // Default 'contains'
    return keywords.some((k) => lowerText.includes(k));
  }

  /**
   * Evaluate inbound message and automatically send appropriate auto-reply
   */
  public async processInboundMessage({
    phone,
    customerName,
    messageText,
    conversationId,
    isFirstMessage,
  }: {
    phone: string;
    customerName?: string;
    messageText: string;
    conversationId: string;
    isFirstMessage: boolean;
  }): Promise<AutomationResult> {
    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    let rules: AutomationRule[] = [];
    let aiConfig: any = null;

    if (isConfigured) {
      try {
        // Fetch active automation rules
        const { data: rulesData } = await supabase
          .from('automation_rules')
          .select('*')
          .eq('is_active', true)
          .order('priority', { ascending: false });

        if (rulesData) rules = rulesData as AutomationRule[];

        // Fetch active AI bot config
        const { data: aiData } = await supabase
          .from('ai_bot_configs')
          .select('*')
          .eq('is_active', true)
          .limit(1)
          .single();

        aiConfig = aiData;
      } catch (err) {
        console.error('Error fetching automation settings from DB:', err);
      }
    } else {
      // Default fallback rules if DB is not yet populated
      rules = [
        {
          id: 'welcome-rule',
          name: 'Welcome Greeting',
          trigger_type: 'welcome',
          match_mode: 'contains',
          response_type: 'text',
          response_message: `👋 Hello ${
            customerName || 'there'
          }! Welcome to our WhatsApp service. How can we help you today?\nReply with *pricing*, *services*, or your question!`,
          priority: 100,
          is_active: true,
          execution_count: 0,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
        {
          id: 'pricing-rule',
          name: 'Pricing Inquiry',
          trigger_type: 'keyword',
          trigger_keywords: 'pricing,price,cost,charges,rate,package',
          match_mode: 'contains',
          response_type: 'text',
          response_message:
            '🏷️ Our WhatsApp bulk broadcast packages start at $29/mo with automated chatbots and live CRM syncing. Would you like to see a live demo?',
          priority: 50,
          is_active: true,
          execution_count: 0,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
        {
          id: 'human-support-rule',
          name: 'Human Support Handover',
          trigger_type: 'keyword',
          trigger_keywords: 'human,agent,support,person,talk,help',
          match_mode: 'contains',
          response_type: 'text',
          response_message:
            '👨‍💼 We have notified a human agent. An advisor will get back to you shortly. Feel free to leave details of your inquiry!',
          priority: 90,
          is_active: true,
          execution_count: 0,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        },
      ];
    }

    // 1. If it's the very first message from the contact, check for Welcome rule
    if (isFirstMessage) {
      const welcomeRule = rules.find((r) => r.trigger_type === 'welcome');
      if (welcomeRule && welcomeRule.response_message) {
        const welcomeText = welcomeRule.response_message.replace(
          /\{\{name\}\}/gi,
          customerName || 'there'
        );

        const sendRes = await defaultWhatsAppClient.sendTextMessage(
          phone,
          welcomeText
        );

        if (isConfigured && conversationId) {
          await supabase.from('messages').insert({
            conversation_id: conversationId,
            direction: 'outgoing',
            message_type: 'text',
            message: welcomeText,
            meta_message_id: sendRes.messageId || null,
            status: sendRes.success ? 'sent' : 'failed',
            sender_type: 'bot_rule',
          });

          try {
            await supabase.rpc('increment_rule_execution', {
              rule_id: welcomeRule.id,
            });
          } catch {}
        }

        return {
          triggered: true,
          ruleName: welcomeRule.name,
          triggerType: 'welcome',
          responseSent: welcomeText,
          senderType: 'bot_rule',
          success: sendRes.success,
          error: sendRes.error,
        };
      }
    }

    // 2. Keyword & FAQ matching rules
    const keywordRules = rules.filter(
      (r) => r.trigger_type === 'keyword' || r.trigger_type === 'faq'
    );

    for (const rule of keywordRules) {
      if (this.matchRule(rule, messageText) && rule.response_message) {
        const replyText = rule.response_message.replace(
          /\{\{name\}\}/gi,
          customerName || 'there'
        );

        const sendRes = await defaultWhatsAppClient.sendTextMessage(
          phone,
          replyText
        );

        if (isConfigured && conversationId) {
          await supabase.from('messages').insert({
            conversation_id: conversationId,
            direction: 'outgoing',
            message_type: 'text',
            message: replyText,
            meta_message_id: sendRes.messageId || null,
            status: sendRes.success ? 'sent' : 'failed',
            sender_type: 'bot_rule',
          });

          try {
            await supabase
              .from('automation_rules')
              .update({
                execution_count: (rule.execution_count || 0) + 1,
                last_executed_at: new Date().toISOString(),
              })
              .eq('id', rule.id);
          } catch {}
        }

        return {
          triggered: true,
          ruleName: rule.name,
          triggerType: 'keyword',
          responseSent: replyText,
          senderType: 'bot_rule',
          success: sendRes.success,
          error: sendRes.error,
        };
      }
    }

    // 3. Fallback: Dynamic AI Responder
    const aiEnabled = aiConfig ? aiConfig.is_active : defaultAiResponder.isAvailable();

    if (aiEnabled) {
      let history: any[] = [];
      if (isConfigured && conversationId) {
        const { data: recentMsgs } = await supabase
          .from('messages')
          .select('direction, message')
          .eq('conversation_id', conversationId)
          .order('created_at', { ascending: false })
          .limit(6);

        if (recentMsgs) {
          history = recentMsgs.reverse().map((m) => ({
            role: m.direction === 'incoming' ? 'user' : 'assistant',
            content: m.message || '',
          }));
        }
      }

      const aiReply = await defaultAiResponder.generateReply(
        messageText,
        history,
        customerName
      );

      if (aiReply) {
        const sendRes = await defaultWhatsAppClient.sendTextMessage(
          phone,
          aiReply
        );

        if (isConfigured && conversationId) {
          await supabase.from('messages').insert({
            conversation_id: conversationId,
            direction: 'outgoing',
            message_type: 'text',
            message: aiReply,
            meta_message_id: sendRes.messageId || null,
            status: sendRes.success ? 'sent' : 'failed',
            sender_type: 'bot_ai',
          });
        }

        return {
          triggered: true,
          ruleName: 'AI Dynamic Responder',
          triggerType: 'fallback_ai',
          responseSent: aiReply,
          senderType: 'bot_ai',
          success: sendRes.success,
          error: sendRes.error,
        };
      }
    }

    return {
      triggered: false,
      senderType: 'none',
      success: true,
    };
  }
}

export const defaultAutomationEngine = new AutomationEngine();
