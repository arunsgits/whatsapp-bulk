<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subjectTitle ?? 'Plan Notification' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); border: 1px solid #e2e8f0;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); padding: 30px 35px; text-align: left;">
                            <div style="font-size: 20px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">
                                {{ $appName ?? 'WhatsApp Bulk' }}
                            </div>
                            <div style="font-size: 13px; color: #bae6fd; margin-top: 4px;">
                                Subscription & Plan Confirmation
                            </div>
                        </td>
                    </tr>

                    <!-- Status Banner -->
                    <tr>
                        <td style="padding: 25px 35px 15px 35px;">
                            <div style="display: inline-block; padding: 6px 14px; background-color: #dcfce7; color: #15803d; font-size: 13px; font-weight: 600; border-radius: 20px; margin-bottom: 12px;">
                                &#10003; {{ ucfirst($actionType ?? 'Activated') }} Successfully
                            </div>
                            <h1 style="margin: 0 0 10px 0; font-size: 22px; font-weight: 700; color: #0f172a;">
                                {{ $headline ?? 'Plan Activated' }}
                            </h1>
                            <p style="margin: 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                Hello <strong>{{ $userName ?? 'Subscriber' }}</strong>,
                                @if(!empty($userMessage))
                                    {{ $userMessage }}
                                @else
                                    your plan <strong>{{ $packageName ?? 'Subscription' }}</strong> has been successfully processed and activated.
                                @endif
                            </p>
                        </td>
                    </tr>

                    <!-- Details Card -->
                    <tr>
                        <td style="padding: 15px 35px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px;">
                                <tr>
                                    <td colspan="2" style="padding: 12px 18px; border-bottom: 2px solid #e2e8f0; font-weight: 700; color: #0f172a; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">
                                        Plan Summary
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Plan Name</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 600; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ $packageName ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Action</td>
                                    <td style="padding: 10px 18px; color: #0284c7; font-weight: 600; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ ucfirst($actionType ?? 'Active') }}</td>
                                </tr>
                                @if(!empty($periodLabel))
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Validity / Duration</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 500; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ $periodLabel }}</td>
                                </tr>
                                @endif
                                @if(!empty($startDateFormatted))
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Start Date</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 500; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ $startDateFormatted }}</td>
                                </tr>
                                @endif
                                @if(!empty($endDateFormatted))
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Valid Until</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 600; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ $endDateFormatted }}</td>
                                </tr>
                                @endif
                                @if(!empty($msgCount))
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Total Messages</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 500; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ number_format((int)$msgCount) }}</td>
                                </tr>
                                @endif
                                @if(!empty($contactCount))
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Total Contacts</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 500; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ number_format((int)$contactCount) }}</td>
                                </tr>
                                @endif
                                @if(!empty($templateCount))
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Template Limit</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 500; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ number_format((int)$templateCount) }}</td>
                                </tr>
                                @endif
                                @if(!empty($baseAmountFormatted) && !empty($gstAmountFormatted))
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Base Amount</td>
                                    <td style="padding: 10px 18px; color: #0f172a; text-align: right; border-bottom: 1px solid #e2e8f0;">₹{{ $baseAmountFormatted }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">GST ({{ $gstRateDisplay ?? '18' }}%)</td>
                                    <td style="padding: 10px 18px; color: #0f172a; text-align: right; border-bottom: 1px solid #e2e8f0;">₹{{ $gstAmountFormatted }}</td>
                                </tr>
                                @endif
                                @if(!empty($totalAmountFormatted))
                                <tr>
                                    <td style="padding: 12px 18px; color: #0f172a; font-weight: 700; font-size: 14px;">Total Price</td>
                                    <td style="padding: 12px 18px; color: #15803d; font-weight: 700; font-size: 15px; text-align: right;">₹{{ $totalAmountFormatted }}</td>
                                </tr>
                                @endif
                                @if(!empty($receiptId))
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-top: 1px solid #e2e8f0;">Receipt / Order #</td>
                                    <td style="padding: 10px 18px; color: #0f172a; text-align: right; border-top: 1px solid #e2e8f0;">{{ $receiptId }}</td>
                                </tr>
                                @endif
                                @if(!empty($paymentMethod))
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-top: 1px solid #e2e8f0;">Payment Method</td>
                                    <td style="padding: 10px 18px; color: #0f172a; text-align: right; border-top: 1px solid #e2e8f0;">{{ ucfirst($paymentMethod) }}</td>
                                </tr>
                                @endif
                            </table>
                        </td>
                    </tr>

                    <!-- CTA Button -->
                    <tr>
                        <td style="padding: 20px 35px; text-align: center;">
                            <a href="{{ $dashboardUrl ?? url('/dashboard') }}" style="display: inline-block; padding: 12px 28px; background-color: #0284c7; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; border-radius: 8px;">
                                Open Dashboard &rarr;
                            </a>
                        </td>
                    </tr>

                    @if(!empty($isCcCopy) && !empty($ccRecipientEmail))
                    <!-- CC Notice -->
                    <tr>
                        <td style="padding: 0 35px 15px 35px;">
                            <div style="background-color: #f1f5f9; border-left: 3px solid #0284c7; padding: 10px 14px; border-radius: 4px; font-size: 12px; color: #64748b;">
                                <strong>Admin Notice:</strong> A carbon copy (CC) of this plan notification was sent to <strong>{{ $ccRecipientEmail }}</strong> as per system SMTP settings.
                            </div>
                        </td>
                    </tr>
                    @endif

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 35px; text-align: center; font-size: 12px; color: #94a3b8;">
                            &copy; {{ date('Y') }} {{ $appName ?? 'WhatsApp Bulk' }}. All rights reserved.<br>
                            This is an automated notification. Please do not reply directly to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
