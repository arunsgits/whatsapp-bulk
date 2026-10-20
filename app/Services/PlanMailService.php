<?php

namespace App\Services;

use App\Models\ActivePackage;
use App\Models\EmailTemplate;
use App\Models\Package;
use App\Models\Settings;
use App\Models\User;
use App\Models\WalletOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PlanMailService
{
    /**
     * Send plan notification email to user and CC admin if enabled.
     *
     * @param User $user
     * @param Package|null $package
     * @param array $options [
     *     'action' => 'subscribed' | 'upgraded' | 'renewed' | 'custom_plan',
     *     'activePkg' => ActivePackage|null,
     *     'order' => WalletOrder|null,
     *     'price' => float|null,
     *     'days' => int|null,
     *     'startDate' => Carbon|string|null,
     *     'endDate' => Carbon|string|null,
     *     'msgCount' => int|null,
     *     'contactCount' => int|null,
     *     'templateCount' => int|null,
     *     'payment_method' => string|null,
     *     'receipt' => string|null,
     * ]
     * @return bool
     */
    public function sendPlanNotification(User $user, ?Package $package, array $options = []): bool
    {
        try {
            $settings = Settings::whereNull('userId')->first() ?: Settings::first();
            if (!$settings || empty($settings->smtp_host) || empty($settings->smtp_user)) {
                Log::info("PlanMailService: SMTP not configured, skipping plan notification email.");
                return false;
            }

            // Dynamically configure Laravel mailer
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

            // Determine recipient and CC
            $userEmail = trim((string) ($user->email ?: ''));
            $isUserEmailValid = filter_var($userEmail, FILTER_VALIDATE_EMAIL) !== false;

            $ccEnabled = (bool) ($settings->smtp_cc_enabled ?? false);
            $ccEmails = $ccEnabled ? $settings->getCcEmails() : [];
            $isCcValid = !empty($ccEmails);

            if (!$isUserEmailValid && !$isCcValid) {
                Log::warning("PlanMailService: Neither user email nor CC email is valid. Aborting mail.");
                return false;
            }

            $primaryRecipient = $isUserEmailValid ? $userEmail : $ccEmails[0];
            $ccRecipients = [];
            if ($ccEnabled) {
                foreach ($ccEmails as $cEmail) {
                    if (strcasecmp($userEmail, $cEmail) !== 0) {
                        $ccRecipients[] = $cEmail;
                    }
                }
            }

            // Extract plan details
            $activePkg = $options['activePkg'] ?? null;
            $order = $options['order'] ?? null;
            $action = strtolower((string) ($options['action'] ?? 'activated'));

            $packageName = $package?->packageName ?: ($activePkg?->package?->packageName ?: 'Custom Subscription Plan');
            $days = (int) ($options['days'] ?? ($activePkg?->day ?: ($package?->day ?: 30)));
            $periodLabel = ($days >= 28 && $days <= 31) ? 'Per Month' : (($days >= 360 && $days <= 366) ? 'Per Year' : "{$days} Days");

            $startDate = $options['startDate'] ?? ($activePkg?->startDate ?? now());
            $endDate = $options['endDate'] ?? ($activePkg?->endDate ?? now()->addDays($days));
            $startDateFmt = $startDate ? Carbon::parse($startDate)->format('d M Y') : now()->format('d M Y');
            $endDateFmt = $endDate ? Carbon::parse($endDate)->format('d M Y') : now()->addDays($days)->format('d M Y');

            $msgCount = $options['msgCount'] ?? ($activePkg?->custom_msg_count ?: ($package?->msgCount ?? null));
            $contactCount = $options['contactCount'] ?? ($activePkg?->custom_contact_count ?: ($package?->contactCount ?? null));
            $templateCount = $options['templateCount'] ?? ($activePkg?->custom_template_count ?: ($package?->templateCount ?? null));

            // Price & Tax breakdown
            $totalPrice = isset($options['price']) ? (float) $options['price'] : ($order ? (float) $order->total_amount : (float) ($activePkg?->custom_price ?: ($package?->price ?? 0)));
            $baseAmount = $order ? (float) ($order->base_amount ?: $totalPrice) : $totalPrice;
            $gstAmount = $order ? (float) ($order->gst_amount ?? 0) : 0;
            $gstRate = $order ? (float) ($order->gst_percentage ?? 18) : 18;

            $receiptId = $options['receipt'] ?? ($order?->receipt ?: ($order?->razorpay_order_id ?: null));
            $paymentMethod = $options['payment_method'] ?? ($order?->payment_method ?: ($order ? 'Razorpay' : 'Admin Allocation'));

            // Subject and headline by action
            $actionName = match ($action) {
                'upgrade', 'upgraded' => 'Upgraded',
                'renewal', 'renewed' => 'Renewed',
                'custom_plan', 'custom' => 'Allocated (Custom Plan)',
                default => 'Activated',
            };

            $subject = "Subscription {$actionName}: {$packageName} - {$appName}";
            $headline = "Subscription Plan {$actionName}";

            $viewData = [
                'subjectTitle' => $subject,
                'appName' => $appName,
                'actionType' => $actionName,
                'headline' => $headline,
                'userName' => $user->name ?: 'Subscriber',
                'userEmail' => $userEmail,
                'packageName' => $packageName,
                'periodLabel' => $periodLabel,
                'startDateFormatted' => $startDateFmt,
                'endDateFormatted' => $endDateFmt,
                'msgCount' => $msgCount,
                'contactCount' => $contactCount,
                'templateCount' => $templateCount,
                'baseAmountFormatted' => $baseAmount > 0 ? number_format($baseAmount, 2) : null,
                'gstAmountFormatted' => $gstAmount > 0 ? number_format($gstAmount, 2) : null,
                'gstRateDisplay' => (int) $gstRate == $gstRate ? (int) $gstRate : $gstRate,
                'totalAmountFormatted' => number_format($totalPrice, 2),
                'receiptId' => $receiptId,
                'paymentMethod' => $paymentMethod,
                'dashboardUrl' => rtrim(config('app.url'), '/') . '/dashboard',
                'isCcCopy' => !empty($ccRecipients),
                'ccRecipientEmail' => !empty($ccRecipients) ? implode(', ', $ccRecipients) : null,
            ];

            // Check for dynamic template in database
            $templateSlug = match ($action) {
                'upgrade', 'upgraded' => 'plan_upgrade',
                'renewal', 'renewed', 'renew' => 'plan_renew',
                'custom_plan', 'custom' => 'custom_plan',
                default => 'plan_purchase',
            };

            $dbTemplate = EmailTemplate::where('slug', $templateSlug)
                ->where('is_active', true)
                ->first();

            // Fallback to plan_purchase if custom_plan template is not active/defined
            if (!$dbTemplate && $templateSlug === 'custom_plan') {
                $dbTemplate = EmailTemplate::where('slug', 'plan_purchase')
                    ->where('is_active', true)
                    ->first();
            }

            if ($dbTemplate && !empty($dbTemplate->body_html)) {
                // Compile dynamic placeholders
                $tokens = [
                    'user_name' => $user->name ?: 'Subscriber',
                    'userName' => $user->name ?: 'Subscriber',
                    'user_email' => $userEmail,
                    'userEmail' => $userEmail,
                    'app_name' => $appName,
                    'appName' => $appName,
                    'plan_name' => $packageName,
                    'packageName' => $packageName,
                    'action_type' => $actionName,
                    'actionType' => $actionName,
                    'headline' => $headline,
                    'period' => $periodLabel,
                    'period_label' => $periodLabel,
                    'periodLabel' => $periodLabel,
                    'days' => (string) $days,
                    'start_date' => $startDateFmt,
                    'startDateFormatted' => $startDateFmt,
                    'end_date' => $endDateFmt,
                    'endDateFormatted' => $endDateFmt,
                    'expiry_date' => $endDateFmt,
                    'messages' => $msgCount !== null ? number_format((int) $msgCount) : '-',
                    'msgCount' => $msgCount !== null ? number_format((int) $msgCount) : '-',
                    'contacts' => $contactCount !== null ? number_format((int) $contactCount) : '-',
                    'contactCount' => $contactCount !== null ? number_format((int) $contactCount) : '-',
                    'templates' => $templateCount !== null ? number_format((int) $templateCount) : '-',
                    'templateCount' => $templateCount !== null ? number_format((int) $templateCount) : '-',
                    'base_amount' => $baseAmount > 0 ? number_format($baseAmount, 2) : '0.00',
                    'baseAmountFormatted' => $baseAmount > 0 ? number_format($baseAmount, 2) : '0.00',
                    'gst_amount' => $gstAmount > 0 ? number_format($gstAmount, 2) : '0.00',
                    'gstAmountFormatted' => $gstAmount > 0 ? number_format($gstAmount, 2) : '0.00',
                    'gst_rate' => (string) ((int) $gstRate == $gstRate ? (int) $gstRate : $gstRate),
                    'gstRateDisplay' => (string) ((int) $gstRate == $gstRate ? (int) $gstRate : $gstRate),
                    'total_amount' => number_format($totalPrice, 2),
                    'totalAmountFormatted' => number_format($totalPrice, 2),
                    'amount' => number_format($totalPrice, 2),
                    'currency' => '₹',
                    'receipt_id' => (string) ($receiptId ?: 'N/A'),
                    'receiptId' => (string) ($receiptId ?: 'N/A'),
                    'order_id' => (string) ($receiptId ?: 'N/A'),
                    'payment_method' => ucfirst((string) $paymentMethod),
                    'paymentMethod' => ucfirst((string) $paymentMethod),
                    'dashboard_url' => rtrim(config('app.url'), '/') . '/dashboard',
                    'dashboardUrl' => rtrim(config('app.url'), '/') . '/dashboard',
                    'year' => date('Y'),
                    'is_cc' => !empty($ccRecipients),
                    'isCcCopy' => !empty($ccRecipients),
                    'cc_email' => !empty($ccRecipients) ? implode(', ', $ccRecipients) : '',
                    'ccRecipientEmail' => !empty($ccRecipients) ? implode(', ', $ccRecipients) : '',
                    'cc_notice' => !empty($ccRecipients) ? 'Admin Notice: A carbon copy (CC) of this plan notification was sent to ' . implode(', ', $ccRecipients) . ' as per system SMTP settings.' : '',
                ];

                $finalSubject = $dbTemplate->renderSubject($tokens);
                $finalHtml = $dbTemplate->renderBody($tokens);

                Mail::send([], [], function ($message) use ($primaryRecipient, $user, $ccRecipients, $finalSubject, $finalHtml, $settings, $appName) {
                    $message->to($primaryRecipient, $user->name ?: 'Subscriber')
                        ->subject($finalSubject)
                        ->from($settings->smtp_user, $appName)
                        ->html($finalHtml);

                    if (!empty($ccRecipients)) {
                        $message->cc($ccRecipients);
                    }
                });

                Log::info("PlanMailService: Rendered dynamic DB email template '{$dbTemplate->slug}' for user {$user->id}");
            } else {
                // Fallback to blade view
                Mail::send('emails.plan-notification', $viewData, function ($message) use ($primaryRecipient, $user, $ccRecipients, $subject, $settings, $appName) {
                    $message->to($primaryRecipient, $user->name ?: 'Subscriber')
                        ->subject($subject)
                    ->from($settings->smtp_user, $appName);

                    if (!empty($ccRecipients)) {
                        $message->cc($ccRecipients);
                    }
                });
            }

            Log::info("PlanMailService: Notification email successfully dispatched for user {$user->id} ({$primaryRecipient})" . (!empty($ccRecipients) ? " with CC to " . implode(', ', $ccRecipients) : ''));
            return true;
        } catch (\Throwable $e) {
            Log::error("PlanMailService: Error sending plan notification email: " . $e->getMessage(), [
                'userId' => $user->id,
                'packageId' => $package?->id,
                'exception' => $e
            ]);
            return false;
        }
    }
}
