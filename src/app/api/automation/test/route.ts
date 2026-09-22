import { NextRequest, NextResponse } from 'next/server';
import { defaultAutomationEngine } from '@/lib/automation/engine';
import { defaultAiResponder } from '@/lib/ai/responder';

export async function POST(request: NextRequest) {
  try {
    const { message, customerName, isFirstMessage } = await request.json();

    if (!message) {
      return NextResponse.json(
        { error: 'Message text is required for testing.' },
        { status: 400 }
      );
    }

    const testPhone = '919999999999';

    const result = await defaultAutomationEngine.processInboundMessage({
      phone: testPhone,
      customerName: customerName || 'Alex',
      messageText: message,
      conversationId: '',
      isFirstMessage: Boolean(isFirstMessage),
    });

    const aiStatus = defaultAiResponder.isAvailable()
      ? 'Configured & Active'
      : 'API key needed (using mock/default)';

    return NextResponse.json({
      success: true,
      simulation: result,
      aiStatus,
    });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error testing automation' },
      { status: 500 }
    );
  }
}
