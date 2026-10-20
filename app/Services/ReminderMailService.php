<?php

namespace App\Services;

use App\Models\ActivePackage;
use App\Models\EmailTemplate;
use App\Models\Package;
use App\Models\Settings;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ReminderMailService
{
    public function __construct(
        protected WalletService $walletService,
        protected InvoiceService $invoiceService
    ) {
    }

    /**
     * Dispatch reminder by type: 'renewal', 'wallet', 'payment'
     */
    public function sendReminder(User $user, string $type, array $options = []): array
    {
        $normalizedType = strtolower(trim($type));

        return match ($normalizedType) {
            'renewal', 'renew', 'plan_renewal', 'plan_expiry' => $this->sendRenewalReminder($user, $options),
            'wallet', 'recharge', 'wallet_recharge', 'low_balance' => $this->sendWalletRechargeReminder($user, $options),
            'payment', 'invoice', 'invoice_payment', 'due_payment' => $this->sendPaymentReminder($user, $options),
            default => [
                'success' => false,
                'message' => "Invalid reminder type '{$type}'. Supported types: 'renewal', 'wallet', 'payment'.",
            ],
        };
    }

    /**
     * 1. Send Subscription Renewal Reminder to User
     */
    public function sendRenewalReminder(User $user, array $options = []): array
    {
        try {
            $smtpReady = $this->configureSmtp();
            if (!$smtpReady['success']) {
                return $smtpReady;
            }

            $userEmail = trim((string) ($user->email ?: ''));
            if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => "User #{$user->id} does not have a valid email address.",
                ];
            }

            // Resolve plan & active package
            $activePkg = ActivePackage::where('userId', $user->id)
                ->with('package')
                ->orderByDesc('id')
                ->first();

            $package = null;
            if (!empty($options['package_id'])) {
                $package = Package::find($options['package_id']);
            }
            if (!$package && $activePkg) {
                $package = $activePkg->package ?: Package::find($activePkg->packageId);
            }

            $planName = $options['plan_name']
                ?? ($options['custom_plan_name']
                ?? ($activePkg?->custom_plan_name
                ?? ($package?->packageName ?? 'WhatsApp Subscription Plan')));

            // Expiry date and days remaining
            $endDate = $activePkg?->endDate ? Carbon::parse($activePkg->endDate) : null;
            $endDateFormatted = $endDate ? $endDate->format('d M Y') : 'Due Soon';
            $isExpired = $endDate ? $endDate->isPast() : false;
            $daysRemaining = $endDate ? (int) ceil(now()->floatDiffInDays($endDate, false)) : 0;

            if ($isExpired) {
                $statusBadge = 'Expired';
                $statusText = 'Your subscription has expired. Renew now to restore full access without service disruption.';
            } elseif ($daysRemaining <= 0) {
                $statusBadge = 'Expires Today';
                $statusText = 'Your subscription plan expires today. Please renew immediately to avoid service interruption.';
            } else {
                $statusBadge = "Expires in {$daysRemaining} Day" . ($daysRemaining > 1 ? 's' : '');
                $statusText = "Your subscription plan is set to expire on {$endDateFormatted}. Renew now to ensure seamless messaging.";
            }

            // Renewal price calculation
            $renewalPrice = isset($options['renewal_price'])
                ? (float) $options['renewal_price']
                : (isset($options['amount'])
                    ? (float) $options['amount']
                    : ($package ? $package->getEffectiveRenewalPrice() : (float) ($activePkg?->custom_price ?: 0)));

            // Renewal payment link
            $paymentLink = $options['payment_link'] ?? $options['payment_url'] ?? null;
            if (empty($paymentLink)) {
                try {
                    $linkData = $this->walletService->createRenewalPaymentLink($user, $package?->id, [
                        'custom_price' => $renewalPrice,
                        'custom_plan_name' => $planName,
                        'days' => $options['days'] ?? ($activePkg?->day ?: ($package?->day ?: 30)),
                    ]);
                    $paymentLink = $linkData['payment_link_url'] ?? $linkData['short_url'] ?? null;
                } catch (\Throwable $linkEx) {
                    // Fallback to direct dashboard renewal page
                    $paymentLink = rtrim(config('app.url'), '/') . '/dashboard/plans';
                }
            }

            $settings = $smtpReady['settings'];
            $appName = $settings->business_name ?: config('app.name', 'WhatsApp Bulk');
            $customMessage = trim((string) ($options['message'] ?? ($options['notes'] ?? '')));

            $subject = "⚠️ Renewal Reminder: {$planName} ({$statusBadge}) - {$appName}";
            $headline = $isExpired ? 'Subscription Expired - Renewal Required' : 'Subscription Expiring Soon';

            $viewData = [
                'appName' => $appName,
                'subjectTitle' => $subject,
                'headline' => $headline,
                'userName' => $user->name ?: 'Subscriber',
                'userEmail' => $userEmail,
                'companyName' => $user->companyName ?: '',
                'planName' => $planName,
                'statusBadge' => $statusBadge,
                'statusText' => $statusText,
                'isExpired' => $isExpired,
                'daysRemaining' => $daysRemaining,
                'expiryDateFormatted' => $endDateFormatted,
                'renewalAmountFormatted' => $renewalPrice > 0 ? number_format($renewalPrice, 2) : '0.00',
                'renewalPrice' => $renewalPrice,
                'paymentLink' => $paymentLink ?: (rtrim(config('app.url'), '/') . '/dashboard/plans'),
                'dashboardUrl' => rtrim(config('app.url'), '/') . '/dashboard',
                'customMessage' => $customMessage,
                'year' => date('Y'),
            ];

            // Check dynamic template in DB
            $dbTemplate = EmailTemplate::where('slug', 'renewal_reminder')
                ->where('is_active', true)
                ->first();

            $tokens = [
                'user_name' => $user->name ?: 'Subscriber',
                'user_email' => $userEmail,
                'app_name' => $appName,
                'plan_name' => $planName,
                'status_badge' => $statusBadge,
                'expiry_date' => $endDateFormatted,
                'days_remaining' => (string) max(0, $daysRemaining),
                'amount' => number_format($renewalPrice, 2),
                'renewal_amount' => number_format($renewalPrice, 2),
                'currency' => '₹',
                'payment_link' => $paymentLink ?: (rtrim(config('app.url'), '/') . '/dashboard/plans'),
                'custom_message' => $customMessage,
                'dashboard_url' => rtrim(config('app.url'), '/') . '/dashboard',
                'year' => date('Y'),
            ];

            $this->dispatchEmail(
                $userEmail,
                $user->name ?: 'Subscriber',
                $subject,
                $dbTemplate,
                $tokens,
                'emails.reminders.renewal',
                $viewData,
                $smtpReady
            );

            Log::info("ReminderMailService: Renewal reminder email sent to User #{$user->id} ({$userEmail})");

            return [
                'success' => true,
                'type' => 'renewal',
                'message' => "Renewal reminder sent successfully to {$userEmail}",
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'email' => $userEmail,
                    'plan_name' => $planName,
                    'expiry_date' => $endDateFormatted,
                    'days_remaining' => $daysRemaining,
                    'renewal_amount' => $renewalPrice,
                    'payment_link' => $paymentLink,
                    'sent_at' => now()->toIso8601String(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error("ReminderMailService::sendRenewalReminder failed: " . $e->getMessage(), [
                'userId' => $user->id,
                'exception' => $e,
            ]);
            return [
                'success' => false,
                'message' => 'Failed to send renewal reminder email: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 2. Send Wallet Recharge / Low Balance Reminder to User
     */
    public function sendWalletRechargeReminder(User $user, array $options = []): array
    {
        try {
            $smtpReady = $this->configureSmtp();
            if (!$smtpReady['success']) {
                return $smtpReady;
            }

            $userEmail = trim((string) ($user->email ?: ''));
            if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => "User #{$user->id} does not have a valid email address.",
                ];
            }

            $currentBalance = round((float) ($user->wallet_balance ?? 0), 2);
            $recommendedAmount = isset($options['amount'])
                ? (float) $options['amount']
                : (isset($options['recharge_amount']) ? (float) $options['recharge_amount'] : 500.00);

            $paymentLink = $options['payment_link']
                ?? ($options['recharge_url']
                ?? (rtrim(config('app.url'), '/') . '/wallet'));

            $settings = $smtpReady['settings'];
            $appName = $settings->business_name ?: config('app.name', 'WhatsApp Bulk');
            $customMessage = trim((string) ($options['message'] ?? ($options['notes'] ?? '')));

            $isCritical = $currentBalance <= 10.0;
            $subject = $isCritical
                ? "⚡ Urgent: Low Wallet Balance Alert (₹" . number_format($currentBalance, 2) . ") - {$appName}"
                : "⚡ Wallet Recharge Reminder: Maintain uninterrupted WhatsApp messaging - {$appName}";

            $viewData = [
                'appName' => $appName,
                'subjectTitle' => $subject,
                'headline' => $isCritical ? 'Urgent: Low Wallet Balance Alert' : 'Wallet Recharge Reminder',
                'userName' => $user->name ?: 'Valued Customer',
                'userEmail' => $userEmail,
                'companyName' => $user->companyName ?: '',
                'currentBalance' => $currentBalance,
                'currentBalanceFormatted' => number_format($currentBalance, 2),
                'recommendedAmount' => $recommendedAmount,
                'recommendedAmountFormatted' => number_format($recommendedAmount, 2),
                'isCritical' => $isCritical,
                'paymentLink' => $paymentLink,
                'dashboardUrl' => rtrim(config('app.url'), '/') . '/wallet',
                'customMessage' => $customMessage,
                'year' => date('Y'),
            ];

            $dbTemplate = EmailTemplate::where('slug', 'wallet_reminder')
                ->where('is_active', true)
                ->first();

            $tokens = [
                'user_name' => $user->name ?: 'Valued Customer',
                'user_email' => $userEmail,
                'app_name' => $appName,
                'balance' => number_format($currentBalance, 2),
                'current_balance' => number_format($currentBalance, 2),
                'recommended_amount' => number_format($recommendedAmount, 2),
                'amount' => number_format($recommendedAmount, 2),
                'currency' => '₹',
                'payment_link' => $paymentLink,
                'custom_message' => $customMessage,
                'dashboard_url' => rtrim(config('app.url'), '/') . '/wallet',
                'year' => date('Y'),
            ];

            $this->dispatchEmail(
                $userEmail,
                $user->name ?: 'Valued Customer',
                $subject,
                $dbTemplate,
                $tokens,
                'emails.reminders.wallet',
                $viewData,
                $smtpReady
            );

            Log::info("ReminderMailService: Wallet recharge reminder sent to User #{$user->id} ({$userEmail})");

            return [
                'success' => true,
                'type' => 'wallet',
                'message' => "Wallet recharge reminder sent successfully to {$userEmail}",
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'email' => $userEmail,
                    'current_balance' => $currentBalance,
                    'recommended_recharge' => $recommendedAmount,
                    'payment_link' => $paymentLink,
                    'sent_at' => now()->toIso8601String(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error("ReminderMailService::sendWalletRechargeReminder failed: " . $e->getMessage(), [
                'userId' => $user->id,
                'exception' => $e,
            ]);
            return [
                'success' => false,
                'message' => 'Failed to send wallet recharge reminder: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * 3. Send Payment / Invoice Reminder to User
     */
    public function sendPaymentReminder(User $user, array $options = []): array
    {
        try {
            $smtpReady = $this->configureSmtp();
            if (!$smtpReady['success']) {
                return $smtpReady;
            }

            $userEmail = trim((string) ($user->email ?: ''));
            if (!filter_var($userEmail, FILTER_VALIDATE_EMAIL)) {
                return [
                    'success' => false,
                    'message' => "User #{$user->id} does not have a valid email address.",
                ];
            }

            // If an invoice identifier is provided, fetch full invoice data
            $invoiceData = null;
            if (!empty($options['invoice_id'])) {
                try {
                    $invoiceData = $this->invoiceService->getInvoiceData($options['invoice_id']);
                } catch (\Throwable $invEx) {
                    $invoiceData = $this->invoiceService->fallbackInvoiceData($options['invoice_id'], $user);
                }
            }

            $invoiceNumber = $options['invoice_number']
                ?? ($invoiceData['invoice_number']
                ?? ('INV-' . date('Y') . '-' . str_pad((string) $user->id, 5, '0', STR_PAD_LEFT)));

            $amountDue = isset($options['amount'])
                ? (float) $options['amount']
                : ($invoiceData ? (float) ($invoiceData['pricing']['total_amount'] ?? $invoiceData['total_amount'] ?? 0) : 1499.00);

            $dueDate = !empty($options['due_date'])
                ? Carbon::parse($options['due_date'])->format('d M Y')
                : ($invoiceData['dates']['due_date_formatted'] ?? now()->addDays(3)->format('d M Y'));

            $paymentLink = $options['payment_link']
                ?? ($invoiceData['payment_link']
                ?? ($invoiceData['payment']['payment_link_url']
                ?? (rtrim(config('app.url'), '/') . '/dashboard')));

            $description = $options['description']
                ?? ($invoiceData['items'][0]['description']
                ?? 'Pending subscription invoice payment');

            $settings = $smtpReady['settings'];
            $appName = $settings->business_name ?: config('app.name', 'WhatsApp Bulk');
            $customMessage = trim((string) ($options['message'] ?? ($options['notes'] ?? '')));

            $subject = "📋 Payment Reminder: Invoice {$invoiceNumber} Due on {$dueDate} - {$appName}";
            $headline = "Payment Reminder: Invoice {$invoiceNumber}";

            $viewData = [
                'appName' => $appName,
                'subjectTitle' => $subject,
                'headline' => $headline,
                'userName' => $user->name ?: 'Customer',
                'userEmail' => $userEmail,
                'companyName' => $user->companyName ?: '',
                'invoiceNumber' => $invoiceNumber,
                'amountDueFormatted' => number_format($amountDue, 2),
                'amountDue' => $amountDue,
                'dueDate' => $dueDate,
                'description' => $description,
                'paymentLink' => $paymentLink,
                'dashboardUrl' => rtrim(config('app.url'), '/') . '/dashboard',
                'customMessage' => $customMessage,
                'year' => date('Y'),
            ];

            $dbTemplate = EmailTemplate::where('slug', 'payment_reminder')
                ->where('is_active', true)
                ->first();

            $tokens = [
                'user_name' => $user->name ?: 'Customer',
                'user_email' => $userEmail,
                'app_name' => $appName,
                'invoice_number' => $invoiceNumber,
                'amount' => number_format($amountDue, 2),
                'amount_due' => number_format($amountDue, 2),
                'due_date' => $dueDate,
                'description' => $description,
                'currency' => '₹',
                'payment_link' => $paymentLink,
                'custom_message' => $customMessage,
                'dashboard_url' => rtrim(config('app.url'), '/') . '/dashboard',
                'year' => date('Y'),
            ];

            $this->dispatchEmail(
                $userEmail,
                $user->name ?: 'Customer',
                $subject,
                $dbTemplate,
                $tokens,
                'emails.reminders.payment',
                $viewData,
                $smtpReady
            );

            Log::info("ReminderMailService: Payment reminder email sent to User #{$user->id} for {$invoiceNumber}");

            return [
                'success' => true,
                'type' => 'payment',
                'message' => "Payment reminder sent successfully to {$userEmail}",
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'email' => $userEmail,
                    'invoice_number' => $invoiceNumber,
                    'amount_due' => $amountDue,
                    'due_date' => $dueDate,
                    'payment_link' => $paymentLink,
                    'sent_at' => now()->toIso8601String(),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error("ReminderMailService::sendPaymentReminder failed: " . $e->getMessage(), [
                'userId' => $user->id,
                'exception' => $e,
            ]);
            return [
                'success' => false,
                'message' => 'Failed to send payment reminder email: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Helper to configure SMTP dynamically from platform Settings table
     */
    protected function configureSmtp(): array
    {
        $settings = Settings::whereNull('userId')->first() ?: Settings::first();
        if (!$settings || empty($settings->smtp_host) || empty($settings->smtp_user)) {
            return [
                'success' => false,
                'message' => 'SMTP is not configured in Admin Settings. Please configure SMTP host and credentials first.',
            ];
        }

        $port = (int) ($settings->smtp_port ?? 587);
        $encryption = ($port === 465) ? 'ssl' : (($port === 587) ? 'tls' : null);
        $appName = $settings->business_name ?: config('app.name', 'WhatsApp Bulk');

        Config::set('mail.default', 'smtp');
        Config::set('mail.mailers.smtp.transport', 'smtp');
        Config::set('mail.mailers.smtp.host', (string) $settings->smtp_host);
        Config::set('mail.mailers.smtp.port', $port);
        Config::set('mail.mailers.smtp.encryption', $encryption);
        Config::set('mail.mailers.smtp.username', (string) $settings->smtp_user);
        Config::set('mail.mailers.smtp.password', (string) ($settings->smtp_password ?? ''));
        Config::set('mail.from.address', (string) $settings->smtp_user);
        Config::set('mail.from.name', $appName);

        $ccEnabled = (bool) ($settings->smtp_cc_enabled ?? false);
        $ccRecipients = $ccEnabled ? $settings->getCcEmails() : [];

        return [
            'success' => true,
            'settings' => $settings,
            'appName' => $appName,
            'ccRecipients' => $ccRecipients,
        ];
    }

    /**
     * Dispatch email with DB Template compile or Blade fallback
     */
    protected function dispatchEmail(
        string $toEmail,
        string $toName,
        string $defaultSubject,
        ?EmailTemplate $dbTemplate,
        array $tokens,
        string $bladeView,
        array $viewData,
        array $smtpReady
    ): void {
        $settings = $smtpReady['settings'];
        $appName = $smtpReady['appName'];
        $ccRecipients = $smtpReady['ccRecipients'];

        if ($dbTemplate && !empty($dbTemplate->body_html)) {
            $finalSubject = $dbTemplate->renderSubject($tokens) ?: $defaultSubject;
            $finalHtml = $dbTemplate->renderBody($tokens);

            Mail::send([], [], function ($message) use ($toEmail, $toName, $finalSubject, $finalHtml, $settings, $appName, $ccRecipients) {
                $message->to($toEmail, $toName)
                    ->subject($finalSubject)
                    ->from($settings->smtp_user, $appName)
                    ->html($finalHtml);

                if (!empty($ccRecipients) && !in_array($toEmail, $ccRecipients, true)) {
                    $message->cc($ccRecipients);
                }
            });
        } else {
            Mail::send($bladeView, $viewData, function ($message) use ($toEmail, $toName, $defaultSubject, $settings, $appName, $ccRecipients) {
                $message->to($toEmail, $toName)
                    ->subject($defaultSubject)
                    ->from($settings->smtp_user, $appName);

                if (!empty($ccRecipients) && !in_array($toEmail, $ccRecipients, true)) {
                    $message->cc($ccRecipients);
                }
            });
        }
    }
}
