<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subjectTitle ?? 'Subscription Renewal Reminder' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; -webkit-font-smoothing: antialiased;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; padding: 35px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 580px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01); border: 1px solid #e2e8f0;">
                    <!-- Brand Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); padding: 32px 35px; text-align: left;">
                            <div style="font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                                {{ $appName ?? 'WhatsApp Bulk' }}
                            </div>
                            <div style="font-size: 13px; color: #fef3c7; margin-top: 4px; font-weight: 500;">
                                Subscription Management &bull; Renewal Notice
                            </div>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 32px 35px 20px 35px;">
                            <!-- Alert Badge -->
                            <div style="display: inline-block; padding: 6px 14px; background-color: {{ $isExpired ? '#fee2e2' : '#fef3c7' }}; color: {{ $isExpired ? '#b91c1c' : '#b45309' }}; font-size: 13px; font-weight: 700; border-radius: 20px; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px;">
                                &#9888; {{ $statusBadge ?? 'Renewal Notice' }}
                            </div>

                            <h1 style="margin: 0 0 12px 0; font-size: 22px; font-weight: 800; color: #0f172a; line-height: 1.3;">
                                {{ $headline ?? 'Subscription Renewal Reminder' }}
                            </h1>

                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                Hello <strong>{{ $userName ?? 'Subscriber' }}</strong>,
                            </p>

                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                {{ $statusText ?? 'Your subscription plan requires renewal to ensure uninterrupted access to WhatsApp Bulk campaigns, automated AI replies, and API services.' }}
                            </p>

                            @if(!empty($customMessage))
                            <div style="background-color: #f1f5f9; border-left: 4px solid #f59e0b; padding: 14px 18px; border-radius: 6px; font-size: 13px; color: #334155; margin-bottom: 24px; line-height: 1.6;">
                                <strong>Message from Administrator:</strong><br>
                                {{ $customMessage }}
                            </div>
                            @endif

                            <!-- Plan Summary Card -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 13px; margin-bottom: 25px;">
                                <tr>
                                    <td colspan="2" style="padding: 14px 20px; border-bottom: 2px solid #e2e8f0; font-weight: 700; color: #0f172a; text-transform: uppercase; font-size: 11px; letter-spacing: 0.8px;">
                                        Subscription Overview
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 20px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Current Plan</td>
                                    <td style="padding: 12px 20px; color: #0f172a; font-weight: 700; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ $planName ?? 'Subscription Plan' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 20px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Expiry Date</td>
                                    <td style="padding: 12px 20px; color: {{ $isExpired ? '#ef4444' : '#0f172a' }}; font-weight: 700; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ $expiryDateFormatted ?? 'Due Soon' }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 14px 20px; color: #0f172a; font-weight: 700; font-size: 14px;">Renewal Price</td>
                                    <td style="padding: 14px 20px; color: #059669; font-weight: 800; font-size: 16px; text-align: right;">₹{{ $renewalAmountFormatted ?? '0.00' }}</td>
                                </tr>
                            </table>

                            <!-- Action Button -->
                            <div style="text-align: center; margin: 28px 0 16px 0;">
                                <a href="{{ $paymentLink ?? url('/dashboard/plans') }}" style="display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; border-radius: 10px; box-shadow: 0 4px 14px 0 rgba(217, 119, 6, 0.35); letter-spacing: 0.2px;">
                                    Renew Subscription Now &rarr;
                                </a>
                            </div>

                            <p style="margin: 0; font-size: 12px; color: #94a3b8; text-align: center;">
                                Having trouble with the button? Copy this link: <br>
                                <a href="{{ $paymentLink ?? url('/dashboard/plans') }}" style="color: #d97706; word-break: break-all;">{{ $paymentLink ?? url('/dashboard/plans') }}</a>
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
