export type Json =
  | string
  | number
  | boolean
  | null
  | { [key: string]: Json | undefined }
  | Json[];

export interface WhatsappConfig {
  id: string;
  phone_number_id: string;
  business_account_id?: string | null;
  access_token: string;
  verify_token: string;
  api_version: string;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

export interface Contact {
  id: string;
  name: string;
  phone: string;
  email?: string | null;
  tags?: string[];
  custom_attributes?: Record<string, any>;
  status: string;
  created_at: string;
  updated_at: string;
}

export interface ContactGroup {
  id: string;
  name: string;
  description?: string | null;
  created_at: string;
  member_count?: number;
}

export interface WhatsappTemplate {
  id: string;
  name: string;
  language: string;
  category: string;
  status: 'APPROVED' | 'PENDING' | 'REJECTED';
  body_text: string;
  header_text?: string | null;
  footer_text?: string | null;
  variable_count: number;
  components?: any[];
  created_at: string;
  updated_at: string;
}

export interface Campaign {
  id: string;
  name: string;
  status: 'draft' | 'scheduled' | 'processing' | 'completed' | 'paused' | 'failed';
  template_id?: string | null;
  direct_message?: string | null;
  media_url?: string | null;
  media_type?: string | null;
  total_recipients: number;
  sent_count: number;
  delivered_count: number;
  read_count: number;
  failed_count: number;
  throttle_delay_sec: number;
  filter_tags?: string[];
  scheduled_at?: string | null;
  started_at?: string | null;
  completed_at?: string | null;
  created_at: string;
  updated_at: string;
  template?: WhatsappTemplate;
}

export interface CampaignRecipient {
  id: string;
  campaign_id: string;
  contact_id?: string | null;
  phone: string;
  name?: string | null;
  variables?: Record<string, any>;
  status: 'pending' | 'sending' | 'sent' | 'delivered' | 'read' | 'failed' | 'skipped';
  error_message?: string | null;
  meta_message_id?: string | null;
  sent_at?: string | null;
  delivered_at?: string | null;
  read_at?: string | null;
  created_at: string;
}

export interface Conversation {
  id: string;
  phone_number: string;
  contact_name?: string | null;
  contact_id?: string | null;
  unread_count: number;
  status: 'open' | 'resolved' | 'bot_handled' | 'human_agent';
  ai_auto_reply_enabled: boolean;
  last_message_text?: string | null;
  last_message_at: string;
  metadata?: Record<string, any>;
  created_at: string;
  updated_at: string;
}

export interface Message {
  id: string;
  conversation_id: string;
  direction: 'incoming' | 'outgoing';
  message_type: 'text' | 'template' | 'image' | 'document' | 'video' | 'interactive' | 'reaction';
  message?: string | null;
  media_url?: string | null;
  media_type?: string | null;
  meta_message_id?: string | null;
  status: 'pending' | 'sent' | 'delivered' | 'read' | 'failed';
  sender_type: 'user' | 'system_campaign' | 'bot_rule' | 'bot_ai' | 'human_agent';
  error_message?: string | null;
  metadata?: Record<string, any>;
  created_at: string;
}

export interface AutomationRule {
  id: string;
  name: string;
  trigger_type: 'welcome' | 'keyword' | 'regex' | 'faq' | 'fallback_ai';
  trigger_keywords?: string | null;
  match_mode: 'exact' | 'contains' | 'starts_with' | 'regex';
  response_type: 'text' | 'template';
  response_message?: string | null;
  template_id?: string | null;
  priority: number;
  is_active: boolean;
  execution_count: number;
  last_executed_at?: string | null;
  conditions?: Record<string, any>;
  created_at: string;
  updated_at: string;
  template?: WhatsappTemplate;
}

export interface AiBotConfig {
  id: string;
  provider: 'groq' | 'openai' | 'deepseek';
  api_key?: string | null;
  base_url: string;
  model: string;
  system_prompt: string;
  business_context?: string | null;
  temperature: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}
