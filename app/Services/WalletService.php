<?php

namespace App\Services;

use App\Models\Settings;
use App\Models\User;
use App\Models\Package;
use App\Models\ActivePackage;
use App\Models\UserPackageHistory;
use App\Models\WalletOrder;
use App\Models\WalletTransaction;
use App\Models\MessageDeliveryStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class WalletService
{
    public function ensureSchema(): void
    {
        static $schemaEnsured = false;
        if ($schemaEnsured) {
            return;
        }
        $schemaEnsured = true;

        if (!Schema::hasColumn('users', 'wallet_enabled')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('wallet_enabled')->default(false);
            });
        }
        if (!Schema::hasColumn('users', 'wallet_balance')) {
            Schema::table('users', function (Blueprint $table) {
                $table->decimal('wallet_balance', 12, 2)->default(0);
            });
        }
        if (Schema::hasTable('settings')) {
            if (!Schema::hasColumn('settings', 'razorpay_key_id')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->string('razorpay_key_id')->nullable();
                });
            }
            if (!Schema::hasColumn('settings', 'razorpay_key_secret')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->string('razorpay_key_secret')->nullable();
                });
            }
            if (!Schema::hasColumn('settings', 'razorpay_webhook_secret')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->string('razorpay_webhook_secret')->nullable();
                });
            }
            if (!Schema::hasColumn('settings', 'wallet_min_balance')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->decimal('wallet_min_balance', 12, 2)->default(250)->nullable();
                });
            }
            if (!Schema::hasColumn('settings', 'wallet_price_marketing')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->decimal('wallet_price_marketing', 8, 4)->default(0.86)->nullable();
                });
            }
            if (!Schema::hasColumn('settings', 'wallet_price_utility')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->decimal('wallet_price_utility', 8, 4)->default(0.17)->nullable();
                });
            }
            if (!Schema::hasColumn('settings', 'wallet_price_authentication')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->decimal('wallet_price_authentication', 8, 4)->default(0.17)->nullable();
                });
            }
            if (!Schema::hasColumn('settings', 'gst_percentage')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->decimal('gst_percentage', 5, 2)->default(18.00)->nullable();
                });
            }
            if (!Schema::hasColumn('settings', 'gst_enabled')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->boolean('gst_enabled')->default(true);
                });
            }
            if (!Schema::hasColumn('settings', 'gstin')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->string('gstin', 30)->nullable();
                });
            }
            if (!Schema::hasColumn('settings', 'gst_state')) {
                Schema::table('settings', function (Blueprint $table) {
                    $table->string('gst_state', 100)->nullable();
                });
            }
        }
        if (Schema::hasTable('packages')) {
            try {
                if (!Schema::hasColumn('packages', 'gst_percentage')) {
                    Schema::table('packages', function (Blueprint $table) {
                        $table->decimal('gst_percentage', 5, 2)->nullable();
                    });
                }
                if (!Schema::hasColumn('packages', 'renewal_price')) {
                    Schema::table('packages', function (Blueprint $table) {
                        $table->string('renewal_price')->nullable()->after('price');
                    });
                }
            } catch (\Throwable $e) {}
        }
        if (Schema::hasTable('message_delivery_statuses')) {
            if (!Schema::hasColumn('message_delivery_statuses', 'wallet_charged')) {
                Schema::table('message_delivery_statuses', function (Blueprint $table) {
                    $table->boolean('wallet_charged')->default(false);
                });
            }
            if (!Schema::hasColumn('message_delivery_statuses', 'wallet_amount')) {
                Schema::table('message_delivery_statuses', function (Blueprint $table) {
                    $table->decimal('wallet_amount', 8, 4)->nullable();
                });
            }
        }
        if (!Schema::hasTable('wallet_orders')) {
            Schema::create('wallet_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('userId');
                $table->string('razorpay_order_id')->nullable()->index();
                $table->string('payment_link_id')->nullable()->index();
                $table->text('payment_link_url')->nullable();
                $table->string('payment_type', 50)->nullable()->default('wallet_recharge');
                $table->string('receipt')->nullable();
                $table->decimal('amount', 12, 2);
                $table->unsignedInteger('amount_paise');
                $table->string('currency', 8)->default('INR');
                $table->string('status', 20)->default('pending');
                $table->string('razorpay_payment_id')->nullable();
                $table->string('razorpay_signature')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        } else {
            try {
                if (!Schema::hasColumn('wallet_orders', 'base_amount')) {
                    Schema::table('wallet_orders', function (Blueprint $table) {
                        $table->decimal('base_amount', 12, 2)->nullable();
                    });
                }
                if (!Schema::hasColumn('wallet_orders', 'gst_percentage')) {
                    Schema::table('wallet_orders', function (Blueprint $table) {
                        $table->decimal('gst_percentage', 5, 2)->nullable();
                    });
                }
                if (!Schema::hasColumn('wallet_orders', 'gst_amount')) {
                    Schema::table('wallet_orders', function (Blueprint $table) {
                        $table->decimal('gst_amount', 12, 2)->nullable();
                    });
                }
                if (!Schema::hasColumn('wallet_orders', 'total_amount')) {
                    Schema::table('wallet_orders', function (Blueprint $table) {
                        $table->decimal('total_amount', 12, 2)->nullable();
                    });
                }
                if (!Schema::hasColumn('wallet_orders', 'package_id')) {
                    Schema::table('wallet_orders', function (Blueprint $table) {
                        $table->unsignedBigInteger('package_id')->nullable();
                    });
                }
                if (!Schema::hasColumn('wallet_orders', 'payment_link_id')) {
                    Schema::table('wallet_orders', function (Blueprint $table) {
                        $table->string('payment_link_id')->nullable()->after('razorpay_order_id');
                    });
                }
                if (!Schema::hasColumn('wallet_orders', 'payment_link_url')) {
                    Schema::table('wallet_orders', function (Blueprint $table) {
                        $table->text('payment_link_url')->nullable()->after('payment_link_id');
                    });
                }
                if (!Schema::hasColumn('wallet_orders', 'payment_type')) {
                    Schema::table('wallet_orders', function (Blueprint $table) {
                        $table->string('payment_type', 50)->nullable()->default('wallet_recharge')->after('package_id');
                    });
                }
                if (!Schema::hasColumn('wallet_orders', 'payment_method')) {
                    Schema::table('wallet_orders', function (Blueprint $table) {
                        $table->string('payment_method', 50)->nullable()->default('online')->after('payment_type');
                    });
                }
                if (!Schema::hasColumn('wallet_orders', 'invoice_number')) {
                    Schema::table('wallet_orders', function (Blueprint $table) {
                        $table->string('invoice_number', 50)->nullable()->index()->after('receipt');
                    });
                }
            } catch (\Throwable $e) {
                // Silently skip if cannot alter
            }
        }
        if (!Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('userId');
                $table->string('type', 20);
                $table->string('source', 30);
                $table->string('category', 30)->nullable()->index();
                $table->decimal('amount', 12, 2);
                $table->decimal('balance_after', 12, 2)->default(0);
                $table->string('reference_id')->nullable()->index();
                $table->unsignedBigInteger('wallet_order_id')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        } else {
            try {
                if (!Schema::hasColumn('wallet_transactions', 'category')) {
                    Schema::table('wallet_transactions', function (Blueprint $table) {
                        $table->string('category', 30)->nullable()->after('source')->index();
                    });
                }
            } catch (\Throwable $e) {
                // Silently skip if table schema cannot be altered dynamically
            }
        }
    }

    public function ownerUser(User $user): User
    {
        $ownerId = $user->getCompanyId();
        if ($ownerId === (int) $user->id) {
            return $user;
        }
        return User::find($ownerId) ?: $user;
    }

    public function isEnabled(User $user): bool
    {
        $this->ensureSchema();
        $owner = $this->ownerUser($user);
        return (bool) ($owner->wallet_enabled ?? false);
    }

    public function getBalance(User $user): float
    {
        $this->ensureSchema();
        $owner = $this->ownerUser($user);
        return round((float) ($owner->wallet_balance ?? 0), 2);
    }

    public function razorpayKeys(): array
    {
        $this->ensureSchema();
        $settings = Settings::whereNull('userId')->first() ?: Settings::first();

        $keyId = $settings->razorpay_key_id ?? null;
        $keySecret = $settings->razorpay_key_secret ?? null;
        $webhookSecret = $settings->razorpay_webhook_secret ?? null;

        if (!$keyId) {
            $keyId = config('services.razorpay.key');
        }
        if (!$keySecret) {
            $keySecret = config('services.razorpay.secret');
        }
        if (!$webhookSecret) {
            $webhookSecret = config('services.razorpay.webhook_secret');
        }

        return [
            'key' => $keyId ? trim((string) $keyId) : '',
            'secret' => $keySecret ? trim((string) $keySecret) : '',
            'webhook_secret' => $webhookSecret ? trim((string) $webhookSecret) : '',
        ];
    }

    public function setEnabled(User $user, bool $enabled): User
    {
        $this->ensureSchema();
        $user->wallet_enabled = $enabled;
        $user->save();
        return $user;
    }

    /**
     * Create Razorpay order for wallet recharge with optional GST.
     */
    public function createRazorpayOrder(User $user, float $amountInr, string $currency = 'INR', array $options = []): array
    {
        $this->ensureSchema();
        $owner = $this->ownerUser($user);

        if (!$this->isEnabled($owner)) {
            throw new \RuntimeException('Wallet is not enabled for this account. Ask Super Admin to enable it.');
        }

        $keys = $this->razorpayKeys();
        if ($keys['key'] === '' || $keys['secret'] === '') {
            throw new \RuntimeException('Razorpay is not configured. Super Admin must add Razorpay Key ID and Secret.');
        }

        $rawAmount = round($amountInr, 2);
        if ($rawAmount < 1) {
            throw new \InvalidArgumentException('Minimum recharge amount is 1.');
        }

        // GST calculation (using admin settings or option override)
        $gst = $this->gstConfig();
        $gstRate = $gst['enabled'] ? (float) ($options['gst_percentage'] ?? $gst['percentage']) : 0.0;
        $isTaxInclusive = (bool) ($options['tax_inclusive'] ?? false);

        if ($isTaxInclusive) {
            $totalAmount = $rawAmount;
            $baseAmount = $gstRate > 0 ? round($totalAmount / (1 + ($gstRate / 100)), 2) : $totalAmount;
            $gstAmount = round($totalAmount - $baseAmount, 2);
        } else {
            $baseAmount = $rawAmount;
            $gstAmount = $gstRate > 0 ? round(($baseAmount * $gstRate) / 100, 2) : 0.0;
            $totalAmount = round($baseAmount + $gstAmount, 2);
        }

        $amountPaise = (int) round($totalAmount * 100);
        $receipt = 'wlt_' . $owner->id . '_' . time();

        $purpose = $options['purpose'] ?? 'wallet_recharge';
        $packageId = $options['package_id'] ?? null;

        $razorpayNotes = [
            'userId' => (string) $owner->id,
            'purpose' => $purpose,
            'base_amount' => number_format($baseAmount, 2, '.', ''),
            'gst_percentage' => (string) $gstRate,
            'gst_amount' => number_format($gstAmount, 2, '.', ''),
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ];
        if ($packageId) {
            $razorpayNotes['package_id'] = (string) $packageId;
        }

        $response = Http::withBasicAuth($keys['key'], $keys['secret'])
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise,
                'currency' => $currency,
                'receipt' => $receipt,
                'notes' => $razorpayNotes,
            ]);

        if (!$response->successful()) {
            $msg = $response->json('error.description') ?: $response->body();
            throw new \RuntimeException('Razorpay order failed: ' . $msg);
        }

        $data = $response->json();
        $order = WalletOrder::create([
            'userId' => $owner->id,
            'razorpay_order_id' => $data['id'] ?? null,
            'receipt' => $receipt,
            'amount' => $baseAmount,
            'base_amount' => $baseAmount,
            'gst_percentage' => $gstRate,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'amount_paise' => $amountPaise,
            'package_id' => $packageId,
            'currency' => $currency,
            'status' => 'pending',
            'notes' => json_encode($data['notes'] ?? $razorpayNotes),
        ]);

        $description = $gstRate > 0
            ? "Recharge ₹{$baseAmount} + {$gstRate}% GST (₹{$gstAmount}) = Total ₹{$totalAmount}"
            : "Wallet Recharge ₹{$baseAmount}";

        return [
            'order_id' => $data['id'],
            'local_order_id' => $order->id,
            'amount' => $totalAmount,
            'base_amount' => $baseAmount,
            'gst_percentage' => $gstRate,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'amount_paise' => $amountPaise,
            'currency' => $currency,
            'key' => $keys['key'],
            'name' => config('app.name', 'WhatsApp Bulk'),
            'description' => $description,
            'prefill' => [
                'name' => $owner->name,
                'email' => $owner->email,
                'contact' => $owner->number,
            ],
            'notes' => [
                'base_amount' => $baseAmount,
                'gst_percentage' => $gstRate,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
            ]
        ];
    }

    /**
     * Create Razorpay order specifically for purchasing a Package / Subscription Plan with GST.
     */
    public function createPlanRazorpayOrder(User $user, $packageId, string $currency = 'INR'): array
    {
        $this->ensureSchema();
        $owner = $this->ownerUser($user);

        $keys = $this->razorpayKeys();
        if ($keys['key'] === '' || $keys['secret'] === '') {
            throw new \RuntimeException('Razorpay is not configured. Super Admin must add Razorpay Key ID and Secret.');
        }

        $package = Package::find($packageId);
        if (!$package) {
            throw new \InvalidArgumentException('Selected subscription plan not found.');
        }

        $rawPrice = preg_replace('/[^\d.]/', '', (string) ($package->price ?? ''));
        $baseAmount = is_numeric($rawPrice) && (float) $rawPrice > 0 ? (float) $rawPrice : 0;
        if ($baseAmount <= 0) {
            throw new \InvalidArgumentException('This plan has no price set.');
        }

        $gst = $this->gstConfig();
        $gstRate = $gst['enabled']
            ? ((isset($package->gst_percentage) && is_numeric($package->gst_percentage)) ? (float) $package->gst_percentage : $gst['percentage'])
            : 0.0;

        $gstAmount = $gstRate > 0 ? round(($baseAmount * $gstRate) / 100, 2) : 0.0;
        $totalAmount = round($baseAmount + $gstAmount, 2);
        $amountPaise = (int) round($totalAmount * 100);
        $receipt = 'plan_' . $package->id . '_' . $owner->id . '_' . time();

        $razorpayNotes = [
            'userId' => (string) $owner->id,
            'purpose' => 'plan_subscription',
            'package_id' => (string) $package->id,
            'package_name' => (string) $package->packageName,
            'base_amount' => number_format($baseAmount, 2, '.', ''),
            'gst_percentage' => (string) $gstRate,
            'gst_amount' => number_format($gstAmount, 2, '.', ''),
            'total_amount' => number_format($totalAmount, 2, '.', ''),
        ];

        $response = Http::withBasicAuth($keys['key'], $keys['secret'])
            ->acceptJson()
            ->timeout(30)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => $amountPaise,
                'currency' => $currency,
                'receipt' => $receipt,
                'notes' => $razorpayNotes,
            ]);

        if (!$response->successful()) {
            $msg = $response->json('error.description') ?: $response->body();
            throw new \RuntimeException('Razorpay order failed: ' . $msg);
        }

        $data = $response->json();
        $order = WalletOrder::create([
            'userId' => $owner->id,
            'razorpay_order_id' => $data['id'] ?? null,
            'receipt' => $receipt,
            'amount' => $baseAmount,
            'base_amount' => $baseAmount,
            'gst_percentage' => $gstRate,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'amount_paise' => $amountPaise,
            'package_id' => $package->id,
            'currency' => $currency,
            'status' => 'pending',
            'notes' => json_encode($data['notes'] ?? $razorpayNotes),
        ]);

        return [
            'order_id' => $data['id'],
            'local_order_id' => $order->id,
            'package_id' => $package->id,
            'package_name' => $package->packageName,
            'amount' => $totalAmount,
            'base_amount' => $baseAmount,
            'gst_percentage' => $gstRate,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'amount_paise' => $amountPaise,
            'currency' => $currency,
            'key' => $keys['key'],
            'name' => config('app.name', 'WhatsApp Bulk'),
            'description' => "Plan: {$package->packageName} - ₹{$baseAmount} + {$gstRate}% GST (₹{$gstAmount}) = Total ₹{$totalAmount}",
            'prefill' => [
                'name' => $owner->name,
                'email' => $owner->email,
                'contact' => $owner->number,
            ],
            'notes' => $razorpayNotes,
        ];
    }

    /**
     * Create Razorpay Payment Link and Order for Subscription Plan Renewal.
     * Uses the plan's renewal_price (falling back to standard price).
     */
    public function createRenewalPaymentLink(User $user, $packageId = null, array $options = []): array
    {
        return $this->createPlanPaymentLink($user, $packageId, 'renewal', $options);
    }

    /**
     * Create Razorpay Payment Link and Order for Subscription Plan (Buy, Upgrade, or Renew).
     * Type: 'subscription' | 'upgrade' | 'renewal'
     */
    public function createPlanPaymentLink(User $user, $packageId = null, string $type = 'subscription', array $options = []): array
    {
        $this->ensureSchema();
        $owner = $this->ownerUser($user);

        // 1. Resolve Package
        $package = null;
        if ($packageId) {
            $rawId = is_numeric($packageId) ? (int) $packageId : (int) str_replace('plan_', '', (string) $packageId);
            $package = Package::find($rawId);
        }

        $activePkg = ActivePackage::where('userId', $owner->id)->latest('id')->first();

        if (!$package && $type === 'renewal') {
            $package = $activePkg?->package ?: ($activePkg ? Package::find($activePkg->packageId) : null);
        }

        if (!$package && $activePkg) {
            $package = $activePkg->package ?: Package::find($activePkg->packageId);
        }

        if (!$package) {
            $package = Package::first();
        }

        if (!$package) {
            throw new \RuntimeException('No subscription plan found.');
        }

        // Determine if this is a custom plan (either explicit in options or renewal of existing custom plan)
        $isExplicitCustom = filter_var($options['is_custom_plan'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || filter_var($options['custom_plan'] ?? false, FILTER_VALIDATE_BOOLEAN)
            || ($type === 'custom_plan');
        $isRenewalOfCustom = ($type === 'renewal' && $activePkg && $activePkg->is_custom);
        $isCustom = $isExplicitCustom || $isRenewalOfCustom;

        // Resolve custom plan display name
        $latestHistory = \App\Models\UserPackageHistory::where('userId', $owner->id)->orderByDesc('id')->first();
        $customNameFromHistory = ($activePkg && $activePkg->is_custom) ? $latestHistory?->packageName : null;
        $customPlanName = $options['custom_plan_name'] 
            ?? ($options['customPlanName'] 
            ?? ($options['plan_name'] 
            ?? ($options['packageName'] 
            ?? $customNameFromHistory)));

        $planDisplayName = ($isCustom && $customPlanName)
            ? $customPlanName
            : ($package->packageName ?: 'Custom Plan');

        // Resolve duration (days)
        $customDays = $options['days'] ?? ($options['day'] ?? ($options['duration_days'] ?? null));
        if ($customDays === null && $isCustom && $activePkg && (int) $activePkg->day > 0) {
            $customDays = (int) $activePkg->day;
        }
        $days = (int) ($customDays ?: ($package->day ?: 30));
        $periodLabel = ($days >= 28 && $days <= 31) ? 'Per Month' : (($days >= 360 && $days <= 366) ? 'Per Year' : "{$days} Days");

        // Resolve quotas (messages, contacts, templates)
        $customMsgCount = $options['custom_msg_count'] ?? ($options['msgCount'] ?? ($options['msg_count'] ?? null));
        if ($customMsgCount === null && $isCustom && $activePkg) {
            $customMsgCount = $activePkg->custom_msg_count;
        }
        $resolvedMsgCount = $customMsgCount !== null ? (int) $customMsgCount : (int) ($package->msgCount ?? 0);

        $customContactCount = $options['custom_contact_count'] ?? ($options['contactCount'] ?? ($options['contact_count'] ?? null));
        if ($customContactCount === null && $isCustom && $activePkg) {
            $customContactCount = $activePkg->custom_contact_count;
        }
        $resolvedContactCount = $customContactCount !== null ? (int) $customContactCount : (int) ($package->contactCount ?? 1000);

        $customTemplateCount = $options['custom_template_count'] ?? ($options['templateCount'] ?? ($options['template_count'] ?? null));
        if ($customTemplateCount === null && $isCustom && $activePkg) {
            $customTemplateCount = $activePkg->custom_template_count;
        }
        $resolvedTemplateCount = $customTemplateCount !== null ? (int) $customTemplateCount : (int) ($package->templateCount ?? 50);

        // 2. Resolve Price
        $explicitPrice = $options['custom_price'] 
            ?? ($options['customPrice'] 
            ?? ($options['renewal_price'] 
            ?? ($options['renewalPrice'] 
            ?? ($options['price'] ?? null))));

        if ($explicitPrice !== null && (float) $explicitPrice > 0) {
            $baseAmount = (float) $explicitPrice;
        } elseif ($isCustom && $activePkg && $activePkg->custom_price !== null && (float) $activePkg->custom_price > 0) {
            $baseAmount = (float) $activePkg->custom_price;
        } elseif ($type === 'renewal') {
            $baseAmount = $package->getEffectiveRenewalPrice();
            if ($baseAmount <= 0) {
                $rawPrice = preg_replace('/[^\d.]/', '', (string) ($package->price ?? ''));
                $baseAmount = is_numeric($rawPrice) && (float) $rawPrice > 0 ? (float) $rawPrice : 0.0;
            }
        } else {
            $rawPrice = preg_replace('/[^\d.]/', '', (string) ($package->price ?? ''));
            $baseAmount = is_numeric($rawPrice) && (float) $rawPrice > 0 ? (float) $rawPrice : 0.0;
        }

        if ($baseAmount <= 0) {
            throw new \InvalidArgumentException("Plan '{$planDisplayName}' does not have a price set.");
        }

        // 3. Resolve Customer Billing State & GST Breakdown
        $gst = $this->gstConfig();
        $sellerState = 'Gujarat';

        $customerState = $options['billing_state'] 
            ?? ($options['state'] 
            ?? ($options['customer_state'] 
            ?? ($options['billingState'] ?? null)));

        if (empty($customerState) && !empty($owner->companyAddress)) {
            $customerState = $this->extractStateFromAddress($owner->companyAddress);
        }

        // Customer billing state = Gujarat → CGST 9% + SGST 9%
        // Customer billing state ≠ Gujarat → IGST 18%
        $isGujarat = false;
        if (!empty($customerState)) {
            $isGujarat = (stripos($customerState, 'gujarat') !== false || strcasecmp(trim($customerState), 'GJ') === 0);
        } else {
            // Default to Gujarat if not explicitly provided
            $isGujarat = true;
            $customerState = 'Gujarat';
        }

        $gstRate = $gst['enabled']
            ? ((isset($package->gst_percentage) && is_numeric($package->gst_percentage)) ? (float) $package->gst_percentage : $gst['percentage'])
            : 0.0;

        $gstAmount = $gstRate > 0 ? round(($baseAmount * $gstRate) / 100, 2) : 0.0;
        $totalAmount = round($baseAmount + $gstAmount, 2);
        $amountPaise = (int) round($totalAmount * 100);
        $currency = $options['currency'] ?? 'INR';

        if ($isGujarat) {
            $taxType = 'INTRA_STATE';
            $cgstRate = round($gstRate / 2, 2);
            $cgstAmount = round($gstAmount / 2, 2);
            $sgstRate = round($gstRate / 2, 2);
            $sgstAmount = round($gstAmount / 2, 2);
            $igstRate = 0.0;
            $igstAmount = 0.0;
            $taxDetailStr = "CGST ({$cgstRate}%): ₹" . number_format($cgstAmount, 2) . " + SGST ({$sgstRate}%): ₹" . number_format($sgstAmount, 2);
        } else {
            $taxType = 'INTER_STATE';
            $cgstRate = 0.0;
            $cgstAmount = 0.0;
            $sgstRate = 0.0;
            $sgstAmount = 0.0;
            $igstRate = $gstRate;
            $igstAmount = $gstAmount;
            $taxDetailStr = "IGST ({$igstRate}%): ₹" . number_format($igstAmount, 2);
        }

        $prefix = $type === 'upgrade' ? 'upg_' : ($type === 'renewal' ? 'ren_' : 'sub_');
        $purpose = $type === 'upgrade' ? 'plan_upgrade' : ($type === 'renewal' ? 'plan_renewal' : 'plan_subscription');
        $descTitle = $type === 'upgrade' ? 'Upgrade to' : ($type === 'renewal' ? 'Renewal for' : 'Subscription for');
        $receipt = $prefix . $package->id . '_' . $owner->id . '_' . time();

        $baseFmt = number_format($baseAmount, 2);
        $gstFmt = number_format($gstAmount, 2);
        $totalFmt = number_format($totalAmount, 2);
        $gstRateDisplay = (int) $gstRate == $gstRate ? (int) $gstRate : $gstRate;

        if ($gstRate > 0) {
            $linkDescription = "WhatsApp Bulk Messaging – {$planDisplayName} ({$periodLabel}) | Base: ₹{$baseFmt} + GST ({$gstRateDisplay}%): ₹{$gstFmt} (Total: ₹{$totalFmt})";
        } else {
            $linkDescription = "WhatsApp Bulk Messaging – {$planDisplayName} ({$periodLabel}) | Amount: ₹{$totalFmt}";
        }

        $backUrl = $options['back_url'] ?? ($options['backUrl'] ?? ($options['return_url'] ?? ($options['cancel_url'] ?? null)));

        $keys = $this->razorpayKeys();

        $razorpayNotes = [
            'userId' => (string) $owner->id,
            'purpose' => $purpose,
            'payment_type' => $type,
            'package_id' => (string) $package->id,
            'package_name' => (string) $planDisplayName,
            'is_custom_plan' => $isCustom ? 'true' : 'false',
            'custom_plan_name' => (string) $planDisplayName,
            'custom_price' => number_format($baseAmount, 2, '.', ''),
            'custom_msg_count' => (string) $resolvedMsgCount,
            'custom_contact_count' => (string) $resolvedContactCount,
            'custom_template_count' => (string) $resolvedTemplateCount,
            'duration_days' => (string) $days,
            'billing_period' => $periodLabel,
            'billing_state' => $isGujarat ? 'Gujarat' : ($customerState ?: 'Outside Gujarat'),
            'tax_type' => $taxType,
            'renewal_price' => number_format($baseAmount, 2, '.', ''),
            'base_amount' => '₹' . $baseFmt,
            'gst_percentage' => (string) $gstRate . '%',
            'gst_amount' => '₹' . $gstFmt,
            'cgst_percentage' => (string) $cgstRate . '%',
            'cgst_amount' => '₹' . number_format($cgstAmount, 2),
            'sgst_percentage' => (string) $sgstRate . '%',
            'sgst_amount' => '₹' . number_format($sgstAmount, 2),
            'igst_percentage' => (string) $igstRate . '%',
            'igst_amount' => '₹' . number_format($igstAmount, 2),
            'total_amount' => '₹' . $totalFmt,
            'back_url' => (string) ($backUrl ?: ''),
        ];

        $paymentLinkId = null;
        $paymentLinkUrl = null;
        $razorpayOrderId = null;

        $callbackUrl = $options['callback_url'] ?? url('/api/plan/payment-callback');

        // 4. If Razorpay configured, call Payment Link API and Order API
        if (!empty($keys['key']) && !empty($keys['secret'])) {
            try {
                $settings = Settings::whereNull('userId')->first() ?: Settings::first();
                $appName = (!empty($settings?->business_name) && trim($settings->business_name) !== '')
                    ? trim($settings->business_name)
                    : config('app.company_name', 'ZapReach Technologies');
                $linkResponse = Http::withBasicAuth($keys['key'], $keys['secret'])
                    ->acceptJson()
                    ->timeout(25)
                    ->post('https://api.razorpay.com/v1/payment_links', [
                        'amount' => $amountPaise,
                        'currency' => $currency,
                        'accept_partial' => false,
                        'description' => $linkDescription,
                        'customer' => [
                            'name' => $owner->name ?: 'Subscriber',
                            'email' => $owner->email ?: 'user@example.com',
                            'contact' => $owner->number ?: '',
                        ],
                        'notify' => [
                            'sms' => false,
                            'email' => false,
                        ],
                        'reminder_enable' => false,
                        'notes' => $razorpayNotes,
                        'callback_url' => $callbackUrl,
                        'callback_method' => 'get',
                        'options' => [
                            'checkout' => [
                                'name' => $appName,
                                'modal' => [
                                    'handleback' => true,
                                    'confirm_close' => true,
                                ],
                            ],
                        ],
                    ]);

                if ($linkResponse->successful()) {
                    $linkData = $linkResponse->json();
                    $paymentLinkId = $linkData['id'] ?? null;
                    $paymentLinkUrl = $linkData['short_url'] ?? ($linkData['url'] ?? null);
                    if (!empty($linkData['order_id'])) {
                        $razorpayOrderId = $linkData['order_id'];
                    }
                } else {
                    Log::warning('Razorpay payment link creation response: ' . $linkResponse->body());
                }
            } catch (\Throwable $e) {
                Log::warning('Razorpay payment link exception: ' . $e->getMessage());
            }

            if (!$razorpayOrderId) {
                try {
                    $orderResponse = Http::withBasicAuth($keys['key'], $keys['secret'])
                        ->acceptJson()
                        ->timeout(25)
                        ->post('https://api.razorpay.com/v1/orders', [
                            'amount' => $amountPaise,
                            'currency' => $currency,
                            'receipt' => $receipt,
                            'notes' => $razorpayNotes,
                        ]);

                    if ($orderResponse->successful()) {
                        $orderData = $orderResponse->json();
                        $razorpayOrderId = $orderData['id'] ?? null;
                    }
                } catch (\Throwable $e) {
                    Log::warning('Razorpay order creation exception: ' . $e->getMessage());
                }
            }
        }

        // Fallback friendly URL if external payment link is not available
        if (!$paymentLinkUrl) {
            $paymentLinkUrl = url('/payment/checkout/' . $receipt);
        }

        $order = WalletOrder::create([
            'userId' => $owner->id,
            'razorpay_order_id' => $razorpayOrderId,
            'payment_link_id' => $paymentLinkId,
            'payment_link_url' => $paymentLinkUrl,
            'payment_type' => $type,
            'receipt' => $receipt,
            'amount' => $baseAmount,
            'base_amount' => $baseAmount,
            'gst_percentage' => $gstRate,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'amount_paise' => $amountPaise,
            'package_id' => $package->id,
            'currency' => $currency,
            'status' => 'pending',
            'notes' => json_encode($razorpayNotes),
        ]);

        return [
            'success' => true,
            'type' => $type,
            'payment_link' => $paymentLinkUrl,
            'payment_url' => $paymentLinkUrl,
            'short_url' => $paymentLinkUrl,
            'payment_link_id' => $paymentLinkId,
            'order_id' => $razorpayOrderId,
            'local_order_id' => $order->id,
            'receipt' => $receipt,
            'package_id' => $package->id,
            'package_name' => $planDisplayName,
            'is_custom' => $isCustom,
            'is_custom_plan' => $isCustom,
            'package' => [
                'id' => $package->id,
                'packageName' => $planDisplayName,
                'packageDesc' => $package->packageDesc,
                'day' => (int) $days,
                'msgCount' => (int) $resolvedMsgCount,
                'templateCount' => (int) $resolvedTemplateCount,
                'contactCount' => (int) $resolvedContactCount,
                'price' => (string) $baseAmount,
                'renewal_price' => (string) $baseAmount,
                'effective_renewal_price' => $baseAmount,
                'is_custom' => $isCustom,
            ],
            'amount' => $totalAmount,
            'base_amount' => $baseAmount,
            'renewal_price' => $baseAmount,
            'gst_percentage' => $gstRate,
            'gst_amount' => $gstAmount,
            'total_amount' => $totalAmount,
            'amount_paise' => $amountPaise,
            'currency' => $currency,
            'period' => $periodLabel,
            'billing_period' => $periodLabel,
            'key' => $keys['key'],
            'description' => $linkDescription,
            'back_url' => $backUrl,
            'cancel_url' => url('/api/plan/payment-link/cancel?receipt=' . $receipt),
            'checkout_url' => url('/payment/checkout/' . $receipt),
            'tax_breakdown' => [
                'tax_type' => $taxType,
                'is_inter_state' => !$isGujarat,
                'seller_state' => $sellerState,
                'customer_state' => $isGujarat ? 'Gujarat' : ($customerState ?: 'Outside Gujarat'),
                'base_amount' => $baseAmount,
                'gst_percentage' => $gstRate,
                'gst_amount' => $gstAmount,
                'cgst_percentage' => $cgstRate,
                'cgst_amount' => $cgstAmount,
                'sgst_percentage' => $sgstRate,
                'sgst_amount' => $sgstAmount,
                'igst_percentage' => $igstRate,
                'igst_amount' => $igstAmount,
                'total_amount' => $totalAmount,
            ],
            'prefill' => [
                'name' => $owner->name,
                'email' => $owner->email,
                'contact' => $owner->number,
            ],
            'user' => [
                'id' => 'usr_' . $owner->id,
                'dbId' => $owner->id,
                'name' => $owner->name,
                'email' => $owner->email,
                'phone' => $owner->number,
            ],
            'notes' => $razorpayNotes,
        ];
    }

    /**
     * Directly purchase, upgrade, or renew a plan using user's available Wallet Balance.
     */
    public function purchasePlanFromWallet(User $user, Package $package, string $type = 'subscription'): array
    {
        $this->ensureSchema();
        $owner = $this->ownerUser($user);
        $activePkg = ActivePackage::where('userId', $owner->id)->latest('id')->first();

        $isCustom = ($type === 'renewal' && $activePkg && $activePkg->is_custom);
        if ($isCustom && $activePkg && $activePkg->custom_price !== null && (float) $activePkg->custom_price > 0) {
            $baseAmount = (float) $activePkg->custom_price;
        } elseif ($type === 'renewal') {
            $baseAmount = $package->getEffectiveRenewalPrice();
            if ($baseAmount <= 0) {
                $rawPrice = preg_replace('/[^\d.]/', '', (string) ($package->price ?? ''));
                $baseAmount = is_numeric($rawPrice) && (float) $rawPrice > 0 ? (float) $rawPrice : 0.0;
            }
        } else {
            $rawPrice = preg_replace('/[^\d.]/', '', (string) ($package->price ?? 0));
            $baseAmount = is_numeric($rawPrice) && (float) $rawPrice > 0 ? (float) $rawPrice : 0.0;
        }

        $latestHistory = \App\Models\UserPackageHistory::where('userId', $owner->id)->orderByDesc('id')->first();
        $customNameFromHist = ($activePkg && $activePkg->is_custom) ? $latestHistory?->packageName : null;
        $planDisplayName = ($isCustom && $customNameFromHist) ? $customNameFromHist : $package->packageName;

        if ($baseAmount <= 0) {
            throw new \InvalidArgumentException("Plan '{$planDisplayName}' does not have a valid price set.");
        }

        $gst = $this->gstConfig();
        $gstRate = $gst['enabled']
            ? ((isset($package->gst_percentage) && is_numeric($package->gst_percentage)) ? (float) $package->gst_percentage : $gst['percentage'])
            : 0.0;
        $gstAmount = $gstRate > 0 ? round(($baseAmount * $gstRate) / 100, 2) : 0.0;
        $totalAmount = round($baseAmount + $gstAmount, 2);

        $currentBalance = $this->getBalance($owner);
        if ($currentBalance < $totalAmount) {
            throw new \RuntimeException("Insufficient wallet balance. Required: ₹{$totalAmount}, Available: ₹{$currentBalance}. Please recharge wallet or choose Razorpay.");
        }

        return DB::transaction(function () use ($owner, $package, $baseAmount, $gstRate, $gstAmount, $totalAmount, $type, $isCustom, $planDisplayName, $activePkg) {
            $locked = User::where('id', $owner->id)->lockForUpdate()->first();
            $newBalance = round((float) $locked->wallet_balance - $totalAmount, 2);
            if ($newBalance < 0) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }
            $locked->wallet_balance = $newBalance;
            $locked->save();

            $prefix = $type === 'upgrade' ? 'upg_' : ($type === 'renewal' ? 'ren_' : 'sub_');
            $receipt = $prefix . $package->id . '_' . $locked->id . '_' . time();

            $invoiceService = app(InvoiceService::class);
            $customerState = $locked->companyState 
                ?? ($locked->state 
                ?? ($invoiceService->extractStateFromAddress($locked->companyAddress) ?: 'Gujarat'));
            $isGujarat = (!empty($customerState) && (stripos($customerState, 'gujarat') !== false || strcasecmp(trim($customerState), 'GJ') === 0));

            if ($isGujarat) {
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

            $year = date('Y');
            $maxId = WalletOrder::max('id') ?: 0;
            $invoiceNumber = 'INV-' . $year . '-' . str_pad((string) ($maxId + 1), 5, '0', STR_PAD_LEFT);

            $orderNotes = [
                'payment_method' => 'wallet',
                'type' => $type,
                'is_custom_plan' => $isCustom,
                'custom_plan_name' => $planDisplayName,
                'custom_price' => $baseAmount,
                'package_id' => $package->id,
                'package_name' => $planDisplayName,
                'billing_state' => $isGujarat ? 'Gujarat' : ($customerState ?: 'Outside Gujarat'),
                'tax_type' => $taxType,
                'cgst_percentage' => (string) $cgstRate . '%',
                'cgst_amount' => (string) $cgstAmount,
                'sgst_percentage' => (string) $sgstRate . '%',
                'sgst_amount' => (string) $sgstAmount,
                'igst_percentage' => (string) $igstRate . '%',
                'igst_amount' => (string) $igstAmount,
            ];

            if ($isCustom && $activePkg) {
                $orderNotes['custom_msg_count'] = $activePkg->custom_msg_count;
                $orderNotes['custom_contact_count'] = $activePkg->custom_contact_count;
                $orderNotes['custom_template_count'] = $activePkg->custom_template_count;
                $orderNotes['duration_days'] = $activePkg->day;
            }

            $orderData = [
                'userId' => $locked->id,
                'payment_type' => $type,
                'receipt' => $receipt,
                'amount' => $baseAmount,
                'base_amount' => $baseAmount,
                'gst_percentage' => $gstRate,
                'gst_amount' => $gstAmount,
                'total_amount' => $totalAmount,
                'amount_paise' => (int) round($totalAmount * 100),
                'package_id' => $package->id,
                'currency' => 'INR',
                'status' => 'paid',
                'notes' => json_encode($orderNotes),
            ];

            if (Schema::hasColumn('wallet_orders', 'payment_method')) {
                $orderData['payment_method'] = 'wallet';
            }
            if (Schema::hasColumn('wallet_orders', 'invoice_number')) {
                $orderData['invoice_number'] = $invoiceNumber;
            }

            $order = WalletOrder::create($orderData);

            // Activate / upgrade / renew plan
            $activePkg = $this->activatePlanRenewal($locked, $package, $order);

            $typeLabel = $type === 'upgrade' ? 'Plan upgrade' : ($type === 'subscription' ? 'Plan subscription' : 'Plan renewal');
            $desc = "{$typeLabel} via wallet (₹{$totalAmount} incl. {$gstRate}% GST)";

            WalletTransaction::create([
                'userId' => $locked->id,
                'type' => 'debit',
                'source' => 'usage',
                'category' => 'PLAN',
                'amount' => $totalAmount,
                'balance_after' => $newBalance,
                'reference_id' => $receipt,
                'wallet_order_id' => $order->id,
                'description' => $desc,
            ]);

            return [
                'success' => true,
                'payment_method' => 'wallet',
                'type' => $type,
                'is_custom' => $isCustom,
                'is_custom_plan' => $isCustom,
                'package_name' => $planDisplayName,
                'amount_debited' => $totalAmount,
                'new_wallet_balance' => $newBalance,
                'invoice_number' => $invoiceNumber,
                'receipt' => $receipt,
                'package' => $package,
                'active_package' => $activePkg->load('package'),
            ];
        });
    }

    /**
     * Activate / Extend Plan Renewal upon payment completion.
     */
    public function activatePlanRenewal(User $user, Package $package, ?WalletOrder $order = null, $assignedBy = null): ActivePackage
    {
        $this->ensureSchema();
        $owner = $this->ownerUser($user);

        return DB::transaction(function () use ($owner, $package, $order, $assignedBy) {
            $activePkg = ActivePackage::where('userId', $owner->id)->latest('id')->first();

            $orderNotes = [];
            if ($order && !empty($order->notes)) {
                $orderNotes = is_string($order->notes)
                    ? (json_decode($order->notes, true) ?: [])
                    : (is_array($order->notes) ? $order->notes : []);
            }

            $orderType = $order?->payment_type ?? 'renewal';
            $isExplicitCustom = filter_var($orderNotes['is_custom_plan'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || filter_var($orderNotes['custom_plan'] ?? false, FILTER_VALIDATE_BOOLEAN)
                || ($orderType === 'custom_plan');
            $isCustomPlan = $isExplicitCustom || ($orderType === 'renewal' && $activePkg && $activePkg->is_custom);

            $isYearly = (bool) $package->is_yearly || (int) ($package->day ?? 0) >= 300;

            if ($isCustomPlan) {
                $orderDays = (int) ($orderNotes['duration_days'] ?? ($orderNotes['days'] ?? ($orderNotes['day'] ?? 0)));
                $days = $orderDays > 0 ? $orderDays : ($activePkg && (int) $activePkg->day > 0 ? (int) $activePkg->day : (int) ($package->day ?: ($isYearly ? 365 : 30)));
            } else {
                $days = (int) ($package->day ?: ($isYearly ? 365 : 30));
                if ($isYearly && $days < 300) {
                    $days = 365;
                }
            }

            $isUpgrade = ($orderType === 'upgrade') || ($activePkg && (int) $activePkg->packageId !== (int) $package->id && $orderType !== 'renewal' && !$isCustomPlan);
            $isUpgradeOrNew = in_array($orderType, ['upgrade', 'subscription']) || (!$activePkg) || ((int) $activePkg->packageId !== (int) $package->id && !$isCustomPlan);

            // Compute remaining days from current active package if still in the future
            $remainingDays = 0;
            if ($activePkg && $activePkg->endDate && Carbon::parse($activePkg->endDate)->isFuture()) {
                $remainingDays = (int) max(0, (int) ceil(now()->floatDiffInDays(Carbon::parse($activePkg->endDate), false)));
            }

            if ($isUpgrade) {
                // When upgrading: start fresh from now() and ADD ON the remaining days from old plan
                $totalDuration = $days + $remainingDays;
                $startDate = now();
                $newEndDate = now()->addDays($totalDuration);
            } elseif (!$isUpgradeOrNew && $activePkg && $activePkg->endDate && Carbon::parse($activePkg->endDate)->isFuture()) {
                // When renewing the same plan and current endDate is in future: extend from current endDate
                $startDate = $activePkg->startDate ? Carbon::parse($activePkg->startDate) : now();
                $newEndDate = Carbon::parse($activePkg->endDate)->copy()->addDays($days);
                $totalDuration = (int) max(1, Carbon::parse($startDate)->diffInDays($newEndDate, false));
            } else {
                $startDate = now();
                $newEndDate = now()->addDays($days);
                $totalDuration = $days;
            }

            $rolloverMessages = 0;
            $rolloverContacts = 0;
            $rolloverTemplates = 0;

            if ($isCustomPlan) {
                // Custom plan quotas & pricing resolution (preserve custom quotas on renewal)
                $baseMsgCount = isset($orderNotes['custom_msg_count'])
                    ? (int) $orderNotes['custom_msg_count']
                    : ($activePkg?->custom_msg_count !== null ? (int) $activePkg->custom_msg_count : (int) ($package->msgCount ?? 0));

                $baseContactCount = isset($orderNotes['custom_contact_count'])
                    ? (int) $orderNotes['custom_contact_count']
                    : ($activePkg?->custom_contact_count !== null ? (int) $activePkg->custom_contact_count : (int) ($package->contactCount ?? 1000));

                $baseTemplateCount = isset($orderNotes['custom_template_count'])
                    ? (int) $orderNotes['custom_template_count']
                    : ($activePkg?->custom_template_count !== null ? (int) $activePkg->custom_template_count : (int) ($package->templateCount ?? 50));

                $customPrice = isset($orderNotes['custom_price'])
                    ? (float) $orderNotes['custom_price']
                    : ($activePkg?->custom_price !== null ? (float) $activePkg->custom_price : (float) ($order?->base_amount ?? ($order?->amount ?? $package->price)));

                if ($activePkg) {
                    $oldLimit = $activePkg->resolvedMessageLimit();
                    $oldUsed = $activePkg->is_yearly
                        ? (int) ($activePkg->monthlyUsedMsgCount ?? 0)
                        : (int) ($activePkg->usedMsgCount ?? 0);
                    $remainingMsgs = max(0, $oldLimit - $oldUsed);
                    if ($remainingMsgs > 0) {
                        $rolloverMessages = $remainingMsgs;
                    }
                }

                $totalMsgCount = $baseMsgCount + $rolloverMessages;
                $totalContactCount = $baseContactCount;
                $totalTemplateCount = $baseTemplateCount;
            } else {
                // Standard package quotas (with rollover logic)
                if ($activePkg) {
                    // 1. Messages: remaining unused messages roll over on both upgrade and renewal
                    $oldLimit = $activePkg->resolvedMessageLimit();
                    $oldUsed = $activePkg->is_yearly
                        ? (int) ($activePkg->monthlyUsedMsgCount ?? 0)
                        : (int) ($activePkg->usedMsgCount ?? 0);
                    $remainingMsgs = max(0, $oldLimit - $oldUsed);
                    if ($remainingMsgs > 0) {
                        $rolloverMessages = $remainingMsgs;
                    }

                    // 2. Contacts: remaining unused contacts + extra add-ons
                    $oldContactLimit = $activePkg->resolvedContactLimit();
                    $usedContacts = max(
                        (int) ($activePkg->monthlyUsedContactCount ?? 0),
                        \App\Models\Contact::where('userId', $owner->id)->count()
                    );
                    $remainingContacts = max(0, $oldContactLimit - $usedContacts);
                    $rolloverContacts = max($remainingContacts, $activePkg->addonContactCount());

                    // 3. Templates: remaining unused custom templates + extra add-ons
                    $oldTemplateLimit = $activePkg->resolvedTemplateLimit();
                    $usedTemplates = max(
                        (int) ($activePkg->monthlyUsedTemplateCount ?? 0),
                        \App\Models\Template::where('userId', $owner->id)->where('type', 'custom')->count()
                    );
                    $remainingTemplates = max(0, $oldTemplateLimit - $usedTemplates);
                    $baseOldTemplateLimit = (int) ($activePkg->package?->templateCount ?? 0);
                    $addonTemplates = max(0, $oldTemplateLimit - $baseOldTemplateLimit);
                    $rolloverTemplates = max($remainingTemplates, $addonTemplates);
                }

                $baseMsgCount = (int) ($package->msgCount ?? 0);
                $totalMsgCount = $baseMsgCount + $rolloverMessages;

                $baseContactCount = (int) ($package->contactCount ?? 1000);
                $totalContactCount = $baseContactCount + $rolloverContacts;

                $baseTemplateCount = (int) ($package->templateCount ?? 50);
                $totalTemplateCount = $baseTemplateCount + $rolloverTemplates;
                $customPrice = null;
            }

            if ($isCustomPlan) {
                $pkgData = [
                    'packageId' => $package->id,
                    'startDate' => $startDate,
                    'endDate' => $newEndDate,
                    'day' => $totalDuration,
                    'status' => ActivePackage::STATUS_ACTIVE,
                    'usedMsgCount' => 0,
                    'custom_msg_count' => $totalMsgCount,
                    'custom_contact_count' => $totalContactCount,
                    'custom_template_count' => $totalTemplateCount,
                    'custom_price' => $customPrice,
                ];
            } else {
                $pkgData = [
                    'packageId' => $package->id,
                    'startDate' => $startDate,
                    'endDate' => $newEndDate,
                    'day' => $totalDuration,
                    'status' => ActivePackage::STATUS_ACTIVE,
                    'usedMsgCount' => 0,
                    'custom_msg_count' => $rolloverMessages > 0 ? $totalMsgCount : null,
                    'custom_contact_count' => $rolloverContacts > 0 ? $totalContactCount : null,
                    'custom_template_count' => $rolloverTemplates > 0 ? $totalTemplateCount : null,
                    'custom_price' => null,
                ];
            }

            if (Schema::hasColumn('active_packages', 'lastMonthlyReset')) {
                $pkgData['lastMonthlyReset'] = now();
            }
            if (Schema::hasColumn('active_packages', 'monthlyUsedMsgCount')) {
                $pkgData['monthlyUsedMsgCount'] = 0;
            }
            if (Schema::hasColumn('active_packages', 'monthlyUsedTemplateCount')) {
                $pkgData['monthlyUsedTemplateCount'] = 0;
            }
            if (Schema::hasColumn('active_packages', 'monthlyUsedContactCount')) {
                $pkgData['monthlyUsedContactCount'] = 0;
            }

            if ($activePkg) {
                $activePkg->update($pkgData);
            } else {
                $pkgData['userId'] = $owner->id;
                $activePkg = ActivePackage::create($pkgData);
            }

            // Set user to active
            $owner->update(['status' => 'active']);
            if ($owner->isAdmin() || strtolower($owner->role) === 'admin') {
                User::where('parent_id', $owner->id)->update(['status' => 'active']);
            }

            // Log package history
            $customNameFromHist = null;
            if ($isCustomPlan) {
                $latestHist = UserPackageHistory::where('userId', $owner->id)->orderByDesc('id')->first();
                $customNameFromHist = $latestHist?->packageName;
            }
            $historyPlanName = $orderNotes['custom_plan_name'] 
                ?? ($orderNotes['package_name'] 
                ?? ($customNameFromHist ?: $package->packageName));

            $priceUsed = $isCustomPlan
                ? ($customPrice ?: (float) ($order?->base_amount ?? ($order?->amount ?? 0)))
                : (($orderType === 'renewal')
                    ? ($package->getEffectiveRenewalPrice() ?: (float) preg_replace('/[^\d.]/', '', (string) $package->price))
                    : (float) preg_replace('/[^\d.]/', '', (string) $package->price));

            $action = match ($orderType) {
                'upgrade' => 'upgraded',
                'subscription' => 'subscribed',
                default => 'renewed',
            };

            $rolloverNotes = [];
            if ($rolloverMessages > 0) {
                $rolloverNotes[] = "+{$rolloverMessages} unused messages";
            }
            if ($rolloverContacts > 0) {
                $rolloverNotes[] = "+{$rolloverContacts} contacts";
            }
            if ($rolloverTemplates > 0) {
                $rolloverNotes[] = "+{$rolloverTemplates} templates";
            }
            if ($remainingDays > 0 && $isUpgrade) {
                $rolloverNotes[] = "+{$remainingDays} remaining days";
            }

            $historyNotes = "Plan {$action} with price ₹{$priceUsed}" . ($order ? " (Order #{$order->id})" : '');
            if (!empty($rolloverNotes)) {
                $historyNotes .= " (Rollover: " . implode(', ', $rolloverNotes) . " carried forward from previous plan)";
            }

            UserPackageHistory::recordOnce([
                'userId' => $owner->id,
                'packageId' => $package->id,
                'packageName' => $historyPlanName,
                'price' => (float) $priceUsed,
                'day' => $totalDuration,
                'msgCount' => $totalMsgCount,
                'templateCount' => $totalTemplateCount,
                'contactCount' => $totalContactCount,
                'startDate' => $startDate,
                'endDate' => $newEndDate,
                'action' => $action,
                'assignedBy' => $assignedBy ?: $owner->id,
                'notes' => $historyNotes,
            ]);

            // Dispatch plan notification email to user and CC admin (if enabled)
            try {
                app(\App\Services\PlanMailService::class)->sendPlanNotification($owner, $package, [
                    'action' => $action,
                    'activePkg' => $activePkg,
                    'order' => $order,
                    'price' => (float) $priceUsed,
                    'days' => $totalDuration,
                    'startDate' => $startDate,
                    'endDate' => $newEndDate,
                    'msgCount' => $totalMsgCount,
                    'contactCount' => $totalContactCount,
                    'templateCount' => $totalTemplateCount,
                    'payment_method' => $order?->payment_method ?: 'Razorpay',
                    'receipt' => $order?->receipt ?: ($order?->razorpay_order_id ?: null),
                ]);
            } catch (\Throwable $mailEx) {
                Log::warning("Failed to dispatch plan notification email: " . $mailEx->getMessage());
            }

            return $activePkg;
        });
    }

    /**
     * Verify payment for a payment link (from callback or verify endpoint) and activate renewal.
     */
    public function verifyPaymentLinkPayment(string $paymentIdentifier, string $paymentId, ?string $signature = null, ?User $user = null): array
    {
        $this->ensureSchema();
        $keys = $this->razorpayKeys();

        // Search order by payment_link_id, razorpay_order_id, receipt, or local ID
        $orderQuery = WalletOrder::query();
        if ($user) {
            $owner = $this->ownerUser($user);
            $orderQuery->where('userId', $owner->id);
        }

        $order = (clone $orderQuery)->where('payment_link_id', $paymentIdentifier)
            ->orWhere('razorpay_order_id', $paymentIdentifier)
            ->orWhere('receipt', $paymentIdentifier)
            ->first();

        if (!$order && is_numeric($paymentIdentifier)) {
            $order = (clone $orderQuery)->where('id', (int) $paymentIdentifier)->first();
        }

        if (!$order) {
            throw new \RuntimeException('Order not found for payment link.');
        }

        $targetUser = User::find($order->userId);
        if (!$targetUser) {
            throw new \RuntimeException('User associated with order not found.');
        }

        if ($order->status === 'paid') {
            return [
                'success' => true,
                'already_credited' => true,
                'package_activated' => true,
                'amount' => (float) $order->amount,
                'total_amount' => (float) ($order->total_amount ?? $order->amount),
                'payment_id' => $order->razorpay_payment_id,
            ];
        }

        // Verify signature if provided and secret configured
        if ($signature && !empty($keys['secret'])) {
            $valid = false;
            // Case 1: Standard Razorpay order signature: order_id|payment_id
            if (!empty($order->razorpay_order_id)) {
                $expected = hash_hmac('sha256', $order->razorpay_order_id . '|' . $paymentId, $keys['secret']);
                if (hash_equals($expected, $signature)) {
                    $valid = true;
                }
            }
            // Case 2: Payment link signature: payment_link_id|payment_link_reference_id|payment_link_status|payment_id
            if (!$valid && !empty($order->payment_link_id)) {
                $refId = $order->receipt ?: '';
                $expectedPlink = hash_hmac('sha256', $order->payment_link_id . '|' . $refId . '|paid|' . $paymentId, $keys['secret']);
                if (hash_equals($expectedPlink, $signature)) {
                    $valid = true;
                }
            }

            if (!$valid) {
                // Check if Razorpay API confirms the payment is captured
                try {
                    $verifyResp = Http::withBasicAuth($keys['key'], $keys['secret'])
                        ->timeout(10)
                        ->get("https://api.razorpay.com/v1/payments/{$paymentId}");
                    if ($verifyResp->successful() && in_array($verifyResp->json('status'), ['captured', 'authorized'])) {
                        $valid = true;
                    }
                } catch (\Throwable $e) {}
            }

            if (!$valid) {
                throw new \RuntimeException('Payment signature verification failed.');
            }
        }

        return DB::transaction(function () use ($targetUser, $order, $paymentId, $signature) {
            $freshOrder = WalletOrder::where('id', $order->id)->lockForUpdate()->first();
            if ($freshOrder->status === 'paid') {
                return [
                    'success' => true,
                    'already_credited' => true,
                    'package_activated' => true,
                    'amount' => (float) $freshOrder->amount,
                    'payment_id' => $freshOrder->razorpay_payment_id,
                ];
            }

            $orderUpdateData = [
                'status' => 'paid',
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ];

            if (empty($freshOrder->invoice_number) && Schema::hasColumn('wallet_orders', 'invoice_number')) {
                $year = date('Y');
                $maxId = WalletOrder::max('id') ?: 0;
                $orderUpdateData['invoice_number'] = 'INV-' . $year . '-' . str_pad((string) ($freshOrder->id ?: ($maxId + 1)), 5, '0', STR_PAD_LEFT);
            }

            $freshOrder->update($orderUpdateData);

            $packageActivated = false;
            $package = null;
            if (!empty($freshOrder->package_id)) {
                $package = Package::find($freshOrder->package_id);
            }
            if (!$package) {
                $activePkg = ActivePackage::where('userId', $targetUser->id)->latest('id')->first();
                $package = $activePkg?->package;
            }

            if ($package) {
                $this->activatePlanRenewal($targetUser, $package, $freshOrder);
                $packageActivated = true;
            }

            // Create transaction log
            $orderType = $freshOrder->payment_type ?? 'renewal';
            $typeLabel = $orderType === 'upgrade' ? 'Plan upgrade' : ($orderType === 'subscription' ? 'Plan subscription' : 'Plan renewal');
            $txDesc = "{$typeLabel} via payment link (Paid ₹{$freshOrder->total_amount} incl. {$freshOrder->gst_percentage}% GST)";
            WalletTransaction::create([
                'userId' => $targetUser->id,
                'type' => 'credit',
                'source' => 'razorpay',
                'amount' => $freshOrder->amount,
                'balance_after' => $this->getBalance($targetUser),
                'reference_id' => $paymentId,
                'wallet_order_id' => $freshOrder->id,
                'description' => $txDesc,
            ]);

            return [
                'success' => true,
                'already_credited' => false,
                'package_activated' => $packageActivated,
                'amount' => (float) $freshOrder->amount,
                'total_amount' => (float) ($freshOrder->total_amount ?? $freshOrder->amount),
                'payment_id' => $paymentId,
                'package_name' => $package?->packageName,
            ];
        });
    }

    public function verifyAndCredit(User $user, string $razorpayOrderId, string $paymentId, string $signature): array
    {
        $this->ensureSchema();
        $owner = $this->ownerUser($user);
        $keys = $this->razorpayKeys();

        if ($keys['secret'] === '') {
            throw new \RuntimeException('Razorpay is not configured.');
        }

        $expected = hash_hmac('sha256', $razorpayOrderId . '|' . $paymentId, $keys['secret']);
        if (!hash_equals($expected, $signature)) {
            throw new \RuntimeException('Payment signature verification failed.');
        }

        $order = WalletOrder::where('razorpay_order_id', $razorpayOrderId)
            ->where('userId', $owner->id)
            ->first();

        if (!$order) {
            throw new \RuntimeException('Wallet order not found for this user.');
        }

        if ($order->status === 'paid') {
            return [
                'already_credited' => true,
                'amount' => (float) $order->amount,
                'balance' => $this->getBalance($owner),
                'payment_id' => $order->razorpay_payment_id,
            ];
        }

        return DB::transaction(function () use ($owner, $order, $paymentId, $signature) {
            $locked = User::where('id', $owner->id)->lockForUpdate()->first();
            $freshOrder = WalletOrder::where('id', $order->id)->lockForUpdate()->first();

            if ($freshOrder->status === 'paid') {
                return [
                    'already_credited' => true,
                    'amount' => (float) $freshOrder->amount,
                    'balance' => (float) $locked->wallet_balance,
                    'payment_id' => $freshOrder->razorpay_payment_id,
                ];
            }

            if (WalletTransaction::where('reference_id', $paymentId)->exists()) {
                $freshOrder->update([
                    'status' => 'paid',
                    'razorpay_payment_id' => $paymentId,
                    'razorpay_signature' => $signature,
                ]);
                return [
                    'already_credited' => true,
                    'amount' => (float) $freshOrder->amount,
                    'balance' => (float) $locked->wallet_balance,
                    'payment_id' => $paymentId,
                ];
            }

            $newBalance = round((float) $locked->wallet_balance + (float) $freshOrder->amount, 2);
            $locked->wallet_balance = $newBalance;
            $locked->save();

            $freshOrder->update([
                'status' => 'paid',
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);

            $txDesc = ($freshOrder->gst_amount && (float) $freshOrder->gst_amount > 0)
                ? "Wallet recharge via Razorpay (Paid ₹{$freshOrder->total_amount} incl. {$freshOrder->gst_percentage}% GST)"
                : 'Wallet recharge via Razorpay';

            WalletTransaction::create([
                'userId' => $locked->id,
                'type' => 'credit',
                'source' => 'razorpay',
                'amount' => $freshOrder->amount,
                'balance_after' => $newBalance,
                'reference_id' => $paymentId,
                'wallet_order_id' => $freshOrder->id,
                'description' => $txDesc,
            ]);

            // If this order was for a package / subscription plan, activate it now
            $packageActivated = false;
            if (!empty($freshOrder->package_id)) {
                $pkg = Package::find($freshOrder->package_id);
                if ($pkg) {
                    $this->activatePlanRenewal($locked, $pkg, $freshOrder);
                    $packageActivated = true;
                }
            }

            return [
                'already_credited' => false,
                'amount' => (float) $freshOrder->amount,
                'base_amount' => (float) ($freshOrder->base_amount ?? $freshOrder->amount),
                'gst_percentage' => (float) ($freshOrder->gst_percentage ?? 0),
                'gst_amount' => (float) ($freshOrder->gst_amount ?? 0),
                'total_amount' => (float) ($freshOrder->total_amount ?? $freshOrder->amount),
                'balance' => $newBalance,
                'payment_id' => $paymentId,
                'package_activated' => $packageActivated,
            ];
        });
    }

    public function adminCredit(User $user, float $amount, string $description = 'Admin wallet credit'): array
    {
        $this->ensureSchema();
        $amount = round($amount, 2);
        if ($amount == 0) {
            throw new \InvalidArgumentException('Amount cannot be zero.');
        }

        return DB::transaction(function () use ($user, $amount, $description) {
            $locked = User::where('id', $user->id)->lockForUpdate()->first();
            $newBalance = round((float) $locked->wallet_balance + $amount, 2);
            if ($newBalance < 0) {
                throw new \RuntimeException('Insufficient wallet balance.');
            }
            $locked->wallet_balance = $newBalance;
            $locked->save();

            $txn = WalletTransaction::create([
                'userId' => $locked->id,
                'type' => $amount >= 0 ? 'credit' : 'debit',
                'source' => 'admin',
                'amount' => abs($amount),
                'balance_after' => $newBalance,
                'description' => $description,
            ]);

            return [
                'transaction' => $txn,
                'balance' => $newBalance,
            ];
        });
    }

    public function summary(User $user): array
    {
        $this->ensureSchema();
        $owner = $this->ownerUser($user);
        $keys = $this->razorpayKeys();
        $pricing = $this->pricingConfig();

        return [
            'walletEnabled' => (bool) ($owner->wallet_enabled ?? false),
            'balance' => round((float) ($owner->wallet_balance ?? 0), 2),
            'currency' => 'INR',
            'razorpayKeyId' => $keys['key'],
            'userId' => $owner->id,
            'minBalance' => $pricing['min_balance'],
            'prices' => [
                'marketing' => $pricing['marketing'],
                'utility' => $pricing['utility'],
                'authentication' => $pricing['authentication'],
            ],
            'gst' => [
                'enabled' => $pricing['gst_enabled'],
                'percentage' => $pricing['gst_percentage'],
                'label' => $pricing['gst_label'],
            ],
            'canSendBulk' => !$this->isEnabled($owner) || (float) ($owner->wallet_balance ?? 0) >= $pricing['min_balance'],
        ];
    }

    /**
     * Get global GST tax configuration.
     */
    public function gstConfig(): array
    {
        $this->ensureSchema();
        $settings = Settings::whereNull('userId')->first() ?: Settings::first();

        $percentage = 18.0;
        if ($settings && isset($settings->gst_percentage) && is_numeric($settings->gst_percentage)) {
            $percentage = (float) $settings->gst_percentage;
        }

        $enabled = true;
        if ($settings && isset($settings->gst_enabled)) {
            $enabled = (bool) $settings->gst_enabled;
        }

        return [
            'enabled' => $enabled,
            'percentage' => round($percentage, 2),
            'label' => $enabled ? "GST (" . round($percentage, 2) . "%)" : 'Disabled (0%)',
            'gstin' => $settings->gstin ?? '',
            'state' => $settings->gst_state ?? '',
        ];
    }

    /**
     * Global wallet billing settings (editable by Super Admin).
     */
    public function pricingConfig(): array
    {
        $this->ensureSchema();
        $settings = Settings::whereNull('userId')->first() ?: Settings::first();
        $gst = $this->gstConfig();

        return [
            'min_balance' => round((float) ($settings->wallet_min_balance ?? config('services.wallet.min_balance', 250)), 2),
            'marketing' => round((float) ($settings->wallet_price_marketing ?? config('services.wallet.price_marketing', 0.86)), 4),
            'utility' => round((float) ($settings->wallet_price_utility ?? config('services.wallet.price_utility', 0.17)), 4),
            'authentication' => round((float) ($settings->wallet_price_authentication ?? config('services.wallet.price_authentication', 0.17)), 4),
            'gst_enabled' => $gst['enabled'],
            'gst_percentage' => $gst['percentage'],
            'gst_label' => $gst['label'],
        ];
    }

    public function priceForCategory(?string $category): float
    {
        $pricing = $this->pricingConfig();
        $cat = strtoupper(trim((string) $category));

        // Normalize common Meta / UI variants
        if (str_contains($cat, 'MARKET')) {
            $cat = 'MARKETING';
        } elseif (str_contains($cat, 'AUTH')) {
            $cat = 'AUTHENTICATION';
        } elseif (str_contains($cat, 'UTIL')) {
            $cat = 'UTILITY';
        }

        return match ($cat) {
            'MARKETING' => $pricing['marketing'],
            'AUTHENTICATION' => $pricing['authentication'],
            default => $pricing['utility'],
        };
    }

    /**
     * Resolve billing category from campaign template (safe if template missing).
     */
    public function resolveCategoryFromDelivery(MessageDeliveryStatus $delivery): string
    {
        $delivery->loadMissing(['bulkMessage.template']);

        // Prefer category saved at send time
        $meta = is_array($delivery->metadata) ? $delivery->metadata : [];
        $category = $meta['billing_category'] ?? null;

        $template = $delivery->bulkMessage?->template;
        if (!$category) {
            $category = $template?->category;
        }

        if (!$category && is_array($template?->components)) {
            $category = $template->components['category'] ?? null;
        }

        $cat = strtoupper(trim((string) ($category ?: 'UTILITY')));
        if (str_contains($cat, 'MARKET')) {
            return 'MARKETING';
        }
        if (str_contains($cat, 'AUTH')) {
            return 'AUTHENTICATION';
        }
        if (str_contains($cat, 'UTIL')) {
            return 'UTILITY';
        }

        return in_array($cat, ['MARKETING', 'UTILITY', 'AUTHENTICATION'], true) ? $cat : 'UTILITY';
    }

    /**
     * Wallet-enabled users must have at least min balance before starting bulk send.
     */
    public function assertCanStartBulk(User $user): void
    {
        $this->ensureSchema();
        if (!$this->isEnabled($user)) {
            return;
        }

        $owner = $this->ownerUser($user);
        $min = $this->pricingConfig()['min_balance'];
        $balance = (float) ($owner->wallet_balance ?? 0);

        if ($balance < $min) {
            throw new \RuntimeException(
                "Wallet balance must be at least ₹{$min} to send bulk messages. Current balance: ₹" . number_format($balance, 2)
            );
        }
    }

    /**
     * Whether the user can send one more message for this campaign (reserves uncharged sent/processing).
     */
    public function canAffordNextSend(User $user, float $price, ?int $campaignId = null): bool
    {
        $this->ensureSchema();
        if (!$this->isEnabled($user)) {
            return true;
        }

        $owner = $this->ownerUser($user);
        $balance = (float) ($owner->wallet_balance ?? 0);
        $price = round($price, 4);

        if ($price <= 0) {
            return true;
        }

        $outstanding = 0;
        if ($campaignId && Schema::hasColumn('message_delivery_statuses', 'wallet_charged')) {
            $outstanding = MessageDeliveryStatus::where('bulk_message_id', $campaignId)
                ->whereIn('status', ['sent', 'processing'])
                ->where(function ($q) {
                    $q->where('wallet_charged', false)->orWhereNull('wallet_charged');
                })
                ->count();
        }

        $available = $balance - ($outstanding * $price);
        return $available >= $price;
    }

    /**
     * Mark all remaining pending/processing rows failed when wallet runs out mid-campaign.
     */
    public function failRemainingForInsufficientWallet(int $campaignId): int
    {
        $this->ensureSchema();
        $query = MessageDeliveryStatus::where('bulk_message_id', $campaignId)
            ->whereIn('status', ['pending', 'processing']);

        $count = $query->count();
        if ($count === 0) {
            return 0;
        }

        MessageDeliveryStatus::where('bulk_message_id', $campaignId)
            ->whereIn('status', ['pending', 'processing'])
            ->update([
                'status' => 'failed',
                'error_message' => 'Insufficient wallet balance. Campaign stopped when wallet funds were exhausted.',
                'failed_at' => now(),
                'updated_at' => now(),
            ]);

        return $count;
    }

    /**
     * Deduct wallet when a bulk message is successfully accepted by WhatsApp (sent)
     * or later confirmed delivered/read. Idempotent per delivery row.
     */
    public function chargeForDelivery(MessageDeliveryStatus $delivery, ?string $forceStatus = null): ?array
    {
        $this->ensureSchema();

        $delivery->refresh();
        $delivery->loadMissing(['bulkMessage.template', 'bulkMessage', 'contact']);

        $campaign = $delivery->bulkMessage;
        if (!$campaign) {
            \Log::warning('Wallet charge skipped: no campaign', ['delivery_id' => $delivery->id]);
            return null;
        }

        $user = User::find($campaign->userId);
        if (!$user) {
            \Log::warning('Wallet charge skipped: user not found', [
                'delivery_id' => $delivery->id,
                'userId' => $campaign->userId,
            ]);
            return null;
        }

        // Always re-read wallet_enabled from DB (avoid stale model)
        $owner = User::find($user->getCompanyId()) ?: $user;
        if (!(bool) ($owner->wallet_enabled ?? false)) {
            return null;
        }

        $status = strtolower((string) ($forceStatus ?: $delivery->status));
        // Bill on successful send (API accepted), and also on delivered/read if somehow uncharged
        if (!in_array($status, ['sent', 'delivered', 'read'], true)) {
            \Log::info('Wallet charge skipped: status not billable', [
                'delivery_id' => $delivery->id,
                'status' => $status,
            ]);
            return null;
        }

        if (Schema::hasColumn('message_delivery_statuses', 'wallet_charged') && $delivery->wallet_charged) {
            return [
                'already_charged' => true,
                'amount' => (float) ($delivery->wallet_amount ?? 0),
                'balance' => $this->getBalance($user),
            ];
        }

        $category = $this->resolveCategoryFromDelivery($delivery);
        $price = $this->priceForCategory($category);
        if ($price <= 0) {
            \Log::warning('Wallet charge skipped: price is 0', [
                'delivery_id' => $delivery->id,
                'category' => $category,
            ]);
            return null;
        }

        $ref = 'delivery_' . $delivery->id;

        return DB::transaction(function () use ($user, $delivery, $price, $ref, $category, $campaign, $owner, $status) {
            $debits = WalletTransaction::where('reference_id', $ref)->where('type', 'debit')->count();
            $refunds = WalletTransaction::where('reference_id', 'refund_' . $ref)->where('type', 'credit')->count();

            if ($debits > $refunds) {
                if (Schema::hasColumn('message_delivery_statuses', 'wallet_charged')) {
                    $delivery->wallet_charged = true;
                    $delivery->wallet_amount = $price;
                    $delivery->save();
                }
                return [
                    'already_charged' => true,
                    'amount' => $price,
                    'balance' => $this->getBalance($user),
                ];
            }

            $locked = User::where('id', $owner->id)->lockForUpdate()->first();
            $freshDelivery = MessageDeliveryStatus::where('id', $delivery->id)->lockForUpdate()->first();

            if ($freshDelivery && Schema::hasColumn('message_delivery_statuses', 'wallet_charged') && $freshDelivery->wallet_charged) {
                return [
                    'already_charged' => true,
                    'amount' => (float) ($freshDelivery->wallet_amount ?? $price),
                    'balance' => (float) $locked->wallet_balance,
                ];
            }

            if ((float) $locked->wallet_balance < $price) {
                \Log::warning('Wallet insufficient on send/delivery charge', [
                    'userId' => $locked->id,
                    'delivery_id' => $delivery->id,
                    'balance' => $locked->wallet_balance,
                    'price' => $price,
                    'category' => $category,
                    'campaign_id' => $campaign->id,
                    'status' => $status,
                ]);
                return [
                    'charged' => false,
                    'insufficient' => true,
                    'amount' => $price,
                    'balance' => (float) $locked->wallet_balance,
                    'category' => $category,
                ];
            }

            $newBalance = round((float) $locked->wallet_balance - $price, 2);
            $locked->wallet_balance = $newBalance;
            $locked->save();

            $txnData = [
                'userId' => $locked->id,
                'type' => 'debit',
                'source' => 'usage',
                'amount' => $price,
                'balance_after' => $newBalance,
                'reference_id' => $ref,
                'description' => sprintf(
                    'Bulk %s ₹%s · campaign #%d · delivery #%d (%s)',
                    $category,
                    number_format($price, 2),
                    $campaign->id,
                    $delivery->id,
                    $status
                ),
            ];
            if (Schema::hasColumn('wallet_transactions', 'category')) {
                $txnData['category'] = $category;
            }
            WalletTransaction::create($txnData);

            if ($freshDelivery && Schema::hasColumn('message_delivery_statuses', 'wallet_charged')) {
                $freshDelivery->wallet_charged = true;
                $freshDelivery->wallet_amount = $price;
                $freshDelivery->save();
            }

            \Log::info('Wallet charged on bulk message', [
                'userId' => $locked->id,
                'delivery_id' => $delivery->id,
                'campaign_id' => $campaign->id,
                'category' => $category,
                'amount' => $price,
                'balance' => $newBalance,
                'status' => $status,
            ]);

            return [
                'charged' => true,
                'amount' => $price,
                'balance' => $newBalance,
                'category' => $category,
            ];
        });
    }

    /**
     * Charge immediately after WhatsApp API accepts the message (status=sent).
     */
    public function chargeForSent(MessageDeliveryStatus $delivery): ?array
    {
        return $this->chargeForDelivery($delivery, 'sent');
    }

    /**
     * Backfill: charge delivered/read rows that were never billed (wallet-enabled users only).
     */
    public function chargeUnbilledDeliveries(int $limit = 200): array
    {
        $this->ensureSchema();

        $query = MessageDeliveryStatus::query()
            ->whereIn('status', ['sent', 'delivered', 'read'])
            ->with(['bulkMessage.template', 'bulkMessage']);

        if (Schema::hasColumn('message_delivery_statuses', 'wallet_charged')) {
            $query->where(function ($q) {
                $q->where('wallet_charged', false)->orWhereNull('wallet_charged');
            });
        }

        $rows = $query->orderBy('id')->limit($limit)->get();
        $charged = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                $result = $this->chargeForDelivery($row);
                if ($result && ($result['charged'] ?? false)) {
                    $charged++;
                } elseif ($result && ($result['already_charged'] ?? false)) {
                    $skipped++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $failed++;
                \Log::error('Wallet backfill charge failed: ' . $e->getMessage(), [
                    'delivery_id' => $row->id,
                ]);
            }
        }

        return compact('charged', 'skipped', 'failed') + ['scanned' => $rows->count()];
    }

    /**
     * Refund wallet balance if a previously charged delivery fails (e.g. via Webhook failed status).
     * Idempotent per delivery row.
     */
    public function refundForFailedDelivery(MessageDeliveryStatus $delivery): ?array
    {
        $this->ensureSchema();

        $delivery->refresh();
        $delivery->loadMissing(['bulkMessage']);

        $campaign = $delivery->bulkMessage;
        if (!$campaign) {
            \Log::warning('Wallet refund skipped: no campaign', ['delivery_id' => $delivery->id]);
            return null;
        }

        $user = User::find($campaign->userId);
        if (!$user) {
            \Log::warning('Wallet refund skipped: user not found', [
                'delivery_id' => $delivery->id,
                'userId' => $campaign->userId,
            ]);
            return null;
        }

        $owner = User::find($user->getCompanyId()) ?: $user;

        $refDebit = 'delivery_' . $delivery->id;
        $refRefund = 'refund_' . $refDebit;

        return DB::transaction(function () use ($owner, $delivery, $refDebit, $refRefund, $campaign) {
            $debits = WalletTransaction::where('reference_id', $refDebit)->where('type', 'debit')->count();
            $refunds = WalletTransaction::where('reference_id', $refRefund)->where('type', 'credit')->count();

            if ($debits <= $refunds) {
                if (Schema::hasColumn('message_delivery_statuses', 'wallet_charged')) {
                    $delivery->wallet_charged = false;
                    $delivery->save();
                }
                return [
                    'already_refunded' => true,
                    'balance' => $this->getBalance($owner),
                ];
            }

            $lastDebit = WalletTransaction::where('reference_id', $refDebit)->where('type', 'debit')->latest('id')->first();
            $chargedAmount = (float) ($delivery->wallet_amount ?? ($lastDebit->amount ?? 0));

            $category = $this->resolveCategoryFromDelivery($delivery);
            if ($chargedAmount <= 0) {
                $chargedAmount = $this->priceForCategory($category);
            }

            if ($chargedAmount <= 0) {
                return null;
            }

            $locked = User::where('id', $owner->id)->lockForUpdate()->first();
            $newBalance = round((float) $locked->wallet_balance + $chargedAmount, 2);
            $locked->wallet_balance = $newBalance;
            $locked->save();

            $txnData = [
                'userId' => $locked->id,
                'type' => 'credit',
                'source' => 'refund',
                'amount' => $chargedAmount,
                'balance_after' => $newBalance,
                'reference_id' => $refRefund,
                'description' => sprintf(
                    'Refund %s ₹%s · failed bulk delivery #%d (campaign #%d)',
                    $category,
                    number_format($chargedAmount, 2),
                    $delivery->id,
                    $campaign->id
                ),
            ];
            if (Schema::hasColumn('wallet_transactions', 'category')) {
                $txnData['category'] = $category;
            }
            WalletTransaction::create($txnData);

            if (Schema::hasColumn('message_delivery_statuses', 'wallet_charged')) {
                $freshDelivery = MessageDeliveryStatus::where('id', $delivery->id)->lockForUpdate()->first();
                if ($freshDelivery) {
                    $freshDelivery->wallet_charged = false;
                    $freshDelivery->save();
                }
            }

            \Log::info('Wallet refunded for failed delivery', [
                'userId' => $locked->id,
                'delivery_id' => $delivery->id,
                'campaign_id' => $campaign->id,
                'refund_amount' => $chargedAmount,
                'balance_after' => $newBalance,
            ]);

            return [
                'refunded' => true,
                'amount' => $chargedAmount,
                'balance' => $newBalance,
            ];
        });
    }

    /**
     * Cancel an active payment link and mark local order cancelled
     */
    public function cancelPaymentLink(string $identifier, ?User $user = null): array
    {
        $this->ensureSchema();
        $orderQuery = WalletOrder::where('receipt', $identifier)
            ->orWhere('payment_link_id', $identifier)
            ->orWhere('razorpay_order_id', $identifier);

        if (is_numeric($identifier)) {
            $orderQuery->orWhere('id', (int) $identifier);
        }

        $order = $orderQuery->latest('id')->first();

        if (!$order) {
            return [
                'success' => false,
                'status' => false,
                'message' => 'Payment order not found',
            ];
        }

        if ($user && !$user->isAdmin() && (int) $order->userId !== (int) $user->id && (int) $order->userId !== (int) $user->getCompanyId()) {
            return [
                'success' => false,
                'status' => false,
                'message' => 'Access denied to cancel this payment link',
            ];
        }

        if (strtolower((string) $order->status) === 'paid') {
            return [
                'success' => false,
                'status' => false,
                'message' => 'Cannot cancel a payment link that is already paid',
            ];
        }

        $keys = $this->razorpayKeys();
        if ($order->payment_link_id && !empty($keys['key']) && !empty($keys['secret'])) {
            try {
                \Illuminate\Support\Facades\Http::withBasicAuth($keys['key'], $keys['secret'])
                    ->acceptJson()
                    ->timeout(15)
                    ->post("https://api.razorpay.com/v1/payment_links/{$order->payment_link_id}/cancel");
            } catch (\Throwable $e) {
                \Log::warning("Razorpay cancel payment link exception: " . $e->getMessage());
            }
        }

        $order->status = 'cancelled';
        $order->save();

        return [
            'success' => true,
            'status' => true,
            'message' => 'Payment link cancelled successfully',
            'order' => $order,
        ];
    }

    /**
     * Helper to detect Indian state from address string or state name.
     */
    public function extractStateFromAddress(?string $address): ?string
    {
        if (empty($address)) return null;
        $indianStates = [
            'Gujarat', 'Maharashtra', 'Delhi', 'Rajasthan', 'Karnataka', 'Tamil Nadu',
            'Uttar Pradesh', 'Madhya Pradesh', 'Punjab', 'Haryana', 'West Bengal',
            'Bihar', 'Kerala', 'Telangana', 'Andhra Pradesh', 'Odisha', 'Assam',
            'Jharkhand', 'Chhattisgarh', 'Uttarakhand', 'Himachal Pradesh', 'Goa',
            'Jammu and Kashmir', 'Chandigarh', 'Puducherry'
        ];
        foreach ($indianStates as $st) {
            if (stripos($address, $st) !== false) {
                return $st;
            }
        }
        if (preg_match('/\b(GJ|GUJ)\b/i', $address)) return 'Gujarat';
        if (preg_match('/\b(MH|MAH)\b/i', $address)) return 'Maharashtra';
        if (preg_match('/\b(DL)\b/i', $address)) return 'Delhi';
        if (preg_match('/\b(RJ|RAJ)\b/i', $address)) return 'Rajasthan';
        return null;
    }
}

