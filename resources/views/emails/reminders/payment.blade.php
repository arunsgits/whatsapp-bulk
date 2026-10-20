<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $subjectTitle ?? 'Payment Reminder' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b; -webkit-font-smoothing: antialiased;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; padding: 35px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 580px; background-color: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01); border: 1px solid #e2e8f0;">
                    <!-- Brand Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); padding: 32px 35px; text-align: left;">
                            <div style="font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                                {{ $appName ?? 'WhatsApp Bulk' }}
                            </div>
                            <div style="font-size: 13px; color: #e0f2fe; margin-top: 4px; font-weight: 500;">
                                Billing &amp; Invoicing &bull; Payment Request
                            </div>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 32px 35px 20px 35px;">
                            <!-- Alert Badge -->
                            <div style="display: inline-block; padding: 6px 14px; background-color: #e0f2fe; color: #0369a1; font-size: 13px; font-weight: 700; border-radius: 20px; margin-bottom: 16px; text-transform: uppercase; letter-spacing: 0.5px;">
                                &#128203; Payment Pending
                            </div>

                            <h1 style="margin: 0 0 12px 0; font-size: 22px; font-weight: 800; color: #0f172a; line-height: 1.3;">
                                {{ $headline ?? 'Invoice Payment Reminder' }}
                            </h1>

                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                Hello <strong>{{ $userName ?? 'Customer' }}</strong>,
                            </p>

                            <p style="margin: 0 0 20px 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                This is a friendly reminder that invoice <strong>{{ $invoiceNumber }}</strong> is pending payment. Please review the details below and proceed with the payment to keep your account in good standing.
                            </p>

                            @if(!empty($customMessage))
                            <div style="background-color: #f1f5f9; border-left: 4px solid #0ea5e9; padding: 14px 18px; border-radius: 6px; font-size: 13px; color: #334155; margin-bottom: 24px; line-height: 1.6;">
                                <strong>Message from Administrator:</strong><br>
                                {{ $customMessage }}
                            </div>
                            @endif

                            <!-- Invoice Details Card -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 13px; margin-bottom: 25px;">
                                <tr>
                                    <td colspan="2" style="padding: 14px 20px; border-bottom: 2px solid #e2e8f0; font-weight: 700; color: #0f172a; text-transform: uppercase; font-size: 11px; letter-spacing: 0.8px;">
                                        Invoice Details
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 20px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Invoice Number</td>
                                    <td style="padding: 12px 20px; color: #0f172a; font-weight: 700; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ $invoiceNumber }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 20px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Due Date</td>
                                    <td style="padding: 12px 20px; color: #dc2626; font-weight: 700; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ $dueDate ?? 'Due on Receipt' }}</td>
                                </tr>
                                @if(!empty($description))
                                <tr>
                                    <td style="padding: 12px 20px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Description</td>
                                    <td style="padding: 12px 20px; color: #0f172a; font-weight: 500; text-align: right; border-bottom: 1px solid #e2e8f0;">{{ $description }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="padding: 14px 20px; color: #0f172a; font-weight: 700; font-size: 14px;">Total Amount Due</td>
                                    <td style="padding: 14px 20px; color: #0284c7; font-weight: 800; font-size: 17px; text-align: right;">₹{{ $amountDueFormatted ?? '0.00' }}</td>
                                </tr>
                            </table>

                            <!-- Action Button -->
                            <div style="text-align: center; margin: 28px 0 16px 0;">
                                <a href="{{ $paymentLink ?? url('/dashboard') }}" style="display: inline-block; padding: 14px 36px; background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; border-radius: 10px; box-shadow: 0 4px 14px 0 rgba(2, 132, 199, 0.35); letter-spacing: 0.2px;">
                                    Pay Invoice Now &rarr;
                                </a>
                            </div>

                            <p style="margin: 0; font-size: 12px; color: #94a3b8; text-align: center;">
                                Having trouble with the button? Copy this link: <br>
                                <a href="{{ $paymentLink ?? url('/dashboard') }}" style="color: #0284c7; word-break: break-all;">{{ $paymentLink ?? url('/dashboard') }}</a>
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 22px 35px; text-align: center; font-size: 12px; color: #94a3b8; line-height: 1.5;">
                            &copy; {{ $year ?? date('Y') }} {{ $appName ?? 'WhatsApp Bulk' }}. All rights reserved.<br>
                            Already made this payment? Please allow a few moments for reconciliation or reach out to support.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
