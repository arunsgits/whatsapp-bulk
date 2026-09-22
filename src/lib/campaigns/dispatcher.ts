import { defaultWhatsAppClient } from '../whatsapp/api';
import { getServiceSupabase, isServerSupabaseConfigured } from '../supabase/server';
import { Campaign, CampaignRecipient } from '@/types/database';

export interface DispatchProgressCallback {
  (progress: {
    campaignId: string;
    total: number;
    sent: number;
    failed: number;
    currentRecipient?: string;
  }): void;
}

export class CampaignDispatcher {
  private sleep(ms: number): Promise<void> {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  /**
   * Replace template / text variables like {{name}} or {{company}}
   */
  public interpolateText(text: string, variables: Record<string, any>): string {
    if (!text) return '';
    let result = text;
    for (const [key, val] of Object.entries(variables)) {
      const regex = new RegExp(`\\{\\{${key}\\}\\}`, 'gi');
      result = result.replace(regex, String(val ?? ''));
    }
    return result;
  }

  /**
   * Dispatch a batch of recipients for a given campaign with anti-ban delay throttling
   */
  public async dispatchCampaign(
    campaign: Campaign,
    recipients: CampaignRecipient[],
    onProgress?: DispatchProgressCallback
  ): Promise<{ sent: number; failed: number; errors: any[] }> {
    const supabase = getServiceSupabase();
    const isConfigured = isServerSupabaseConfigured();

    let sent = 0;
    let failed = 0;
    const errors: any[] = [];
    const delayMs = Math.max(500, (campaign.throttle_delay_sec || 2) * 1000);

    if (isConfigured) {
      await supabase
        .from('campaigns')
        .update({
          status: 'processing',
          started_at: new Date().toISOString(),
        })
        .eq('id', campaign.id);
    }

    for (let i = 0; i < recipients.length; i++) {
      const recipient = recipients[i];
      const mergedVariables = {
        name: recipient.name || 'Valued Customer',
        phone: recipient.phone,
        ...(recipient.variables || {}),
      };

      try {
        let result: { success: boolean; messageId?: string; error?: string };

        if (campaign.template_id && campaign.template) {
          // Template-based campaign
          const bodyParams = Object.values(mergedVariables).map((v) => ({
            type: 'text',
            text: String(v),
          }));

          const components = [
            {
              type: 'body',
              parameters: bodyParams,
            },
          ];

          result = await defaultWhatsAppClient.sendTemplateMessage(
            recipient.phone,
            campaign.template.name,
            campaign.template.language || 'en',
            components
          );
        } else {
          // Direct message campaign
          const finalMessage = this.interpolateText(
            campaign.direct_message || '',
            mergedVariables
          );

          result = await defaultWhatsAppClient.sendTextMessage(
            recipient.phone,
            finalMessage
          );
        }

        if (result.success) {
          sent++;
          if (isConfigured) {
            await supabase
              .from('campaign_recipients')
              .update({
                status: 'sent',
                meta_message_id: result.messageId,
                sent_at: new Date().toISOString(),
              })
              .eq('id', recipient.id);

            // Also ensure conversation exists
            const { data: conv } = await supabase
              .from('conversations')
              .upsert(
                {
                  phone_number: recipient.phone,
                  contact_name: recipient.name,
                  last_message_text: campaign.direct_message || 'Template Message',
                  last_message_at: new Date().toISOString(),
                },
                { onConflict: 'phone_number' }
              )
              .select('id')
              .single();

            if (conv) {
              await supabase.from('messages').insert({
                conversation_id: conv.id,
                direction: 'outgoing',
                message_type: campaign.template_id ? 'template' : 'text',
                message: campaign.direct_message || 'Template Message',
                meta_message_id: result.messageId,
                status: 'sent',
                sender_type: 'system_campaign',
              });
            }
          }
        } else {
          failed++;
          errors.push({ phone: recipient.phone, error: result.error });
          if (isConfigured) {
            await supabase
              .from('campaign_recipients')
              .update({
                status: 'failed',
                error_message: result.error,
              })
              .eq('id', recipient.id);
          }
        }
      } catch (err: any) {
        failed++;
        errors.push({ phone: recipient.phone, error: err.message });
      }

      if (onProgress) {
        onProgress({
          campaignId: campaign.id,
          total: recipients.length,
          sent,
          failed,
          currentRecipient: recipient.phone,
        });
      }

      // Anti-ban delay between sends (skip delay on last item)
      if (i < recipients.length - 1) {
        await this.sleep(delayMs);
      }
    }

    if (isConfigured) {
      await supabase
        .from('campaigns')
        .update({
          status: 'completed',
          sent_count: sent,
          failed_count: failed,
          completed_at: new Date().toISOString(),
        })
        .eq('id', campaign.id);
    }

    return { sent, failed, errors };
  }
}

export const defaultCampaignDispatcher = new CampaignDispatcher();
