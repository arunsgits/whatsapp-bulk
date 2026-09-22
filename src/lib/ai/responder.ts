export interface AiGenerateOptions {
  apiKey?: string;
  baseUrl?: string;
  model?: string;
  systemPrompt?: string;
  businessContext?: string;
  temperature?: number;
}

export interface ChatHistoryMessage {
  role: 'user' | 'assistant' | 'system';
  content: string;
}

export class AiResponder {
  private apiKey: string;
  private baseUrl: string;
  private model: string;
  private systemPrompt: string;
  private businessContext: string;
  private temperature: number;

  constructor(options?: AiGenerateOptions) {
    this.apiKey = options?.apiKey || process.env.AI_API_KEY || '';
    this.baseUrl =
      options?.baseUrl ||
      process.env.AI_BASE_URL ||
      'https://api.groq.com/openai/v1';
    this.model =
      options?.model || process.env.AI_MODEL || 'openai/gpt-oss-20b';
    this.systemPrompt =
      options?.systemPrompt ||
      'You are a professional, friendly, and concise WhatsApp assistant for our business. Keep replies direct and under 2-3 sentences. Never make false promises. If you do not have enough information, offer to connect the customer with our support agent.';
    this.businessContext =
      options?.businessContext ||
      'We provide WhatsApp bulk marketing, intelligent chatbots, and lead automation solutions.';
    this.temperature = options?.temperature ?? 0.7;
  }

  public isAvailable(): boolean {
    const key = this.apiKey || process.env.AI_API_KEY || '';
    return Boolean(key && key.trim() !== '');
  }

  public async generateReply(
    userMessage: string,
    history: ChatHistoryMessage[] = [],
    customerName?: string
  ): Promise<string | null> {
    const activeKey = this.apiKey || process.env.AI_API_KEY || '';
    const activeBaseUrl = this.baseUrl || process.env.AI_BASE_URL || 'https://api.groq.com/openai/v1';
    const activeModel = process.env.AI_MODEL || this.model || 'openai/gpt-oss-20b';

    if (!activeKey) {
      return null;
    }

    const systemInstruction = `${this.systemPrompt}\n\nBUSINESS CONTEXT:\n${this.businessContext}\n${
      customerName ? `\nThe customer's name is: ${customerName}.` : ''
    }\n\nFORMAT RULES:\n- Keep your answer concise (1 to 3 short sentences max).\n- Use WhatsApp friendly formatting (e.g. *bold*, bullet points, occasional emojis).\n- Sound natural and helpful.`;

    const messages: ChatHistoryMessage[] = [
      { role: 'system', content: systemInstruction },
      ...history.slice(-6),
      { role: 'user', content: userMessage },
    ];

    try {
      const endpoint = `${activeBaseUrl.replace(/\/+$/, '')}/chat/completions`;
      const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
          Authorization: `Bearer ${activeKey}`,
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          model: activeModel,
          messages,
          temperature: this.temperature,
          max_tokens: 250,
        }),
      });

      if (!response.ok) {
        const errText = await response.text();
        console.error('AI API Error response:', errText);
        return null;
      }

      const data = await response.json();
      const reply = data.choices?.[0]?.message?.content?.trim();
      return reply || null;
    } catch (error) {
      console.error('AI Auto-reply generation exception:', error);
      return null;
    }
  }
}

export const defaultAiResponder = new AiResponder();
