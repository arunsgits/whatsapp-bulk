-- ==============================================================================
-- WhatsApp Bulk Message Sent System & Dynamic Auto-Reply Engine Schema for Supabase
-- ==============================================================================

-- Enable UUID generation extension if not enabled
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- 1. WhatsApp Cloud API Configuration
CREATE TABLE IF NOT EXISTS whatsapp_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    phone_number_id TEXT NOT NULL,
    business_account_id TEXT,
    access_token TEXT NOT NULL,
    verify_token TEXT NOT NULL DEFAULT 'test1234',
    api_version TEXT NOT NULL DEFAULT 'v19.0',
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- 2. Contacts Table
CREATE TABLE IF NOT EXISTS contacts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    phone TEXT NOT NULL UNIQUE,
    email TEXT,
    tags TEXT[] DEFAULT '{}',
    custom_attributes JSONB DEFAULT '{}'::jsonb,
    status TEXT NOT NULL DEFAULT 'active',
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_contacts_phone ON contacts (phone);
CREATE INDEX IF NOT EXISTS idx_contacts_tags ON contacts USING GIN (tags);

-- 3. Contact Groups
CREATE TABLE IF NOT EXISTS contact_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS contact_group_members (
    group_id UUID NOT NULL REFERENCES contact_groups(id) ON DELETE CASCADE,
    contact_id UUID NOT NULL REFERENCES contacts(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (group_id, contact_id)
);

-- 4. WhatsApp Message Templates
CREATE TABLE IF NOT EXISTS whatsapp_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    language TEXT NOT NULL DEFAULT 'en',
    category TEXT DEFAULT 'MARKETING',
    status TEXT NOT NULL DEFAULT 'APPROVED',
    body_text TEXT NOT NULL,
    header_text TEXT,
    footer_text TEXT,
    variable_count INT NOT NULL DEFAULT 0,
    components JSONB DEFAULT '[]'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    CONSTRAINT unique_template_name_lang UNIQUE (name, language)
);

-- 5. Bulk Messaging Campaigns
CREATE TABLE IF NOT EXISTS campaigns (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    status TEXT NOT NULL DEFAULT 'draft', -- draft, scheduled, processing, completed, paused, failed
    template_id UUID REFERENCES whatsapp_templates(id) ON DELETE SET NULL,
    direct_message TEXT,
    media_url TEXT,
    media_type TEXT,
    total_recipients INT NOT NULL DEFAULT 0,
    sent_count INT NOT NULL DEFAULT 0,
    delivered_count INT NOT NULL DEFAULT 0,
    read_count INT NOT NULL DEFAULT 0,
    failed_count INT NOT NULL DEFAULT 0,
    throttle_delay_sec NUMERIC(4,1) NOT NULL DEFAULT 2.0, -- Anti-ban delay between sends
    filter_tags TEXT[] DEFAULT '{}',
    scheduled_at TIMESTAMPTZ,
    started_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_campaigns_status ON campaigns (status, created_at DESC);

-- 6. Campaign Recipients & Dispatch Logs
CREATE TABLE IF NOT EXISTS campaign_recipients (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    campaign_id UUID NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
    contact_id UUID REFERENCES contacts(id) ON DELETE SET NULL,
    phone TEXT NOT NULL,
    name TEXT,
    variables JSONB DEFAULT '{}'::jsonb,
    status TEXT NOT NULL DEFAULT 'pending', -- pending, sending, sent, delivered, read, failed, skipped
    error_message TEXT,
    meta_message_id TEXT,
    sent_at TIMESTAMPTZ,
    delivered_at TIMESTAMPTZ,
    read_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_camp_recip_status ON campaign_recipients (campaign_id, status);
CREATE INDEX IF NOT EXISTS idx_camp_recip_meta_id ON campaign_recipients (meta_message_id);

-- 7. WhatsApp Conversations (Inbox Threads)
CREATE TABLE IF NOT EXISTS conversations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    phone_number TEXT NOT NULL UNIQUE,
    contact_name TEXT,
    contact_id UUID REFERENCES contacts(id) ON DELETE SET NULL,
    unread_count INT NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'open', -- open, resolved, bot_handled, human_agent
    ai_auto_reply_enabled BOOLEAN NOT NULL DEFAULT true,
    last_message_text TEXT,
    last_message_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_conversations_last_msg ON conversations (last_message_at DESC);

-- 8. Messages (Chat and Log History)
CREATE TABLE IF NOT EXISTS messages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    conversation_id UUID NOT NULL REFERENCES conversations(id) ON DELETE CASCADE,
    direction TEXT NOT NULL, -- 'incoming' or 'outgoing'
    message_type TEXT NOT NULL DEFAULT 'text', -- text, template, image, document, video, interactive, reaction
    message TEXT,
    media_url TEXT,
    media_type TEXT,
    meta_message_id TEXT UNIQUE,
    status TEXT NOT NULL DEFAULT 'sent', -- pending, sent, delivered, read, failed
    sender_type TEXT NOT NULL DEFAULT 'user', -- user, system_campaign, bot_rule, bot_ai, human_agent
    error_message TEXT,
    metadata JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_messages_conversation ON messages (conversation_id, created_at ASC);
CREATE INDEX IF NOT EXISTS idx_messages_meta_id ON messages (meta_message_id);

-- 9. Dynamic Automation & Auto-Reply Rules
CREATE TABLE IF NOT EXISTS automation_rules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name TEXT NOT NULL,
    trigger_type TEXT NOT NULL DEFAULT 'keyword', -- welcome, keyword, regex, faq, fallback_ai
    trigger_keywords TEXT, -- comma-separated keywords or regex string
    match_mode TEXT NOT NULL DEFAULT 'contains', -- exact, contains, starts_with, regex
    response_type TEXT NOT NULL DEFAULT 'text', -- text, template
    response_message TEXT,
    template_id UUID REFERENCES whatsapp_templates(id) ON DELETE SET NULL,
    priority INT NOT NULL DEFAULT 10,
    is_active BOOLEAN NOT NULL DEFAULT true,
    execution_count INT NOT NULL DEFAULT 0,
    last_executed_at TIMESTAMPTZ,
    conditions JSONB DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS idx_automation_active_prio ON automation_rules (is_active, priority DESC);

-- Helper function: Increment rule execution atomically
CREATE OR REPLACE FUNCTION increment_rule_execution(rule_id UUID)
RETURNS VOID AS $$
BEGIN
    UPDATE automation_rules
    SET execution_count = execution_count + 1,
        last_executed_at = now()
    WHERE id = rule_id;
END;
$$ LANGUAGE plpgsql;

-- 10. AI Bot Settings
CREATE TABLE IF NOT EXISTS ai_bot_configs (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    provider TEXT NOT NULL DEFAULT 'groq', -- groq, openai, deepseek
    api_key TEXT,
    base_url TEXT NOT NULL DEFAULT 'https://api.groq.com/openai/v1',
    model TEXT NOT NULL DEFAULT 'openai/gpt-oss-20b',
    system_prompt TEXT NOT NULL DEFAULT 'You are a warm, helpful WhatsApp assistant. Answer customer queries accurately, politely, and keep answers concise (under 2-3 sentences). If you do not know the answer or if the user asks for a representative, politely inform them a human agent will follow up shortly.',
    business_context TEXT DEFAULT 'We provide WhatsApp automation, bulk messaging, and CRM solutions for businesses.',
    temperature NUMERIC(2,1) NOT NULL DEFAULT 0.7,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- Seed sample automation rules & AI config
INSERT INTO automation_rules (name, trigger_type, trigger_keywords, match_mode, response_message, priority, is_active)
VALUES 
    ('Welcome Greeting', 'welcome', '', 'contains', '👋 Hello! Welcome to our WhatsApp service. How can we help you today? Reply with "info", "pricing", or type your question!', 100, true),
    ('Pricing Inquiry', 'keyword', 'pricing,price,cost,charges,plan', 'contains', '🏷️ Our WhatsApp Bulk Sending & CRM packages start from $29/mo with unlimited campaigns and auto-replies. Would you like a product demo?', 50, true),
    ('Human Support Handover', 'keyword', 'agent,human,support,talk to person,help', 'contains', '👨‍💼 An agent has been notified and will respond to your message shortly. Please leave your question or requirements below.', 90, true)
ON CONFLICT DO NOTHING;

INSERT INTO ai_bot_configs (provider, base_url, model, is_active)
VALUES ('groq', 'https://api.groq.com/openai/v1', 'openai/gpt-oss-20b', true)
ON CONFLICT DO NOTHING;

-- Seed default WhatsApp configuration placeholder
INSERT INTO whatsapp_configs (phone_number_id, access_token, verify_token)
VALUES ('YOUR_PHONE_NUMBER_ID', 'YOUR_META_ACCESS_TOKEN', 'test1234')
ON CONFLICT DO NOTHING;

-- Enable Row Level Security (RLS) with full permissive access policies
ALTER TABLE whatsapp_configs ENABLE ROW LEVEL SECURITY;
ALTER TABLE contacts ENABLE ROW LEVEL SECURITY;
ALTER TABLE contact_groups ENABLE ROW LEVEL SECURITY;
ALTER TABLE contact_group_members ENABLE ROW LEVEL SECURITY;
ALTER TABLE whatsapp_templates ENABLE ROW LEVEL SECURITY;
ALTER TABLE campaigns ENABLE ROW LEVEL SECURITY;
ALTER TABLE campaign_recipients ENABLE ROW LEVEL SECURITY;
ALTER TABLE conversations ENABLE ROW LEVEL SECURITY;
ALTER TABLE messages ENABLE ROW LEVEL SECURITY;
ALTER TABLE automation_rules ENABLE ROW LEVEL SECURITY;
ALTER TABLE ai_bot_configs ENABLE ROW LEVEL SECURITY;

DO $$ 
BEGIN
    DROP POLICY IF EXISTS "Permissive whatsapp_configs" ON whatsapp_configs;
    CREATE POLICY "Permissive whatsapp_configs" ON whatsapp_configs FOR ALL USING (true) WITH CHECK (true);

    DROP POLICY IF EXISTS "Permissive contacts" ON contacts;
    CREATE POLICY "Permissive contacts" ON contacts FOR ALL USING (true) WITH CHECK (true);

    DROP POLICY IF EXISTS "Permissive contact_groups" ON contact_groups;
    CREATE POLICY "Permissive contact_groups" ON contact_groups FOR ALL USING (true) WITH CHECK (true);

    DROP POLICY IF EXISTS "Permissive contact_group_members" ON contact_group_members;
    CREATE POLICY "Permissive contact_group_members" ON contact_group_members FOR ALL USING (true) WITH CHECK (true);

    DROP POLICY IF EXISTS "Permissive whatsapp_templates" ON whatsapp_templates;
    CREATE POLICY "Permissive whatsapp_templates" ON whatsapp_templates FOR ALL USING (true) WITH CHECK (true);

    DROP POLICY IF EXISTS "Permissive campaigns" ON campaigns;
    CREATE POLICY "Permissive campaigns" ON campaigns FOR ALL USING (true) WITH CHECK (true);

    DROP POLICY IF EXISTS "Permissive campaign_recipients" ON campaign_recipients;
    CREATE POLICY "Permissive campaign_recipients" ON campaign_recipients FOR ALL USING (true) WITH CHECK (true);

    DROP POLICY IF EXISTS "Permissive conversations" ON conversations;
    CREATE POLICY "Permissive conversations" ON conversations FOR ALL USING (true) WITH CHECK (true);

    DROP POLICY IF EXISTS "Permissive messages" ON messages;
    CREATE POLICY "Permissive messages" ON messages FOR ALL USING (true) WITH CHECK (true);

    DROP POLICY IF EXISTS "Permissive automation_rules" ON automation_rules;
    CREATE POLICY "Permissive automation_rules" ON automation_rules FOR ALL USING (true) WITH CHECK (true);

    DROP POLICY IF EXISTS "Permissive ai_bot_configs" ON ai_bot_configs;
    CREATE POLICY "Permissive ai_bot_configs" ON ai_bot_configs FOR ALL USING (true) WITH CHECK (true);
END $$;
