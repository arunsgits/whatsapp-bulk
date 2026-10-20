<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subjectTitle ?? 'Wallet Recharge Reminder' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; -webkit-font-smoothing: antialiased;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; padding: 35px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 580px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01); border: 1px solid #e2e8f0;">
                    <!-- Brand Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); padding: 32px 35px; text-align: left;">
                            <div style="font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                                {{ $appName ?? 'WhatsApp Bulk' }}
                            </div>
                            <div style="font-size: 13px; color: #e0e7ff; margin-top: 4px; font-weight: 500;">
                                Wallet &amp; Billing &bull; Top-up Notification
                            </div>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 32px 35px 20px 35px;">
                            <!-- Alert Badge -->
                            <div style="display: inline-block; padding: 6px 14px; background-color: {{ $isCritical ? '#fee2e2' : '#e0e7ff' }}; color: {{ $isCritical ? '#b91c1c' : '#4338ca' }}; font-size: 13px; font-weight: 700; border-radius: 20px; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px;">
                                &#9889; {{ $isCritical ? 'Critical: Low Balance' : 'Wallet Top-up Reminder' }}
                            </div>

                            <h1 style="margin: 0 0 12px 0; font-size: 22px; font-weight: 800; color: #0f172a; line-height: 1.3;">
                                {{ $headline ?? 'Wallet Recharge Reminder' }}
                            </h1>

                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                Hello <strong>{{ $userName ?? 'Customer' }}</strong>,
                            </p>

                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                Your WhatsApp Bulk wallet balance is currently running low. Please top up your wallet to ensure your automated replies, template messaging, and bulk campaigns continue without interruption.
                            </p>

                            @if(!empty($customMessage))
                            <div style="background-color: #f1f5f9; border-left: 4px solid #6366f1; padding: 14px 18px; border-radius: 6px; font-size: 13px; color: #334155; margin-bottom: 24px; line-height: 1.6;">
                                <strong>Message from Administrator:</strong><br>
                                {{ $customMessage }}
                            </div>
                            @endif

                            <!-- Wallet Balance Highlight Card -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px; border: 1px solid #e2e8f0; font-size: 13px; margin-bottom: 25px; text-align: center;">
                                <tr>
                                    <td style="padding: 24px 20px;">
                                        <div style="font-size: 12px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.8px;">Current Wallet Balance</div>
                                        <div style="font-size: 32px; font-weight: 900; color: {{ $isCritical ? '#ef4444' : '#0f172a' }}; margin: 8px 0 6px 0;">
                                            ₹{{ $currentBalanceFormatted ?? '0.00' }}
                                        </div>
                                        <div style="font-size: 13px; color: #64748b;">
                                            Recommended Minimum Top-up: <strong style="color: #4f46e5;">₹{{ $recommendedAmountFormatted ?? '500.00' }}</strong>
                                        </div>
                                    </td>
                                </tr>
                            </table>

                            <!-- Action Button -->
                            <div style="text-align: center; margin: 28px 0 16px 0;">
                                <a href="{{ $paymentLink ?? url('/wallet') }}" style="display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; border-radius: 10px; box-shadow: 0 4px 14px 0 rgba(79, 70, 229, 0.35); letter-spacing: 0.2px;">
                                    Recharge Wallet Now &rarr;
                                </a>
                            </div>

                            <p style="margin: 0; font-size: 12px; color: #94a3b8; text-align: center;">
                                Having trouble with the button? Copy this link: <br>
                                <a href="{{ $paymentLink ?? url('/wallet') }}" style="color: #4f46e5; word-break: break-all;">{{ $paymentLink ?? url('/wallet') }}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 22px 35px; text-align: center; font-size: 12px; color: #94a3b8; line-height: 1.5;">
                            &copy; {{ $year ?? date('Y') }} {{ $appName ?? 'WhatsApp Bulk' }}. All rights reserved.<br>
                            Need assistance? Please contact our support team.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
