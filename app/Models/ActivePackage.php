<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'userId',
        'packageId',
        'startDate',
        'endDate',
        'day',
        'status',
        'usedMsgCount',
        'custom_msg_count',
        'custom_msg_price',
        'custom_contact_count',
        'custom_contact_price',
        'custom_template_count',
        'custom_template_price',
        'custom_price',
        'lastMonthlyReset',
        'monthlyUsedMsgCount',
        'monthlyUsedTemplateCount',
        'monthlyUsedContactCount',
    ];

    public const STATUS_EXPIRED = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_UPCOMING = 2;

    protected $casts = [
        'startDate' => 'datetime',
        'endDate' => 'datetime',
        'lastMonthlyReset' => 'datetime',
    ];

    /**
     * Resolve package status from dates: upcoming if start is in the future.
     */
    public static function statusFromDates($startDate, $endDate): int
    {
        $now = now();
        $end = $endDate ? \Carbon\Carbon::parse($endDate) : null;
        $start = $startDate ? \Carbon\Carbon::parse($startDate) : null;

        if ($end && $now->gt($end)) {
            return self::STATUS_EXPIRED;
        }

        if ($start && $now->lt($start)) {
            return self::STATUS_UPCOMING;
        }

        return self::STATUS_ACTIVE;
    }

    public function computedStatus(): int
    {
        if (isset($this->status) && (int) $this->status === self::STATUS_EXPIRED) {
            return self::STATUS_EXPIRED;
        }

        return self::statusFromDates($this->startDate, $this->endDate);
    }

    public function statusLabel(): string
    {
        return match ($this->computedStatus()) {
            self::STATUS_UPCOMING => 'upcoming',
            self::STATUS_EXPIRED => 'expired',
            default => 'active',
        };
    }

    public function isCurrentlyActive(): bool
    {
        return $this->computedStatus() === self::STATUS_ACTIVE;
    }

    public function isUpcoming(): bool
    {
        return $this->computedStatus() === self::STATUS_UPCOMING;
    }

    public function scopeCurrentlyActive($query)
    {
        $now = now();

        return $query
            ->where('status', '!=', self::STATUS_EXPIRED)
            ->where(function ($q) use ($now) {
                $q->whereNull('startDate')->orWhere('startDate', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('endDate')->orWhere('endDate', '>=', $now);
            });
    }

    /**
     * Get the user that owns the active package.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Get the package for the active package.
     */
    public function package()
    {
        return $this->belongsTo(Package::class, 'packageId');
    }

    /**
     * Check and perform monthly quota reset for yearly plans.
     * Triggers automatically when a new 30-day billing cycle begins within the 1-year validity.
     */
    public function checkAndPerformMonthlyReset(): bool
    {
        // Only yearly plans have monthly recurring quota resets within the active validity
        if (!$this->is_yearly) {
            return false;
        }

        $now = now();

        // If package has expired past endDate, do not reset quotas
        if ($this->endDate && $now->gt($this->endDate)) {
            return false;
        }

        $lastReset = $this->lastMonthlyReset 
            ? \Carbon\Carbon::parse($this->lastMonthlyReset) 
            : ($this->startDate ? \Carbon\Carbon::parse($this->startDate) : null);

        // If never initialized, initialize it to start date (or now)
        if (!$lastReset) {
            $this->lastMonthlyReset = $this->startDate ?: $now;
            $this->monthlyUsedMsgCount = $this->monthlyUsedMsgCount ?? 0;
            $this->monthlyUsedContactCount = $this->monthlyUsedContactCount ?? 0;
            $this->monthlyUsedTemplateCount = $this->monthlyUsedTemplateCount ?? 0;
            if ($this->exists) {
                $this->save();
            }
            return false;
        }

        // Check if 30 days have elapsed since last monthly reset
        $daysSinceReset = $lastReset->diffInDays($now);
        if ($daysSinceReset >= 30) {
            $usedThisMonth = (int) ($this->monthlyUsedMsgCount ?? 0);
            $limitThisMonth = $this->resolvedMessageLimit();

            $this->monthlyUsedMsgCount = 0;
            $this->monthlyUsedContactCount = 0;
            $this->monthlyUsedTemplateCount = 0;
            $this->lastMonthlyReset = $now;
            if ($this->exists) {
                $this->save();
            }

            // Create an audit history record for this completed monthly cycle
            try {
                if ($this->userId) {
                    \App\Models\UserPackageHistory::recordOnce([
                        'userId' => $this->userId,
                        'packageId' => $this->packageId,
                        'packageName' => $this->packageModel()?->packageName ?? 'Yearly Plan',
                        'action' => 'monthly_cycle_reset',
                        'msgCount' => $limitThisMonth,
                        'startDate' => $lastReset,
                        'endDate' => $now,
                        'day' => 30,
                        'notes' => "Monthly cycle completed: {$usedThisMonth} of {$limitThisMonth} messages sent. Monthly quota refreshed for the new 30-day cycle.",
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::warning("Failed to record monthly cycle history: " . $e->getMessage());
            }

            \Illuminate\Support\Facades\Log::info("Automatic on-the-fly monthly quota reset applied for Yearly Plan", [
                'activePackageId' => $this->id ?? null,
                'userId' => $this->userId,
                'usedLastMonth' => $usedThisMonth,
                'packageName' => $this->packageModel()?->packageName ?? 'Yearly Plan',
                'newMonthlyReset' => $now->toDateTimeString(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Check if package needs manual reactivation after 1 year
     */
    public function needsManualReactivation()
    {
        $now = now();
        return $now->gt($this->endDate) && $this->status == 0;
    }

    /**
     * Check if package is in monthly renewal period (within 1 year)
     */
    public function isInMonthlyRenewalPeriod()
    {
        $now = now();
        return $now->lte($this->endDate)
            && $this->isCurrentlyActive();
    }

    /**
     * Get days remaining until package expires
     */
    public function getDaysUntilExpiry()
    {
        $now = now();
        if ($now->gt($this->endDate)) {
            return 0;
        }

        return $now->diffInDays($this->endDate);
    }

    protected function packageModel(): ?Package
    {
        if ($this->relationLoaded('package')) {
            return $this->package;
        }

        return $this->package()->first();
    }

    /**
     * Base message limit from the assigned package (before add-ons).
     */
    public function baseMessageLimit(): int
    {
        $pkg = $this->packageModel();
        if (!$pkg) {
            return 0;
        }

        return (int) ($pkg->msgCount ?? $pkg->msg_count ?? 0);
    }

    /**
     * Base contact limit from the assigned package (before add-ons).
     */
    public function baseContactLimit(): int
    {
        $pkg = $this->packageModel();
        if (!$pkg) {
            return 0;
        }

        return (int) ($pkg->contactCount ?? $pkg->contact_count ?? 0);
    }

    /**
     * Contact limit from assigned plan (custom override wins = base + add-on).
     */
    public function resolvedContactLimit(): int
    {
        if ($this->custom_contact_count !== null && (int) $this->custom_contact_count > 0) {
            return (int) $this->custom_contact_count;
        }

        return $this->baseContactLimit();
    }

    /**
     * Message limit from assigned plan (custom override wins = base + add-on).
     */
    public function resolvedMessageLimit(): int
    {
        if ($this->custom_msg_count !== null && (int) $this->custom_msg_count > 0) {
            return (int) $this->custom_msg_count;
        }

        return $this->baseMessageLimit();
    }

    /**
     * Extra messages added on top of the base package.
     */
    public function addonMessageCount(): int
    {
        return (int) max(0, $this->resolvedMessageLimit() - $this->baseMessageLimit());
    }

    /**
     * Extra contacts added on top of the base package.
     */
    public function addonContactCount(): int
    {
        return (int) max(0, $this->resolvedContactLimit() - $this->baseContactLimit());
    }

    /**
     * Template limit from assigned plan (custom override wins).
     */
    public function resolvedTemplateLimit(): int
    {
        if ($this->custom_template_count !== null && (int) $this->custom_template_count > 0) {
            return (int) $this->custom_template_count;
        }

        $pkg = $this->packageModel();
        if (!$pkg) {
            return 0;
        }

        return (int) ($pkg->templateCount ?? $pkg->template_count ?? 0);
    }

    /**
     * Determine if this active package represents a yearly plan.
     */
    public function getIsYearlyAttribute(): bool
    {
        $pkg = $this->packageModel();
        if ($pkg && $pkg->is_yearly) {
            return true;
        }
        if ($pkg && $pkg->is_monthly) {
            return false;
        }
        return (int) ($this->day ?? 0) >= 300;
    }

    /**
     * Determine if this active package represents a monthly plan.
     */
    public function getIsMonthlyAttribute(): bool
    {
        return !$this->is_yearly;
    }

    /**
     * Billing cycle name ('yearly' or 'monthly').
     */
    public function getBillingCycleAttribute(): string
    {
        return $this->is_yearly ? 'yearly' : 'monthly';
    }

    /**
     * Billing period name ('yearly' or 'monthly').
     */
    public function getBillingPeriodAttribute(): string
    {
        return $this->is_yearly ? 'yearly' : 'monthly';
    }

    /**
     * Normalized period suffix ('/year' or '/month').
     */
    public function getPeriodAttribute(): string
    {
        return $this->is_yearly ? '/year' : '/month';
    }

    /**
     * Human readable duration text.
     */
    public function getDurationTextAttribute(): string
    {
        if ($this->is_yearly) {
            return '1 Year';
        }
        $days = (int) ($this->day ?? 30);
        if ($days >= 28 && $days <= 31) {
            return 'Per Month';
        }
        return "{$days} days";
    }

    /**
     * Determine if this is a custom plan (custom limits/prices or custom package).
     */
    public function getIsCustomAttribute(): bool
    {
        $pkg = $this->packageModel();
        return $this->custom_msg_count !== null
            || $this->custom_contact_count !== null
            || $this->custom_template_count !== null
            || $this->custom_price !== null
            || empty($this->packageId)
            || ($pkg && stripos($pkg->packageName, 'custom') !== false);
    }

    /**
     * Next subscription renewal date.
     * Yearly plan renewals are based on 1 Year (365 days) from start, or endDate.
     * Monthly plan renewals are based on 1 Month (30 days) from start, or endDate.
     */
    public function nextRenewalDate(): ?\Carbon\Carbon
    {
        if ($this->endDate) {
            return \Carbon\Carbon::parse($this->endDate);
        }

        $startDate = $this->startDate ? \Carbon\Carbon::parse($this->startDate) : now();
        $days = (int) ($this->day ?: ($this->is_yearly ? 365 : 30));
        return $startDate->copy()->addDays($days);
    }

    /**
     * Next monthly quota reset date (if last monthly reset is set).
     */
    public function nextMonthlyResetDate(): ?\Carbon\Carbon
    {
        if ($this->lastMonthlyReset) {
            return \Carbon\Carbon::parse($this->lastMonthlyReset)->addDays(30);
        }

        return null;
    }

    /**
     * Fetch latest paid order / invoice record for this active plan.
     */
    public function latestPaidOrder(): ?WalletOrder
    {
        return WalletOrder::where('userId', $this->userId)
            ->whereIn('status', ['paid', 'completed'])
            ->where(function ($q) {
                if ($this->packageId) {
                    $q->where('package_id', $this->packageId)
                      ->orWhereIn('payment_type', ['subscription', 'renewal', 'upgrade', 'custom_plan']);
                } else {
                    $q->whereIn('payment_type', ['subscription', 'renewal', 'upgrade', 'custom_plan']);
                }
            })
            ->orderBy('id', 'desc')
            ->first();
    }

    /**
     * Consistent plan payload for admin + app APIs (limits, add-ons, dates, renewal, GST).
     */
    public function toPlanPayload(): array
    {
        $this->checkAndPerformMonthlyReset();

        $pkg = $this->packageModel();
        $startDate = $this->startDate ? \Carbon\Carbon::parse($this->startDate) : null;
        $endDate = $this->endDate ? \Carbon\Carbon::parse($this->endDate) : null;
        $renewalDate = $this->nextRenewalDate();
        $baseMsg = $this->baseMessageLimit();
        $totalMsg = $this->resolvedMessageLimit();
        $addonMsg = $this->addonMessageCount();
        $baseContact = $this->baseContactLimit();
        $totalContact = $this->resolvedContactLimit();
        $addonContact = $this->addonContactCount();
        $templateLimit = $this->resolvedTemplateLimit();

        // Yearly vs Monthly Billing Cycle resolution
        $isYearly = $this->is_yearly;
        $isMonthly = $this->is_monthly;

        $allTimeUsedMessages = (int) ($this->usedMsgCount ?? 0);
        $monthlyUsedMessages = (int) ($this->monthlyUsedMsgCount ?? 0);
        // For yearly plans: each month user receives fresh monthly quota ($totalMsg)
        $currentUsedMessages = $isYearly ? $monthlyUsedMessages : $allTimeUsedMessages;
        $remainingMessages = (int) max(0, $totalMsg - $currentUsedMessages);

        $basePrice = (float) ($pkg?->price ?? 0);
        $price = $this->custom_price !== null
            ? (float) $this->custom_price
            : $basePrice;

        $daysRemaining = $endDate ? (int) max(0, ceil(now()->diffInDays($endDate, false))) : 0;
        $isExpired = $endDate ? now()->gt($endDate) : true;
        $needsRenewal = $isExpired || $daysRemaining <= 7;
        $renewalPrice = $pkg ? ($pkg->renewal_price ?: $pkg->price) : $price;
        $effectiveRenewalPrice = $pkg ? $pkg->getEffectiveRenewalPrice() : $basePrice;

        // Yearly vs Monthly Billing Cycle resolution
        $isYearly = $this->is_yearly;
        $isMonthly = $this->is_monthly;
        $billingCycle = $this->billing_cycle;
        $billingPeriod = $this->billing_period;
        $period = $this->period;
        $periodLabel = $isYearly ? 'Yearly' : 'Monthly';
        $durationText = $this->duration_text;
        $isCustom = $this->is_custom;

        $renewalDateIso = $renewalDate ? $renewalDate->toIso8601String() : null;
        $formattedRenewalDate = $renewalDate ? $renewalDate->format('d M, Y') : null;
        $nextMonthlyReset = $this->nextMonthlyResetDate();
        $nextMonthlyResetIso = $nextMonthlyReset ? $nextMonthlyReset->toIso8601String() : null;

        // GST & Payment Details applied while payment
        $latestOrder = $this->latestPaidOrder();

        $user = $this->user ?: User::find($this->userId);
        $orderNotes = [];
        if ($latestOrder && !empty($latestOrder->notes)) {
            $orderNotes = is_string($latestOrder->notes)
                ? (json_decode($latestOrder->notes, true) ?: [])
                : (is_array($latestOrder->notes) ? $latestOrder->notes : []);
        }

        $invoiceService = app(\App\Services\InvoiceService::class);
        $billingState = $orderNotes['billing_state'] 
            ?? ($orderNotes['state'] 
            ?? ($orderNotes['customer_state'] 
            ?? ($user?->companyState 
            ?? ($user?->state 
            ?? ($invoiceService->extractStateFromAddress($user?->companyAddress) ?: 'Gujarat')))));

        $isCustomerGujarat = (!empty($billingState) && (stripos($billingState, 'gujarat') !== false || strcasecmp(trim($billingState), 'GJ') === 0));

        if ($latestOrder) {
            $baseAmount = (float) ($latestOrder->base_amount ?? $latestOrder->amount);
            $gstRate = (float) ($latestOrder->gst_percentage ?? 0);
            $gstAmount = (float) ($latestOrder->gst_amount ?? 0);
            $totalAmount = (float) ($latestOrder->total_amount ?? ($baseAmount + $gstAmount));
            $invoiceNumber = $latestOrder->invoice_number;
            $receipt = $latestOrder->receipt;
            $paymentMethod = $latestOrder->payment_method ?: ($latestOrder->razorpay_payment_id ? 'razorpay' : 'wallet');
            $paymentType = $latestOrder->payment_type ?: 'subscription';
            $paidAt = $latestOrder->updated_at ? $latestOrder->updated_at->toIso8601String() : null;
            $gstApplied = ($gstAmount > 0 || $gstRate > 0);
        } else {
            $gstConfig = app(\App\Services\WalletService::class)->gstConfig();
            $gstRate = $gstConfig['enabled']
                ? ((isset($pkg->gst_percentage) && is_numeric($pkg->gst_percentage)) ? (float) $pkg->gst_percentage : $gstConfig['percentage'])
                : 0.0;
            $baseAmount = $price;
            $gstAmount = $gstRate > 0 ? round(($baseAmount * $gstRate) / 100, 2) : 0.0;
            $totalAmount = round($baseAmount + $gstAmount, 2);
            $invoiceNumber = null;
            $receipt = null;
            $paymentMethod = null;
            $paymentType = 'subscription';
            $paidAt = null;
            $gstApplied = ($gstAmount > 0);
        }

        if ($isCustomerGujarat) {
            $taxType = 'INTRA_STATE';
            $cgstRate = round($gstRate / 2, 2);
            $cgstAmount = round($gstAmount / 2, 2);
            $sgstRate = round($gstRate / 2, 2);
            $sgstAmount = round($gstAmount / 2, 2);
            $igstRate = 0.0;
            $igstAmount = 0.0;
        } else {
            $taxType = 'INTER_STATE';
            $cgstRate = 0.0;
            $cgstAmount = 0.0;
            $sgstRate = 0.0;
            $sgstAmount = 0.0;
            $igstRate = $gstRate;
            $igstAmount = $gstAmount;
        }

        $invoiceUrl = $receipt ? url('/invoices/' . $receipt . '/view') : null;
        $invoiceDownloadUrl = $receipt ? url('/invoices/' . $receipt . '/download') : null;

        $latestHistory = \App\Models\UserPackageHistory::where('userId', $this->userId)->orderByDesc('id')->first();
        $customName = $latestHistory?->packageName;
        $displayName = ($isCustom && $customName) ? $customName : ($pkg?->packageName ?? ($isCustom ? 'Custom Plan' : 'Plan'));

        return [
            'id' => $this->id,
            'packageId' => $this->packageId,
            'packageName' => $displayName,
            'packageDesc' => $pkg?->packageDesc ?? '',
            'status' => $this->computedStatus(),
            'statusLabel' => $this->statusLabel(),
            'isActive' => $this->isCurrentlyActive(),
            'isUpcoming' => $this->isUpcoming(),

            // Yearly / Monthly Billing Cycle
            'billingCycle' => $billingCycle,
            'period' => $period,
            'periodLabel' => $periodLabel,
            'isYearly' => $isYearly,
            'isMonthly' => $isMonthly,
            'durationText' => $durationText,

            // Dates & Renewal Calculations
            'startDate' => $startDate ? $startDate->toIso8601String() : null,
            'endDate' => $endDate ? $endDate->toIso8601String() : null,
            'renewalDate' => $renewalDateIso,
            'formattedRenewalDate' => $formattedRenewalDate,
            'lastMonthlyReset' => $this->lastMonthlyReset
                ? \Carbon\Carbon::parse($this->lastMonthlyReset)->toIso8601String()
                : null,
            'nextMonthlyReset' => $nextMonthlyResetIso,
            'days' => (int) ($this->day ?? ($pkg?->day ?? ($isYearly ? 365 : 30))),
            'daysRemaining' => $daysRemaining,
            'isExpired' => $isExpired,
            'needsRenewal' => $needsRenewal,

            // Quotas & Limits
            'baseMsgCount' => $baseMsg,
            'addonMsgCount' => $addonMsg,
            'msgCount' => $totalMsg,
            'usedMsgCount' => $allTimeUsedMessages,
            'monthlyUsedMsgCount' => $monthlyUsedMessages,
            'usedMessages' => $currentUsedMessages,
            'remainingMessages' => $remainingMessages,
            'baseContactCount' => $baseContact,
            'addonContactCount' => $addonContact,
            'contactCount' => $totalContact,
            'templateCount' => $templateLimit,

            // Custom Plan Details
            'isCustom' => $isCustom,
            'custom_msg_count' => $this->custom_msg_count,
            'custom_contact_count' => $this->custom_contact_count,
            'custom_template_count' => $this->custom_template_count,
            'custom_price' => $this->custom_price,
            'custom_details' => [
                'isCustom' => $isCustom,
                'custom_msg_count' => $this->custom_msg_count,
                'custom_contact_count' => $this->custom_contact_count,
                'custom_template_count' => $this->custom_template_count,
                'custom_price' => $this->custom_price,
                'has_custom_messages' => $this->custom_msg_count !== null,
                'has_custom_contacts' => $this->custom_contact_count !== null,
                'has_custom_templates' => $this->custom_template_count !== null,
                'has_custom_price' => $this->custom_price !== null,
            ],

            // Pricing & Renewal Pricing
            'price' => $price,
            'basePrice' => $basePrice,
            'renewalPrice' => $renewalPrice,
            'effectiveRenewalPrice' => $effectiveRenewalPrice,

            // GST Details
            'gstApplied' => $gstApplied,
            'baseAmount' => $baseAmount,
            'gstPercentage' => $gstRate,
            'gstAmount' => $gstAmount,
            'totalAmount' => $totalAmount,
            'formatted_base_amount' => '₹' . number_format($baseAmount, 2),
            'formatted_gst_amount' => '₹' . number_format($gstAmount, 2),
            'formatted_total_amount' => '₹' . number_format($totalAmount, 2),
            'taxType' => $taxType,
            'billingState' => $billingState,
            'is_gujarat' => $isCustomerGujarat,
            'cgst_percentage' => $cgstRate,
            'cgst_amount' => $cgstAmount,
            'sgst_percentage' => $sgstRate,
            'sgst_amount' => $sgstAmount,
            'igst_percentage' => $igstRate,
            'igst_amount' => $igstAmount,
            'invoiceNumber' => $invoiceNumber,
            'invoiceUrl' => $invoiceUrl,
            'invoiceDownloadUrl' => $invoiceDownloadUrl,
            'receipt' => $receipt,
            'payment_method' => $paymentMethod,
            'payment_type' => $paymentType,
            'paid_at' => $paidAt,

            'gst_details' => [
                'gst_applied' => $gstApplied,
                'base_amount' => $baseAmount,
                'gst_percentage' => $gstRate,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'formatted_base_amount' => '₹' . number_format($baseAmount, 2),
                'formatted_gst_amount' => '₹' . number_format($gstAmount, 2),
                'formatted_total_amount' => '₹' . number_format($totalAmount, 2),
                'tax_type' => $taxType,
                'billing_state' => $billingState,
                'is_gujarat' => $isCustomerGujarat,
                'cgst_percentage' => $cgstRate,
                'cgst_amount' => $cgstAmount,
                'sgst_percentage' => $sgstRate,
                'sgst_amount' => $sgstAmount,
                'igst_percentage' => $igstRate,
                'igst_amount' => $igstAmount,
                'invoice_number' => $invoiceNumber,
                'invoice_url' => $invoiceUrl,
                'invoice_download_url' => $invoiceDownloadUrl,
            ],
            'payment_details' => [
                'order_id' => $latestOrder?->id,
                'receipt' => $receipt,
                'invoice_number' => $invoiceNumber,
                'payment_method' => $paymentMethod,
                'payment_type' => $paymentType,
                'paid_at' => $paidAt,
                'base_amount' => $baseAmount,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'invoice_url' => $invoiceUrl,
                'invoice_download_url' => $invoiceDownloadUrl,
            ],
        ];
    }

    /**
     * Increment contact usage counters after add/import.
     */
    public function incrementContactUsage(int $by = 1): void
    {
        if ($by < 1) {
            return;
        }

        $this->increment('monthlyUsedContactCount', $by);
    }

    /**
     * Active package for a company/user (no global fallback).
     */
    public static function forUser(int $userId): ?self
    {
        $activePkg = static::where('userId', $userId)
            ->currentlyActive()
            ->with('package')
            ->latest()
            ->first();

        if ($activePkg) {
            $activePkg->checkAndPerformMonthlyReset();
        }

        return $activePkg;
    }
}
