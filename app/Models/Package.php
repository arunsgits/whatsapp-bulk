<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    use HasFactory;

    protected $fillable = [
        'packageName',
        'packageDesc',
        'day',
        'msgCount',
        'templateCount',
        'contactCount',
        'price',
        'renewal_price',
        'period',
        'popular',
        'color',
        'iconName',
        'features',
        'gst_percentage',
    ];

    protected $casts = [
        'popular' => 'boolean',
        'features' => 'array',
        'gst_percentage' => 'decimal:2',
    ];

    protected $appends = [
        'is_monthly',
        'is_yearly',
        'billing_period',
    ];

    /**
     * Determine if this package is a Monthly plan.
     */
    public function getIsMonthlyAttribute(): bool
    {
        $period = strtolower((string) ($this->period ?? ''));
        if (str_contains($period, 'year')) {
            return false;
        }
        if (str_contains($period, 'month')) {
            return true;
        }
        return (int) ($this->day ?? 30) <= 90;
    }

    /**
     * Determine if this package is a Yearly plan.
     */
    public function getIsYearlyAttribute(): bool
    {
        $period = strtolower((string) ($this->period ?? ''));
        if (str_contains($period, 'year')) {
            return true;
        }
        if (str_contains($period, 'month')) {
            return false;
        }
        return (int) ($this->day ?? 30) >= 300;
    }

    /**
     * Get human-readable billing cycle name (monthly or yearly).
     */
    public function getBillingPeriodAttribute(): string
    {
        if ($this->is_yearly) {
            return 'yearly';
        }
        if ($this->is_monthly) {
            return 'monthly';
        }
        return $this->period ? trim($this->period, '/') : 'monthly';
    }

    /**
     * Normalized period suffix (/month or /year).
     */
    public function getNormalizedPeriodAttribute(): string
    {
        return $this->is_yearly ? '/year' : '/month';
    }

    /**
     * Scope query to only Monthly plans.
     */
    public function scopeMonthly($query)
    {
        return $query->where(function ($q) {
            $q->where('period', 'like', '%month%')
              ->orWhere(function ($sub) {
                  $sub->where('day', '<=', 90)
                      ->where(function ($w) {
                          $w->whereNull('period')
                            ->orWhere('period', 'not like', '%year%');
                      });
              });
        });
    }

    /**
     * Scope query to only Yearly plans.
     */
    public function scopeYearly($query)
    {
        return $query->where(function ($q) {
            $q->where('period', 'like', '%year%')
              ->orWhere('day', '>=', 300);
        });
    }

    /**
     * Get the effective renewal price float amount.
     * Falls back to normal price if renewal_price is not set or empty.
     */
     public function getEffectiveRenewalPrice(): float
     {
         $rawRenewal = preg_replace('/[^\d.]/', '', (string) ($this->renewal_price ?? ''));
         if (is_numeric($rawRenewal) && (float) $rawRenewal > 0) {
             return (float) $rawRenewal;
         }

         $rawPrice = preg_replace('/[^\d.]/', '', (string) ($this->price ?? ''));
         return (is_numeric($rawPrice) && (float) $rawPrice > 0) ? (float) $rawPrice : 0.0;
     }

    /**
     * Format package into standardized admin payload.
     */
    public function toAdminArray(): array
    {
        $isYearly = $this->is_yearly;
        $isMonthly = $this->is_monthly;
        $period = $this->normalized_period;
        $billingCycle = $isYearly ? 'yearly' : 'monthly';
        $day = (int) ($this->day ?: ($isYearly ? 365 : 30));

        $rawPrice = preg_replace('/[^\d.]/', '', (string) ($this->price ?? ''));
        $rawPriceNum = is_numeric($rawPrice) && $rawPrice !== '' ? (float) $rawPrice : null;

        $rawRenewal = preg_replace('/[^\d.]/', '', (string) ($this->renewal_price ?? ''));
        $rawRenewalPriceNum = is_numeric($rawRenewal) && $rawRenewal !== '' ? (float) $rawRenewal : $rawPriceNum;
        $displayRenewalPrice = $this->renewal_price ?: $this->price;

        $gstRate = (isset($this->gst_percentage) && is_numeric($this->gst_percentage))
            ? (float) $this->gst_percentage
            : Settings::getGstPercentage();
        $gstAmount = ($rawPriceNum !== null && $gstRate > 0) ? round(($rawPriceNum * $gstRate) / 100, 2) : 0.0;
        $totalPriceWithGst = ($rawPriceNum !== null) ? round($rawPriceNum + $gstAmount, 2) : null;
        $formattedTotalPriceWithGst = $totalPriceWithGst !== null ? ('₹' . number_format($totalPriceWithGst, 2)) : null;

        $renewalGstAmount = ($rawRenewalPriceNum !== null && $gstRate > 0) ? round(($rawRenewalPriceNum * $gstRate) / 100, 2) : 0.0;
        $totalRenewalPriceWithGst = ($rawRenewalPriceNum !== null) ? round($rawRenewalPriceNum + $renewalGstAmount, 2) : null;
        $formattedTotalRenewalPriceWithGst = $totalRenewalPriceWithGst !== null ? ('₹' . number_format($totalRenewalPriceWithGst, 2)) : null;

        return [
            'id' => 'plan_' . $this->id,
            'dbId' => $this->id,
            'packageName' => $this->packageName,
            'packageDesc' => $this->packageDesc ?? '',
            'msgCount' => is_numeric($this->msgCount) ? number_format((int) $this->msgCount) : ($this->msgCount ?? 'Unlimited'),
            'rawMsgCount' => is_numeric($this->msgCount) ? (int) $this->msgCount : $this->msgCount,
            'contactCount' => is_numeric($this->contactCount) ? number_format((int) $this->contactCount) : ($this->contactCount ?? '1,000'),
            'rawContactCount' => is_numeric($this->contactCount) ? (int) $this->contactCount : 1000,
            'templateCount' => (int) ($this->templateCount ?? 50),
            'price' => $this->price ?? ($isYearly ? '₹39,999' : '₹3,999'),
            'rawPrice' => $rawPriceNum,
            'renewalPrice' => $displayRenewalPrice,
            'rawRenewalPrice' => $rawRenewalPriceNum,
            'gstPercentage' => $gstRate,
            'gstAmount' => $gstAmount,
            'totalPriceWithGst' => $totalPriceWithGst,
            'formattedTotalPriceWithGst' => $formattedTotalPriceWithGst,
            'renewalGstAmount' => $renewalGstAmount,
            'totalRenewalPriceWithGst' => $totalRenewalPriceWithGst,
            'formattedTotalRenewalPriceWithGst' => $formattedTotalRenewalPriceWithGst,
            'priceBreakdown' => [
                'basePrice' => $rawPriceNum,
                'gstPercentage' => $gstRate,
                'gstAmount' => $gstAmount,
                'totalAmount' => $totalPriceWithGst,
            ],
            'renewalPriceBreakdown' => [
                'basePrice' => $rawRenewalPriceNum,
                'gstPercentage' => $gstRate,
                'gstAmount' => $renewalGstAmount,
                'totalAmount' => $totalRenewalPriceWithGst,
            ],
            'period' => $period,
            'billingCycle' => $billingCycle,
            'isMonthly' => $isMonthly,
            'isYearly' => $isYearly,
            'day' => $day,
            'durationText' => $isYearly ? '1 Year' : (($day >= 28 && $day <= 31) ? 'Per Month' : "{$day} days"),
            'popular' => (bool) $this->popular,
            'color' => $this->color ?? 'indigo',
            'iconName' => $this->iconName ?? 'package',
            'features' => $this->features ?? ["Basic broadcast", "Standard support"],
            'activeSubscribersCount' => $this->active_subscribers_count ?? ($this->relationLoaded('activePackages')
                ? $this->activePackages->where('status', ActivePackage::STATUS_ACTIVE)->count()
                : $this->activePackages()->where('status', ActivePackage::STATUS_ACTIVE)->count()),
            'createdAt' => $this->created_at?->toIso8601String(),
            'updatedAt' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get the active packages for the package.
     */
    public function activePackages()
    {
        return $this->hasMany(ActivePackage::class, 'packageId');
    }
}
