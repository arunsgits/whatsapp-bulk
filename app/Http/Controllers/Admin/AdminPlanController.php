<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\ActivePackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminPlanController extends Controller
{
    /**
     * List All Subscription Plans (with Monthly & Yearly Filtering)
     * GET /api/v1/plans or /api/v1/admin/plans
     * 
     * Query Parameters:
     * - period / billingPeriod / billing_period / billingCycle / planType / filter / type: 'monthly', 'yearly', 'all'
     * - search: string (search in packageName, packageDesc)
     * - popular: boolean (1, 0, true, false)
     * - grouped: boolean (if true, also returns 'grouped' object with monthly & yearly arrays)
     */
    public function index(Request $request)
    {
        try {
            $query = Package::query()->withCount([
                'activePackages as active_subscribers_count' => function ($q) {
                    $q->where('status', ActivePackage::STATUS_ACTIVE);
                },
                'activePackages as total_subscribers_count'
            ]);

            // 1. Period / Billing Cycle Filter (Monthly vs Yearly)
            $periodFilter = strtolower(trim((string) (
                $request->input('period') ??
                $request->input('billingPeriod') ??
                $request->input('billing_period') ??
                $request->input('billingCycle') ??
                $request->input('planType') ??
                $request->input('filter') ??
                $request->input('type') ??
                ''
            )));

            if (in_array($periodFilter, ['monthly', 'month', '/month', '30'])) {
                $query->monthly();
            } elseif (in_array($periodFilter, ['yearly', 'year', 'annual', 'annually', '/year', '365'])) {
                $query->yearly();
            }

            // 2. Keyword Search
            if ($request->filled('search')) {
                $search = trim($request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('packageName', 'like', "%{$search}%")
                      ->orWhere('packageDesc', 'like', "%{$search}%");
                });
            }

            // 3. Popular Filter
            if ($request->has('popular')) {
                $query->where('popular', $request->boolean('popular'));
            }

            // Fetch all packages for global counter summary
            $allPackages = Package::all();
            $monthlyCount = $allPackages->filter(fn($p) => $p->is_monthly)->count();
            $yearlyCount = $allPackages->filter(fn($p) => $p->is_yearly)->count();
            $popularCount = $allPackages->filter(fn($p) => (bool) $p->popular)->count();

            // Fetch filtered results sorted by duration and ID
            $packages = $query->orderBy('day', 'asc')->orderBy('id', 'asc')->get();

            $plans = $packages->map(function ($pkg) {
                return $pkg->toAdminArray();
            });

            $responseData = [
                'success' => true,
                'data' => $plans,
                'summary' => [
                    'totalPlans' => $allPackages->count(),
                    'monthlyPlansCount' => $monthlyCount,
                    'yearlyPlansCount' => $yearlyCount,
                    'popularPlansCount' => $popularCount,
                    'filteredCount' => $plans->count(),
                    'activeFilter' => $periodFilter ?: 'all',
                    'gst' => app(\App\Services\WalletService::class)->gstConfig(),
                ]
            ];

            // Provide grouped monthly/yearly breakdown if requested
            if ($request->boolean('grouped') || $request->get('format') === 'grouped') {
                $responseData['grouped'] = [
                    'monthly' => $plans->where('isMonthly', true)->values(),
                    'yearly' => $plans->where('isYearly', true)->values(),
                ];
            }

            return response()->json($responseData, 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error listing subscription plans: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Plans Overview Summary & Subscriber Statistics
     * GET /api/v1/plans/summary or /api/v1/admin/plans/summary
     */
    public function summary(Request $request)
    {
        try {
            $packages = Package::with(['activePackages' => function ($q) {
                $q->where('status', ActivePackage::STATUS_ACTIVE);
            }])->get();

            $monthlyPlans = $packages->filter(fn($p) => $p->is_monthly);
            $yearlyPlans = $packages->filter(fn($p) => $p->is_yearly);

            $totalActiveSubscribers = $packages->sum(fn($p) => $p->activePackages->count());
            $monthlySubscribers = $monthlyPlans->sum(fn($p) => $p->activePackages->count());
            $yearlySubscribers = $yearlyPlans->sum(fn($p) => $p->activePackages->count());

            return response()->json([
                'success' => true,
                'data' => [
                    'totalPlans' => $packages->count(),
                    'monthlyPlansCount' => $monthlyPlans->count(),
                    'yearlyPlansCount' => $yearlyPlans->count(),
                    'popularPlansCount' => $packages->filter(fn($p) => (bool) $p->popular)->count(),
                    'subscriptions' => [
                        'totalActive' => $totalActiveSubscribers,
                        'monthlySubscribers' => $monthlySubscribers,
                        'yearlySubscribers' => $yearlySubscribers,
                    ],
                    'gst' => app(\App\Services\WalletService::class)->gstConfig(),
                    'plans' => $packages->map(fn($p) => [
                        'id' => 'plan_' . $p->id,
                        'dbId' => $p->id,
                        'packageName' => $p->packageName,
                        'billingPeriod' => $p->billing_period,
                        'period' => $p->normalized_period,
                        'isMonthly' => $p->is_monthly,
                        'isYearly' => $p->is_yearly,
                        'price' => $p->price,
                        'days' => (int) $p->day,
                        'activeSubscribers' => $p->activePackages->count(),
                    ])->values()
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating plans summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Single Plan Details
     * GET /api/v1/plans/{planId} or /api/v1/admin/plans/{planId}
     */
    public function show(Request $request, $planId)
    {
        if ($planId === 'summary') {
            return $this->summary($request);
        }

        try {
            $rawId = str_replace('plan_', '', $planId);
            $package = Package::with(['activePackages' => function ($q) {
                $q->where('status', ActivePackage::STATUS_ACTIVE)->with('user');
            }])->find($rawId);

            if (!$package) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan not found'
                ], 404);
            }

            $planData = $package->toAdminArray();

            $planData['activeSubscribers'] = $package->activePackages->map(function ($ap) {
                return [
                    'userId' => 'usr_' . $ap->userId,
                    'dbUserId' => $ap->userId,
                    'userName' => $ap->user?->name ?? 'User #' . $ap->userId,
                    'userEmail' => $ap->user?->email,
                    'companyName' => $ap->user?->companyName,
                    'startDate' => $ap->startDate?->toIso8601String(),
                    'endDate' => $ap->endDate?->toIso8601String(),
                    'status' => $ap->statusLabel(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $planData
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving plan details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create New Plan (Monthly or Yearly, with optional pair creation)
     * POST /api/v1/plans or /api/v1/admin/plans
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'packageName' => 'required|string|max:255',
            'packageDesc' => 'nullable|string',
            'msgCount' => 'required',
            'contactCount' => 'nullable',
            'templateCount' => 'nullable|integer',
            'price' => 'nullable|string',
            'renewalPrice' => 'nullable|string',
            'renewal_price' => 'nullable|string',
            'yearlyPrice' => 'nullable|string',
            'yearly_price' => 'nullable|string',
            'yearlyRenewalPrice' => 'nullable|string',
            'yearly_renewal_price' => 'nullable|string',
            'period' => 'nullable|string',
            'billingPeriod' => 'nullable|string',
            'billing_period' => 'nullable|string',
            'billingCycle' => 'nullable|string',
            'planType' => 'nullable|string',
            'type' => 'nullable|string',
            'day' => 'nullable|integer',
            'days' => 'nullable|integer',
            'duration' => 'nullable|integer',
            'popular' => 'nullable|boolean',
            'color' => 'nullable|string',
            'iconName' => 'nullable|string',
            'features' => 'nullable',
            'createYearlyPair' => 'nullable|boolean',
            'gstPercentage' => 'nullable|numeric|min:0|max:100',
            'gst_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $rawMsgCount = preg_replace('/\D/', '', (string) $request->msgCount);
            $rawContactCount = preg_replace('/\D/', '', (string) ($request->contactCount ?? 1000));

            $billingInput = strtolower(trim((string) (
                $request->input('billingPeriod') ??
                $request->input('billing_period') ??
                $request->input('billingCycle') ??
                $request->input('billing_cycle') ??
                $request->input('planType') ??
                $request->input('plan_type') ??
                $request->input('period') ??
                $request->input('type') ??
                ''
            )));

            $dayInput = $request->input('day', $request->input('days', $request->input('duration')));

            // Detect if this plan is intended as Yearly or Monthly
            if (
                str_contains($billingInput, 'year') ||
                str_contains($billingInput, 'annual') ||
                ($dayInput && (int) $dayInput >= 300)
            ) {
                $period = '/year';
                $day = $dayInput && (int) $dayInput > 0 ? (int) $dayInput : 365;
            } else {
                $period = '/month';
                $day = $dayInput && (int) $dayInput > 0 ? (int) $dayInput : 30;
            }

            // Parse features (supports array or newline/comma separated string)
            $features = $request->input('features', ["Basic broadcast", "Standard support"]);
            if (is_string($features)) {
                $features = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $features))));
            }

            $customGst = null;
            if ($request->hasAny(['gstPercentage', 'gst_percentage'])) {
                $rawGst = $request->input('gstPercentage', $request->input('gst_percentage'));
                if ($rawGst !== null && $rawGst !== '') {
                    $customGst = round((float) $rawGst, 2);
                }
            }

            $renewalPrice = $request->input('renewalPrice', $request->input('renewal_price'));

            $package = Package::create([
                'packageName' => $request->packageName,
                'packageDesc' => $request->packageDesc,
                'day' => $day,
                'msgCount' => $rawMsgCount ? (int) $rawMsgCount : 10000,
                'templateCount' => (int) ($request->templateCount ?? 50),
                'contactCount' => $rawContactCount ? (int) $rawContactCount : 1000,
                'price' => $request->price ?? ($period === '/year' ? '₹39,999' : '₹3,999'),
                'renewal_price' => $renewalPrice,
                'period' => $period,
                'popular' => (bool) $request->input('popular', false),
                'color' => $request->input('color', 'indigo'),
                'iconName' => $request->input('iconName', 'package'),
                'features' => !empty($features) ? $features : ["Basic broadcast", "Standard support"],
                'gst_percentage' => $customGst,
            ]);

            $createdYearlyPair = null;
            // Optionally auto-create matching Yearly plan pair if creating a Monthly plan
            if ($period === '/month' && ($request->boolean('createYearlyPair') || $request->filled('yearlyPrice') || $request->filled('yearly_price'))) {
                $yearlyPriceInput = $request->input('yearlyPrice', $request->input('yearly_price'));
                if (!$yearlyPriceInput) {
                    $rawMonthly = (float) preg_replace('/[^\d.]/', '', (string) $package->price);
                    $yearlyPriceInput = $rawMonthly > 0 ? '₹' . number_format($rawMonthly * 10) : '₹39,999';
                }

                $yearlyRenewalPriceInput = $request->input('yearlyRenewalPrice', $request->input('yearly_renewal_price'));
                if (!$yearlyRenewalPriceInput && $renewalPrice) {
                    $rawRenewal = (float) preg_replace('/[^\d.]/', '', (string) $renewalPrice);
                    $yearlyRenewalPriceInput = $rawRenewal > 0 ? '₹' . number_format($rawRenewal * 10) : $yearlyPriceInput;
                }

                $createdYearlyPair = Package::create([
                    'packageName' => $package->packageName . ' (Yearly)',
                    'packageDesc' => $package->packageDesc,
                    'day' => 365,
                    'msgCount' => is_numeric($package->msgCount) ? ((int) $package->msgCount * 12) : $package->msgCount,
                    'templateCount' => $package->templateCount,
                    'contactCount' => $package->contactCount,
                    'price' => $yearlyPriceInput,
                    'renewal_price' => $yearlyRenewalPriceInput ?: $yearlyPriceInput,
                    'period' => '/year',
                    'popular' => false,
                    'color' => $package->color,
                    'iconName' => $package->iconName,
                    'features' => $package->features,
                    'gst_percentage' => $package->gst_percentage,
                ]);
            }

            $responseData = [
                'success' => true,
                'message' => 'Plan created successfully' . ($createdYearlyPair ? ' along with Yearly plan pair' : ''),
                'data' => $package->toAdminArray()
            ];

            if ($createdYearlyPair) {
                $responseData['yearlyPair'] = $createdYearlyPair->toAdminArray();
            }

            return response()->json($responseData, 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit / Update Plan (Supports changing cycle between Monthly and Yearly)
     * PUT/POST /api/v1/plans/{planId} or /api/v1/admin/plans/{planId}
     */
    public function update(Request $request, $planId)
    {
        try {
            $rawId = str_replace('plan_', '', $planId);
            $package = Package::find($rawId);

            if (!$package) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan not found'
                ], 404);
            }

            $updateData = [];
            if ($request->has('packageName')) $updateData['packageName'] = $request->packageName;
            if ($request->has('packageDesc')) $updateData['packageDesc'] = $request->packageDesc;
            if ($request->has('price')) $updateData['price'] = $request->price;
            if ($request->hasAny(['renewalPrice', 'renewal_price'])) {
                $updateData['renewal_price'] = $request->input('renewalPrice', $request->input('renewal_price'));
            }
            if ($request->has('popular')) $updateData['popular'] = (bool) $request->popular;
            if ($request->has('color')) $updateData['color'] = $request->color;
            if ($request->has('iconName')) $updateData['iconName'] = $request->iconName;

            if ($request->hasAny(['gstPercentage', 'gst_percentage'])) {
                $rawGst = $request->input('gstPercentage', $request->input('gst_percentage'));
                $updateData['gst_percentage'] = ($rawGst !== null && $rawGst !== '') ? round((float) $rawGst, 2) : null;
            }

            if ($request->has('features')) {
                $features = $request->features;
                if (is_string($features)) {
                    $features = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $features))));
                }
                $updateData['features'] = $features;
            }

            // Billing Cycle / Period Update (switching between Monthly and Yearly)
            if ($request->hasAny(['period', 'billingPeriod', 'billing_period', 'billingCycle', 'billing_cycle', 'planType', 'plan_type', 'type'])) {
                $billingInput = strtolower(trim((string) (
                    $request->input('billingPeriod') ??
                    $request->input('billing_period') ??
                    $request->input('billingCycle') ??
                    $request->input('billing_cycle') ??
                    $request->input('planType') ??
                    $request->input('plan_type') ??
                    $request->input('period') ??
                    $request->input('type') ??
                    ''
                )));

                if (str_contains($billingInput, 'year') || str_contains($billingInput, 'annual')) {
                    $updateData['period'] = '/year';
                    // Auto-adjust duration to 365 days if switching to yearly and no custom day passed
                    if (!$request->hasAny(['day', 'days', 'duration']) && (int) $package->day <= 90) {
                        $updateData['day'] = 365;
                    }
                } elseif (str_contains($billingInput, 'month')) {
                    $updateData['period'] = '/month';
                    // Auto-adjust duration to 30 days if switching to monthly and no custom day passed
                    if (!$request->hasAny(['day', 'days', 'duration']) && (int) $package->day >= 300) {
                        $updateData['day'] = 30;
                    }
                }
            }

            // Explicit Days / Duration
            if ($request->hasAny(['day', 'days', 'duration'])) {
                $updateData['day'] = (int) $request->input('day', $request->input('days', $request->input('duration')));
                if (!isset($updateData['period'])) {
                    $updateData['period'] = $updateData['day'] >= 300 ? '/year' : '/month';
                }
            }

            // Message Limit
            if ($request->has('msgCount')) {
                $rawMsg = preg_replace('/\D/', '', (string) $request->msgCount);
                $updateData['msgCount'] = $rawMsg ? (int) $rawMsg : $package->msgCount;
            }

            // Contact Limit
            if ($request->has('contactCount')) {
                $rawContact = preg_replace('/\D/', '', (string) $request->contactCount);
                $updateData['contactCount'] = $rawContact ? (int) $rawContact : $package->contactCount;
            }

            // Template Limit
            if ($request->has('templateCount')) {
                $updateData['templateCount'] = (int) $request->templateCount;
            }

            $package->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Plan updated successfully',
                'data' => $package->fresh()->toAdminArray()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Duplicate an existing plan as Monthly or Yearly
     * POST /api/v1/plans/{planId}/duplicate or /api/v1/admin/plans/{planId}/duplicate
     */
    public function duplicate(Request $request, $planId)
    {
        try {
            $rawId = str_replace('plan_', '', $planId);
            $sourcePackage = Package::find($rawId);

            if (!$sourcePackage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Source plan not found'
                ], 404);
            }

            $targetCycle = strtolower(trim((string) $request->input('targetCycle', $request->input('billingPeriod', ''))));

            // If target cycle not specified, toggle from current: monthly -> yearly, yearly -> monthly
            $isSourceYearly = $sourcePackage->is_yearly;
            $makeYearly = $targetCycle ? (str_contains($targetCycle, 'year') || str_contains($targetCycle, 'annual')) : !$isSourceYearly;

            $targetPeriod = $makeYearly ? '/year' : '/month';
            $targetDays = $makeYearly ? 365 : 30;
            $cycleLabel = $makeYearly ? 'Yearly' : 'Monthly';

            $defaultName = $request->input('packageName', $sourcePackage->packageName . ' (' . $cycleLabel . ')');

            // Price calculation with 2-months-free discount pattern for yearly
            $defaultPrice = $request->input('price');
            if (!$defaultPrice) {
                $rawPrice = (float) preg_replace('/[^\d.]/', '', (string) $sourcePackage->price);
                if ($rawPrice > 0) {
                    if ($makeYearly && !$isSourceYearly) {
                        $calculated = round($rawPrice * 10); // 10 months billing = 2 months free discount
                        $defaultPrice = '₹' . number_format($calculated);
                    } elseif (!$makeYearly && $isSourceYearly) {
                        $calculated = round($rawPrice / 10);
                        $defaultPrice = '₹' . number_format($calculated);
                    } else {
                        $defaultPrice = $sourcePackage->price;
                    }
                } else {
                    $defaultPrice = $sourcePackage->price;
                }
            }

            $newPackage = Package::create([
                'packageName' => $defaultName,
                'packageDesc' => $request->input('packageDesc', $sourcePackage->packageDesc),
                'day' => (int) $request->input('day', $targetDays),
                'msgCount' => $request->input('msgCount', $sourcePackage->msgCount),
                'templateCount' => (int) $request->input('templateCount', $sourcePackage->templateCount ?? 50),
                'contactCount' => $request->input('contactCount', $sourcePackage->contactCount ?? 1000),
                'price' => $defaultPrice,
                'renewal_price' => $request->input('renewalPrice', $request->input('renewal_price', $sourcePackage->renewal_price)),
                'period' => $targetPeriod,
                'popular' => (bool) $request->input('popular', false),
                'color' => $request->input('color', $sourcePackage->color ?? 'indigo'),
                'iconName' => $request->input('iconName', $sourcePackage->iconName ?? 'package'),
                'features' => $request->input('features', $sourcePackage->features ?? []),
                'gst_percentage' => $request->has('gstPercentage') ? $request->input('gstPercentage') : ($request->has('gst_percentage') ? $request->input('gst_percentage') : $sourcePackage->gst_percentage),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Plan successfully duplicated as {$cycleLabel} plan",
                'data' => $newPackage->toAdminArray()
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error duplicating plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Quick Toggle Plan Cycle between Monthly and Yearly
     * POST /api/v1/plans/{planId}/toggle-cycle or /api/v1/admin/plans/{planId}/toggle-cycle
     */
    public function toggleCycle(Request $request, $planId)
    {
        try {
            $rawId = str_replace('plan_', '', $planId);
            $package = Package::find($rawId);

            if (!$package) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan not found'
                ], 404);
            }

            $willBeYearly = !$package->is_yearly;
            $newPeriod = $willBeYearly ? '/year' : '/month';
            $newDays = $willBeYearly ? 365 : 30;
            $cycleLabel = $willBeYearly ? 'Yearly' : 'Monthly';

            $package->update([
                'period' => $newPeriod,
                'day' => $newDays
            ]);

            return response()->json([
                'success' => true,
                'message' => "Plan billing cycle successfully toggled to {$cycleLabel}",
                'data' => $package->fresh()->toAdminArray()
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error toggling plan cycle: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Plan
     * DELETE /api/v1/plans/{planId} or /api/v1/admin/plans/{planId}
     */
    public function destroy(Request $request, $planId)
    {
        try {
            $rawId = str_replace('plan_', '', $planId);
            $package = Package::find($rawId);

            if (!$package) {
                return response()->json([
                    'success' => false,
                    'message' => 'Plan not found'
                ], 404);
            }

            $activeCount = ActivePackage::where('packageId', $package->id)
                ->where('status', ActivePackage::STATUS_ACTIVE)
                ->count();

            if ($activeCount > 0 && !$request->boolean('force')) {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot delete plan. There are {$activeCount} active user subscription(s) on this plan. Pass force=true to proceed.",
                    'activeSubscribersCount' => $activeCount
                ], 422);
            }

            $package->delete();

            return response()->json([
                'success' => true,
                'message' => 'Plan successfully deleted'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get GST Configuration for Plans
     * GET /api/v1/plans/gst or /api/v1/admin/plans/gst
     */
    public function getGstSettings(Request $request)
    {
        try {
            $gst = app(\App\Services\WalletService::class)->gstConfig();
            return response()->json([
                'success' => true,
                'data' => $gst
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving GST settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Global GST Tax Rate / Settings (18% or any custom percentage)
     * POST/PUT /api/v1/plans/gst or /api/v1/admin/plans/gst
     */
    public function updateGstSettings(Request $request)
    {
        return app(AdminSettingsController::class)->updateGst($request);
    }

    /**
     * List all Plan Subscription Transactions (all users)
     * GET /api/v1/plans/transactions or /api/v1/admin/plans/transactions
     */
    public function transactions(Request $request)
    {
        $request->merge(['transaction_type' => 'plan']);
        return app(AdminWalletController::class)->transactions($request);
    }

    /**
     * Admin Upgrade User Subscription Plan
     * POST /api/v1/plans/upgrade or /api/v1/admin/plans/upgrade
     * POST /api/v1/plan/upgrade or /api/v1/admin/plan/upgrade
     */
    public function upgrade(Request $request)
    {
        $userId = $request->input('userId', $request->input('user_id'));
        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Target userId is required to upgrade user subscription plan'
            ], 422);
        }

        return app(AdminUserController::class)->upgradeUserPlan($request, $userId);
    }
}
