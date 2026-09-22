import { normalizePhoneNumber } from './phone';

export interface WhatsAppConfigOptions {
  phoneNumberId?: string;
  accessToken?: string;
  apiVersion?: string;
}

export interface SendResult {
  success: boolean;
  messageId?: string;
  error?: string;
  raw?: any;
}

export class WhatsAppClient {
  private phoneNumberId: string;
  private accessToken: string;
  private apiVersion: string;

  constructor(options?: WhatsAppConfigOptions) {
    this.phoneNumberId =
      options?.phoneNumberId || process.env.WHATSAPP_PHONE_NUMBER_ID || '';
    this.accessToken =
      options?.accessToken || process.env.WHATSAPP_ACCESS_TOKEN || '';
    this.apiVersion =
      options?.apiVersion || process.env.WHATSAPP_API_VERSION || 'v19.0';
  }

  public isConfigured(): boolean {
    return Boolean(this.phoneNumberId && this.accessToken);
  }

  private getUrl(endpoint = 'messages'): string {
    return `https://graph.facebook.com/${this.apiVersion}/${this.phoneNumberId}/${endpoint}`;
  }

  /**
   * Send a direct WhatsApp text message
   */
  public async sendTextMessage(to: string, message: string): Promise<SendResult> {
    if (!this.isConfigured()) {
      return {
        success: false,
        error: 'WhatsApp Cloud API is not configured. Missing Phone Number ID or Access Token.',
      };
    }

    const normalizedTo = normalizePhoneNumber(to);
    if (!normalizedTo) {
      return { success: false, error: 'Invalid recipient phone number.' };
    }

    const payload = {
      messaging_product: 'whatsapp',
      recipient_type: 'individual',
      to: normalizedTo,
      type: 'text',
      text: {
        preview_url: true,
        body: message,
      },
    };

    try {
      const response = await fetch(this.getUrl('messages'), {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${this.accessToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const data = await response.json();

      if (response.ok && data.messages?.[0]?.id) {
        return {
          success: true,
          messageId: data.messages[0].id,
          raw: data,
        };
      }

      return {
        success: false,
        error: data.error?.message || JSON.stringify(data),
        raw: data,
      };
    } catch (err: any) {
      return {
        success: false,
        error: err.message || 'Network exception while calling WhatsApp API',
      };
    }
  }

  /**
   * Send an approved WhatsApp template message
   */
  public async sendTemplateMessage(
    to: string,
    templateName: string,
    languageCode = 'en',
    components: any[] = []
  ): Promise<SendResult> {
    if (!this.isConfigured()) {
      return {
        success: false,
        error: 'WhatsApp Cloud API is not configured. Missing Phone Number ID or Access Token.',
      };
    }

    const normalizedTo = normalizePhoneNumber(to);
    if (!normalizedTo) {
      return { success: false, error: 'Invalid recipient phone number.' };
    }

    const templatePayload: any = {
      name: templateName,
      language: {
        code: languageCode,
      },
    };

    if (components && components.length > 0) {
      templatePayload.components = components;
    }

    const payload = {
      messaging_product: 'whatsapp',
      recipient_type: 'individual',
      to: normalizedTo,
      type: 'template',
      template: templatePayload,
    };

    try {
      const response = await fetch(this.getUrl('messages'), {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${this.accessToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const data = await response.json();

      if (response.ok && data.messages?.[0]?.id) {
        return {
          success: true,
          messageId: data.messages[0].id,
          raw: data,
        };
      }

      return {
        success: false,
        error: data.error?.message || JSON.stringify(data),
        raw: data,
      };
    } catch (err: any) {
      return {
        success: false,
        error: err.message || 'Network exception while calling WhatsApp Template API',
      };
    }
  }

  /**
   * Send a media message (image, document, video)
   */
  public async sendMediaMessage(
    to: string,
    type: 'image' | 'document' | 'video',
    mediaUrl: string,
    caption?: string,
    filename?: string
  ): Promise<SendResult> {
    if (!this.isConfigured()) {
      return { success: false, error: 'WhatsApp API credentials not configured.' };
    }

    const normalizedTo = normalizePhoneNumber(to);
    const mediaObj: any = { link: mediaUrl };
    if (caption) mediaObj.caption = caption;
    if (filename && type === 'document') mediaObj.filename = filename;

    const payload = {
      messaging_product: 'whatsapp',
      recipient_type: 'individual',
      to: normalizedTo,
      type,
      [type]: mediaObj,
    };

    try {
      const response = await fetch(this.getUrl('messages'), {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${this.accessToken}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const data = await response.json();
      if (response.ok && data.messages?.[0]?.id) {
        return { success: true, messageId: data.messages[0].id, raw: data };
      }

      return { success: false, error: data.error?.message || 'Media send error' };
    } catch (err: any) {
      return { success: false, error: err.message };
    }
  }

  /**
   * Fetch approved message templates from Meta Business Account
   */
  public async fetchMetaTemplates(businessAccountId?: string): Promise<any[]> {
    const wabaId =
      businessAccountId || process.env.WHATSAPP_BUSINESS_ACCOUNT_ID;
    if (!wabaId || !this.accessToken) return [];

    try {
      const url = `https://graph.facebook.com/${this.apiVersion}/${wabaId}/message_templates?limit=100`;
      const res = await fetch(url, {
        headers: { Authorization: `Bearer ${this.accessToken}` },
      });
      const data = await res.json();
      return data.data || [];
    } catch (e) {
      console.error('Error fetching Meta templates:', e);
      return [];
    }
  }
}

export const defaultWhatsAppClient = new WhatsAppClient();
