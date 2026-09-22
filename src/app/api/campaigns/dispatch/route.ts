import { NextRequest, NextResponse } from 'next/server';
import { defaultCampaignDispatcher } from '@/lib/campaigns/dispatcher';
import { Campaign, CampaignRecipient } from '@/types/database';

export async function POST(request: NextRequest) {
  try {
    const body = await request.json();
    const { campaign, recipients } = body as {
      campaign: Campaign;
      recipients: CampaignRecipient[];
    };

    if (!campaign || !recipients || recipients.length === 0) {
      return NextResponse.json(
        { error: 'Campaign and at least 1 recipient are required.' },
        { status: 400 }
      );
    }

    // Run dispatch
    const result = await defaultCampaignDispatcher.dispatchCampaign(
      campaign,
      recipients
    );

    return NextResponse.json({
      success: true,
      ...result,
    });
  } catch (err: any) {
    return NextResponse.json(
      { error: err.message || 'Error executing campaign dispatch' },
      { status: 500 }
    );
  }
}
