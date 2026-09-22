import { NextRequest, NextResponse } from 'next/server';
import { POST as createCampaign } from '../route';

export async function POST(request: NextRequest) {
  return createCampaign(request);
}
