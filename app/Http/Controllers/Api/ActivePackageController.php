<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivePackage;
use App\Models\Package;
use App\Models\User;
use App\Models\UserPackageHistory;
use App\Models\WalletOrder;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ActivePackageController extends Controller
{
    /**
     * Get all active packages (global view)
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $query = ActivePackage::with(['package', 'user']);

            if ($request->filled('user_id')) {
                $query->where('userId', $request->query('user_id'));
            } elseif (!$request->boolean('global', false) && !$request->boolean('all', false)) {
                $query->where('userId', $userId);
            }

            $activePackages = $query->orderBy('created_at', 'desc')->get()->map(function (ActivePackage $ap) {
                return array_merge($ap->toArray(), $ap->toPlanPayload(), [
                    'package' => $ap->package,
                    'user' => $ap->user,
                ]);
            });

            return response()->json([
                'status' => true,
                'data' => $activePackages
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new active package (subscribe to a package)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'sometimes|exists:users,id',
            'packageId' => 'required|exists:packages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $isAdmin = $request->user()->role === 'admin';
            $targetUserId = $isAdmin && $request->userId ? $request->userId : $request->user()->getCompanyId();

            // Fetch user
            $user = User::find($targetUserId);
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Fetch package
            $package = Package::find($request->packageId);
            if (!$package) {
                return response()->json([
                    'status' => false,
                    'message' => 'Package not found'
                ], 404);
            }

            // Check if user already has an active package
            $existingActivePackage = ActivePackage::where('userId', $targetUserId)
                ->where('status', 1)
                ->first();

            if ($existingActivePackage) {
                return response()->json([
                    'status' => false,
                    'message' => 'User already has an active package. Please deactivate the current package first.'
                ], 400);
            }

            $isYearly = (bool) $package->is_yearly || (int) ($package->day ?? 0) >= 300;
            $days = (int) ($package->day ?: ($isYearly ? 365 : 30));
            if ($isYearly && $days < 300) {
                $days = 365;
            }

            $startDate = now();
            $endDate = now()->addDays($days);

            $activePackage = ActivePackage::create([
                'userId' => $targetUserId,
                'packageId' => $request->packageId,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'day' => $days,
                'status' => 1,
                'usedMsgCount' => 0,
                'lastMonthlyReset' => $startDate,
                'monthlyUsedMsgCount' => 0,
                'monthlyUsedContactCount' => 0,
                'monthlyUsedTemplateCount' => 0,
            ]);

            // Log to UserPackageHistory (Idempotent - deduplicates rapid submissions and identical periods)
            try {
                UserPackageHistory::recordOnce([
                    'userId' => $targetUserId,
                    'packageId' => $package->id,
                    'assignedBy' => $request->user()?->id ?: $targetUserId,
                    'action' => 'subscribe',
                    'packageName' => $package->packageName,
                    'price' => (float) $package->price,
                    'msgCount' => (int) $package->msgCount,
                    'contactCount' => (int) ($package->contactCount ?? $package->contact_count ?? 1000),
                    'templateCount' => (int) ($package->templateCount ?? 50),
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'day' => $days,
                    'notes' => "Subscribed to plan '{$package->packageName}'",
                ]);
            } catch (\Throwable $histEx) {
                Log::warning("Failed to log package history in store: " . $histEx->getMessage());
            }

            // Dispatch plan notification email to user and CC admin (if enabled)
            try {
                app(\App\Services\PlanMailService::class)->sendPlanNotification($user, $package, [
                    'action' => 'subscribed',
                    'activePkg' => $activePackage,
                    'price' => (float) $package->price,
                    'days' => $days,
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                ]);
            } catch (\Throwable $mailEx) {
                Log::warning("Failed to dispatch plan notification email: " . $mailEx->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'Package activated successfully',
                'data' => array_merge($activePackage->toArray(), $activePackage->toPlanPayload(), [
                    'package' => $package,
                    'user' => $user,
                ])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific active package
     */
    public function show(Request $request, $id)
    {
        try {
            // For global plan, allow access to any active package
            $activePackage = ActivePackage::with(['package', 'user'])->find($id);

            if (!$activePackage) {
                return response()->json([
                    'status' => false,
                    'message' => 'Active package not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $activePackage
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an active package
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|integer|in:0,1',
            'endDate' => 'sometimes|date|after:startDate',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $userId = $request->user()->getCompanyId();
            $isAdmin = $request->user()->role === 'admin';

            $query = $isAdmin ? ActivePackage::query() : ActivePackage::where('userId', $userId);
            $activePackage = $query->find($id);

            if (!$activePackage) {
                return response()->json([
                    'status' => false,
                    'message' => 'Active package not found'
                ], 404);
            }

            $activePackage->update($request->only(['status', 'endDate']));

            return response()->json([
                'status' => true,
                'message' => 'Active package updated successfully',
                'data' => $activePackage->load(['package', 'user'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete an active package
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $isAdmin = $request->user()->role === 'admin';

            $query = $isAdmin ? ActivePackage::query() : ActivePackage::where('userId', $userId);
            $activePackage = $query->find($id);

            if (!$activePackage) {
                return response()->json([
                    'status' => false,
                    'message' => 'Active package not found'
                ], 404);
            }

            $activePackage->delete();

            return response()->json([
                'status' => true,
                'message' => 'Active package deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renew an active package
     */
    public function renew(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Check password for renewal
            if ($request->password !== env('RENEW_PLAN_PASSWORD', 'renew123')) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized: Invalid password'
                ], 401);
            }

            $activePackage = ActivePackage::with('package')->find($id);

            if (!$activePackage) {
                return response()->json([
                    'status' => false,
                    'message' => 'Active package not found'
                ], 404);
            }

            $package = $activePackage->package;
            if (!$package) {
                return response()->json([
                    'status' => false,
                    'message' => 'Package not found'
                ], 404);
            }

            // Prevent duplicate renewal if requested within a 15-second debounce window (e.g. double click)
            $recentRenewal = UserPackageHistory::where('userId', $activePackage->userId)
                ->where('action', 'renew')
                ->where('created_at', '>=', now()->subSeconds(15))
                ->latest('id')
                ->first();

            if ($recentRenewal) {
                return response()->json([
                    'status' => true,
                    'message' => 'Package renewed successfully',
                    'data' => $activePackage->load(['package', 'user']),
                    'deduplicated' => true,
                ]);
            }

            // Renew by adding package / active package days to current end date
            $renewDays = (int) ($activePackage->day ?: ($package->day ?: 30));
            $currentEndDate = $activePackage->endDate && \Carbon\Carbon::parse($activePackage->endDate)->isFuture()
                ? \Carbon\Carbon::parse($activePackage->endDate)
                : now();
            $newEndDate = $currentEndDate->copy()->addDays($renewDays);

            $activePackage->update([
                'endDate' => $newEndDate,
                'status' => ActivePackage::statusFromDates($activePackage->startDate, $newEndDate),
            ]);

            // Log to UserPackageHistory (Idempotent)
            try {
                UserPackageHistory::recordOnce([
                    'userId' => $activePackage->userId,
                    'packageId' => $package->id,
                    'assignedBy' => $request->user()?->id ?: $activePackage->userId,
                    'action' => 'renew',
                    'packageName' => $package->packageName,
                    'price' => (float) ($activePackage->custom_price ?: $package->price),
                    'msgCount' => (int) $activePackage->resolvedMessageLimit(),
                    'contactCount' => (int) $activePackage->resolvedContactLimit(),
                    'templateCount' => (int) $activePackage->resolvedTemplateLimit(),
                    'startDate' => $activePackage->startDate,
                    'endDate' => $newEndDate,
                    'day' => $renewDays,
                    'notes' => "Plan '{$package->packageName}' renewed for {$renewDays} days",
                ]);
            } catch (\Throwable $histEx) {
                Log::warning("Failed to log package history in renew: " . $histEx->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'Package renewed successfully',
                'data' => $activePackage->load(['package', 'user'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get package renewal status and information
     */
    public function getRenewalStatus(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $activePackage = ActivePackage::where('userId', $userId)
                ->with('package')
                ->latest('id')
                ->first();

            if (!$activePackage) {
                // Fallback if user is under global default active plan
                $activePackage = ActivePackage::where('status', 1)
                    ->with('package')
                    ->latest('id')
                    ->first();
            }

            if (!$activePackage) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active plan found'
                ], 404);
            }

            $pkg = $activePackage->package;
            $now = now();
            $daysUntilExpiry = $activePackage->getDaysUntilExpiry();
            $isExpired = $activePackage->endDate ? $now->gt($activePackage->endDate) : true;
            $needsRenewal = $isExpired || $daysUntilExpiry <= 7;
            $isInRenewalPeriod = $activePackage->isInMonthlyRenewalPeriod();
            $needsManualReactivation = $activePackage->needsManualReactivation();

            $nextRenewalDate = $activePackage->nextRenewalDate();

            $displayRenewalPrice = $pkg ? ($pkg->renewal_price ?: $pkg->price) : '0';
            $effectiveRenewalPrice = $pkg ? $pkg->getEffectiveRenewalPrice() : 0.0;

            $walletService = app(WalletService::class);
            $gst = $walletService->gstConfig();
            $gstRate = $gst['enabled']
                ? ((isset($pkg->gst_percentage) && is_numeric($pkg->gst_percentage)) ? (float) $pkg->gst_percentage : $gst['percentage'])
                : 0.0;
            $gstAmount = $gstRate > 0 ? round(($effectiveRenewalPrice * $gstRate) / 100, 2) : 0.0;
            $totalRenewalAmount = round($effectiveRenewalPrice + $gstAmount, 2);

            $packageData = array_merge($activePackage->toPlanPayload(), [
                'name' => $pkg->packageName ?? null,
                'packageName' => $pkg->packageName ?? null,
                'packageDesc' => $pkg->packageDesc ?? '',
                'daysUntilExpiry' => $daysUntilExpiry,
                'days_remaining' => $daysUntilExpiry,
                'is_expired' => $isExpired,
                'needs_renewal' => $needsRenewal,
                'needsRenewal' => $needsRenewal,
                'isInRenewalPeriod' => $isInRenewalPeriod,
                'needsManualReactivation' => $needsManualReactivation,
                'lastMonthlyReset' => $activePackage->lastMonthlyReset,
                'nextRenewalDate' => $nextRenewalDate,
                'renewalPrice' => $displayRenewalPrice,
                'renewal_price' => $displayRenewalPrice,
                'effective_renewal_price' => $effectiveRenewalPrice,
                'duration_days' => (int) ($activePackage->day ?: ($pkg?->day ?: 30)),
            ]);

            return response()->json([
                'status' => true,
                'success' => true,
                'data' => [
                    'needs_renewal' => $needsRenewal,
                    'needsRenewal' => $needsRenewal,
                    'show_renewal_popup' => $needsRenewal,
                    'is_expired' => $isExpired,
                    'days_remaining' => $daysUntilExpiry,
                    'daysUntilExpiry' => $daysUntilExpiry,
                    'renewal_price' => $displayRenewalPrice,
                    'renewalPrice' => $displayRenewalPrice,
                    'effective_renewal_price' => $effectiveRenewalPrice,
                    'renewal_price_breakdown' => [
                        'base_price' => $effectiveRenewalPrice,
                        'gst_percentage' => $gstRate,
                        'gst_amount' => $gstAmount,
                        'total_amount' => $totalRenewalAmount,
                        'formatted_total' => '₹' . number_format($totalRenewalAmount, 2),
                    ],
                    'support' => [
                        'email' => config('mail.from.address', 'support@zapreach.com'),
                        'phone' => '+91 9824734531',
                    ],
                    'package' => $packageData,
                    'usage' => [
                        'monthlyUsedMessages' => $activePackage->monthlyUsedMsgCount,
                        'monthlyUsedTemplates' => $activePackage->monthlyUsedTemplateCount,
                        'monthlyUsedContacts' => $activePackage->monthlyUsedContactCount,
                        'usedContacts' => \App\Models\Contact::where('userId', $activePackage->userId)->count(),
                        'remainingContacts' => max(
                            0,
                            $activePackage->resolvedContactLimit() - \App\Models\Contact::where('userId', $activePackage->userId)->count()
                        ),
                        'totalUsedMessages' => $activePackage->usedMsgCount,
                        'yearlyUsedMessages' => $activePackage->usedMsgCount,
                    ],
                    'limits' => [
                        'messageLimit' => $activePackage->resolvedMessageLimit(),
                        'templateLimit' => $activePackage->resolvedTemplateLimit(),
                        'contactLimit' => $activePackage->resolvedContactLimit(),
                        'baseMsgCount' => $activePackage->baseMessageLimit(),
                        'addonMsgCount' => $activePackage->addonMessageCount(),
                        'baseContactCount' => $activePackage->baseContactLimit(),
                        'addonContactCount' => $activePackage->addonContactCount(),
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Buy / Subscribe to a plan.
     * POST /api/plan/buy or POST /api/plan/subscribe
     */
    public function subscribe(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required_without:packageId',
            'packageId' => 'required_without:package_id',
            'payment_method' => 'nullable|string|in:razorpay,wallet',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Package ID is required',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $user = $request->user();
            $packageId = $request->input('package_id', $request->input('packageId'));
            $rawId = is_numeric($packageId) ? (int) $packageId : (int) str_replace('plan_', '', (string) $packageId);
            $package = Package::find($rawId);

            if (!$package) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => 'Plan not found.'
                ], 404);
            }

            $walletService = app(WalletService::class);
            $paymentMethod = strtolower((string) $request->input('payment_method', 'razorpay'));

            if ($paymentMethod === 'wallet') {
                $result = $walletService->purchasePlanFromWallet($user, $package, 'subscription');
                return response()->json([
                    'status' => true,
                    'success' => true,
                    'message' => "Successfully subscribed to plan '{$package->packageName}' using wallet balance!",
                    'data' => $result
                ], 200);
            }

            // Razorpay payment link
            $callbackUrl = $request->input('callbackUrl', $request->input('callback_url', url('/api/plan/payment-callback')));
            $result = $walletService->createPlanPaymentLink($user, $package->id, 'subscription', [
                'callback_url' => $callbackUrl,
                'back_url' => $request->input('back_url', $request->input('backUrl', $request->input('return_url', $request->input('cancel_url')))),
                'billing_state' => $request->input('billing_state', $request->input('billingState', $request->input('state', $request->input('customer_state')))),
            ]);

            return response()->json([
                'status' => true,
                'success' => true,
                'message' => "Payment link for plan '{$package->packageName}' generated successfully",
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Alias for subscribe()
     * POST /api/plan/buy
     */
    public function buyPlan(Request $request)
    {
        return $this->subscribe($request);
    }

    /**
     * Upgrade plan to a new / higher plan.
     * POST /api/plan/upgrade
     */
    public function upgrade(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'package_id' => 'required_without:packageId',
            'packageId' => 'required_without:package_id',
            'payment_method' => 'nullable|string|in:razorpay,wallet',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Package ID is required',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $user = $request->user();
            $targetUserId = $user->getCompanyId();
            $packageId = $request->input('package_id', $request->input('packageId'));
            $rawId = is_numeric($packageId) ? (int) $packageId : (int) str_replace('plan_', '', (string) $packageId);
            $newPackage = Package::find($rawId);

            if (!$newPackage) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => 'Selected plan not found.'
                ], 404);
            }

            // Check current active package
            $currentActive = ActivePackage::where('userId', $targetUserId)
                ->where('status', ActivePackage::STATUS_ACTIVE)
                ->latest('id')
                ->first();

            if ($currentActive && (int)$currentActive->packageId === (int)$newPackage->id) {
                return response()->json([
                    'status' => false,
                    'success' => false,
                    'message' => "You are already subscribed to the '{$newPackage->packageName}' plan. Use renewal if you wish to extend it."
                ], 422);
            }

            $walletService = app(WalletService::class);
            $paymentMethod = strtolower((string) $request->input('payment_method', 'razorpay'));

            if ($paymentMethod === 'wallet') {
                $result = $walletService->purchasePlanFromWallet($user, $newPackage, 'upgrade');
                return response()->json([
                    'status' => true,
                    'success' => true,
                    'message' => "Successfully upgraded to plan '{$newPackage->packageName}' using wallet balance!",
                    'data' => $result
                ], 200);
            }

            // Razorpay payment link
            $callbackUrl = $request->input('callbackUrl', $request->input('callback_url', url('/api/plan/payment-callback')));
            $result = $walletService->createPlanPaymentLink($user, $newPackage->id, 'upgrade', [
                'callback_url' => $callbackUrl,
                'back_url' => $request->input('back_url', $request->input('backUrl', $request->input('return_url', $request->input('cancel_url')))),
                'billing_state' => $request->input('billing_state', $request->input('billingState', $request->input('state', $request->input('customer_state')))),
            ]);

            return response()->json([
                'status' => true,
                'success' => true,
                'message' => "Payment link for upgrading to '{$newPackage->packageName}' generated successfully",
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Generate Renewal Payment Link for the authenticated user.
     * POST /api/plan/renewal-payment-link
     */
    public function generateRenewalPaymentLink(Request $request)
    {
        try {
            $user = $request->user();
            $packageId = $request->input('package_id', $request->input('packageId'));
            $walletService = app(WalletService::class);

            if ($request->input('payment_method') === 'wallet') {
                $rawId = $packageId ? (is_numeric($packageId) ? (int) $packageId : (int) str_replace('plan_', '', (string) $packageId)) : null;
                $pkg = $rawId ? Package::find($rawId) : null;
                if (!$pkg) {
                    $activePkg = ActivePackage::where('userId', $user->getCompanyId())->latest('id')->first();
                    $pkg = $activePkg?->package;
                }
                if (!$pkg) {
                    return response()->json(['status' => false, 'message' => 'Plan not found'], 404);
                }
                $res = $walletService->purchasePlanFromWallet($user, $pkg, 'renewal');
                return response()->json([
                    'status' => true,
                    'success' => true,
                    'message' => 'Subscription plan renewed successfully using wallet balance!',
                    'data' => $res
                ], 200);
            }

            $result = $walletService->createRenewalPaymentLink($user, $packageId, [
                'callback_url' => $request->input('callbackUrl', $request->input('callback_url', url('/api/plan/payment-callback'))),
                'back_url' => $request->input('back_url', $request->input('backUrl', $request->input('return_url', $request->input('cancel_url')))),
                'billing_state' => $request->input('billing_state', $request->input('billingState', $request->input('state', $request->input('customer_state')))),
                'custom_price' => $request->input('custom_price', $request->input('customPrice', $request->input('renewal_price', $request->input('renewalPrice', $request->input('price'))))),
                'custom_plan_name' => $request->input('custom_plan_name', $request->input('customPlanName', $request->input('plan_name', $request->input('packageName')))),
                'days' => $request->input('day', $request->input('days', $request->input('daysToAdd', $request->input('days_to_add')))),
                'msg_count' => $request->input('msg_count', $request->input('msgCount', $request->input('custom_msg_count'))),
                'contact_count' => $request->input('contact_count', $request->input('contactCount', $request->input('custom_contact_count'))),
                'template_count' => $request->input('template_count', $request->input('templateCount', $request->input('custom_template_count'))),
                'is_custom_plan' => $request->boolean('is_custom_plan', $request->boolean('custom_plan', false)),
            ]);

            return response()->json([
                'status' => true,
                'success' => true,
                'message' => 'Renewal payment link generated successfully',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Error generating renewal payment link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify payment and complete plan renewal.
     * POST /api/plan/verify-renewal
     */
    public function verifyRenewalPayment(Request $request)
    {
        return $this->verify($request);
    }

    /**
     * Unified payment verification for buy, upgrade, or renewal.
     * POST /api/plan/verify
     */
    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'razorpay_payment_id' => 'required_without:payment_id|string',
            'payment_id' => 'required_without:razorpay_payment_id|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Payment ID is required',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $paymentId = $request->input('razorpay_payment_id', $request->input('payment_id'));
            $orderIdentifier = $request->input('razorpay_order_id', $request->input('order_id', $request->input('razorpay_payment_link_id', $request->input('payment_link_id', $request->input('receipt', $request->input('id'))))));
            $signature = $request->input('razorpay_signature', $request->input('signature'));

            $walletService = app(WalletService::class);
            $result = $walletService->verifyPaymentLinkPayment(
                (string) $orderIdentifier,
                (string) $paymentId,
                $signature ? (string) $signature : null,
                $request->user()
            );

            $order = isset($result['order']) ? $result['order'] : null;
            $type = $order ? ($order->payment_type ?? 'plan') : 'plan';
            $actionLabel = $type === 'upgrade' ? 'upgraded' : ($type === 'subscription' ? 'activated' : 'renewed');

            return response()->json([
                'status' => true,
                'success' => true,
                'message' => "Subscription plan {$actionLabel} successfully!",
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Handle Razorpay Payment Link Callback / Redirect
     * GET /api/plan/payment-callback or /payment/callback
     */
    public function handlePaymentCallback(Request $request)
    {
        $paymentLinkId = $request->input('razorpay_payment_link_id');
        $paymentId = $request->input('razorpay_payment_id');
        $signature = $request->input('razorpay_signature');
        $status = $request->input('razorpay_payment_link_status');

        $walletService = app(WalletService::class);
        $activated = false;
        $order = null;

        if ($paymentLinkId && $paymentId) {
            try {
                $result = $walletService->verifyPaymentLinkPayment($paymentLinkId, $paymentId, $signature);
                $activated = !empty($result['package_activated']) || !empty($result['already_credited']);
                $order = WalletOrder::where('payment_link_id', $paymentLinkId)->first();
            } catch (\Throwable $e) {
                Log::warning('Payment callback verification warning: ' . $e->getMessage());
            }
        }

        if (!$order && $paymentLinkId) {
            $order = WalletOrder::where('payment_link_id', $paymentLinkId)
                ->orWhere('receipt', $paymentLinkId)
                ->orWhere('razorpay_order_id', $paymentLinkId)
                ->first();
        }

        if (!$order && $paymentId) {
            $order = WalletOrder::where('razorpay_payment_id', $paymentId)
                ->orWhere('receipt', 'like', "%{$paymentId}%")
                ->first();
        }

        if (!$order && $request->filled('receipt')) {
            $order = WalletOrder::where('receipt', $request->input('receipt'))->first();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'status' => true,
                'success' => true,
                'message' => 'Payment callback processed',
                'activated' => $activated,
            ]);
        }

        $dashboardUrl = rtrim(config('app.url'), '/') . '/dashboard';
        $faviconUrl = rtrim(config('app.url'), '/') . '/favicon.ico';

        $targetUser = $order ? User::find($order->userId) : null;
        $activePkg = $targetUser ? ActivePackage::where('userId', $targetUser->id)->orderByDesc('id')->first() : null;
        $package = ($order && $order->package_id) ? Package::find($order->package_id) : ($activePkg && $activePkg->packageId ? Package::find($activePkg->packageId) : null);

        $notes = [];
        if ($order && !empty($order->notes)) {
            $notes = is_string($order->notes) ? (json_decode($order->notes, true) ?: []) : (is_array($order->notes) ? $order->notes : []);
        }

        $userName = $targetUser ? htmlspecialchars($targetUser->name, ENT_QUOTES, 'UTF-8') : 'Subscriber';
        $packageName = $package ? htmlspecialchars($package->packageName, ENT_QUOTES, 'UTF-8') : ($notes['package_name'] ?? 'Enterprise (Advanced)');

        // Days & Billing Period
        $days = (int) ($package?->day ?: ($activePkg?->day ?: ($notes['days'] ?? 30)));
        $periodLabel = ($days >= 28 && $days <= 31) ? 'Per Month' : (($days >= 360 && $days <= 366) ? 'Per Year' : "{$days} Days");
        if (!empty($notes['billing_period'])) {
            $periodLabel = $notes['billing_period'];
        }

        // Quotas
        $msgCount = $activePkg?->custom_msg_count ?: ($package?->msgCount ?? ($notes['messages'] ?? null));
        $contactCount = $activePkg?->custom_contact_count ?: ($package?->contactCount ?? ($package?->contact_count ?? ($notes['contacts'] ?? null)));
        $templateCount = $activePkg?->custom_template_count ?: ($package?->templateCount ?? ($notes['templates'] ?? null));
        $expiryDate = $activePkg?->endDate ? \Carbon\Carbon::parse($activePkg->endDate)->format('d M, Y') : null;

        // Pricing & GST Calculation
        $baseAmount = $order ? (float) ($order->base_amount ?: ($order->amount ?: 0)) : 0.0;
        $gstRate = $order ? (float) ($order->gst_percentage ?: 0) : 0.0;
        $gstAmount = $order ? (float) ($order->gst_amount ?: 0) : 0.0;
        $totalAmount = $order ? (float) ($order->total_amount ?: ($baseAmount + $gstAmount)) : 0.0;

        if ($totalAmount <= 0 && $package) {
            $totalAmount = (float) $package->price;
            $baseAmount = $totalAmount;
        }

        if ($gstAmount <= 0 && $baseAmount > 0 && $gstRate > 0) {
            $gstAmount = round(($baseAmount * $gstRate) / 100, 2);
            $totalAmount = round($baseAmount + $gstAmount, 2);
        }

        $gstRateDisplay = (int) $gstRate == $gstRate ? (int) $gstRate : $gstRate;
        $baseFmt = number_format($baseAmount, 2);
        $gstFmt = number_format($gstAmount, 2);
        $totalFmt = number_format($totalAmount, 2);
        $paymentIdDisplay = htmlspecialchars((string) ($paymentId ?: ($order->receipt ?? 'Online Gateway')), ENT_QUOTES, 'UTF-8');

        // Build details rows HTML
        $detailRows = [];
        $detailRows[] = '<div class="row"><span>Status:</span> <span style="color:#22c55e;font-weight:600;">Active</span></div>';
        $detailRows[] = '<div class="row"><span>Plan:</span> <span>' . $packageName . '</span></div>';
        $detailRows[] = '<div class="row"><span>Billing Cycle:</span> <span>' . htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') . '</span></div>';
        
        if ($msgCount !== null && $msgCount > 0) {
            $detailRows[] = '<div class="row"><span>Total Messages:</span> <span>' . number_format((int)$msgCount) . '</span></div>';
        }
        if ($contactCount !== null && $contactCount > 0) {
            $detailRows[] = '<div class="row"><span>Total Contacts:</span> <span>' . number_format((int)$contactCount) . '</span></div>';
        }
        if ($templateCount !== null && $templateCount > 0) {
            $detailRows[] = '<div class="row"><span>Template Limit:</span> <span>' . number_format((int)$templateCount) . '</span></div>';
        }
        if ($expiryDate) {
            $detailRows[] = '<div class="row"><span>Valid Until:</span> <span>' . $expiryDate . '</span></div>';
        }
        if ($paymentIdDisplay) {
            $detailRows[] = '<div class="row"><span>Payment ID:</span> <span>' . $paymentIdDisplay . '</span></div>';
        }
        if ($baseAmount > 0 && $gstAmount > 0) {
            $detailRows[] = '<div class="row"><span>Base Amount:</span> <span>₹' . $baseFmt . '</span></div>';
            $detailRows[] = '<div class="row"><span>GST (' . $gstRateDisplay . '%):</span> <span>₹' . $gstFmt . '</span></div>';
        }
        $detailRows[] = '<div class="row total-row"><span>Total Paid:</span> <span>₹' . $totalFmt . '</span></div>';

        $detailsHtml = implode("\n            ", $detailRows);

        return response()->view('payment-success', [
            'dashboardUrl' => $dashboardUrl,
            'faviconUrl' => $faviconUrl,
            'userName' => $userName,
            'packageName' => $packageName,
            'periodLabel' => $periodLabel,
            'msgCount' => $msgCount,
            'contactCount' => $contactCount,
            'templateCount' => $templateCount,
            'expiryDate' => $expiryDate,
            'paymentId' => $paymentIdDisplay,
            'baseAmount' => $baseAmount,
            'baseFmt' => $baseFmt,
            'gstAmount' => $gstAmount,
            'gstFmt' => $gstFmt,
            'gstRateDisplay' => $gstRateDisplay,
            'totalFmt' => $totalFmt,
            'detailsHtml' => $detailsHtml,
        ], 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Render a direct payment checkout page for users paying an order receipt.
     * GET /payment/checkout/{receipt}
     */
    public function renderCheckoutPage(Request $request, $receipt)
    {
        $walletService = app(WalletService::class);
        $order = WalletOrder::where('receipt', $receipt)
            ->orWhere('payment_link_id', $receipt)
            ->orWhere('razorpay_order_id', $receipt)
            ->first();

        if (!$order && is_numeric($receipt)) {
            $order = WalletOrder::find((int) $receipt);
        }

        if (!$order) {
            abort(404, 'Payment order not found.');
        }

        $user = User::find($order->userId);
        $package = $order->package_id ? Package::find($order->package_id) : null;
        $keys = $walletService->razorpayKeys();

        $amountPaise = $order->amount_paise ?: (int) round($order->total_amount * 100);
        $keyId = $keys['key'] ?: '';
        $orderId = $order->razorpay_order_id ?: '';
        $planName = $package ? htmlspecialchars($package->packageName, ENT_QUOTES, 'UTF-8') : 'Plan Renewal';
        $userName = $user ? htmlspecialchars($user->name, ENT_QUOTES, 'UTF-8') : 'Subscriber';
        $userEmail = $user ? htmlspecialchars($user->email, ENT_QUOTES, 'UTF-8') : '';
        $userPhone = $user ? htmlspecialchars($user->number ?? '', ENT_QUOTES, 'UTF-8') : '';
        $totalFormatted = number_format((float) $order->total_amount, 2);
        $verifyUrl = url('/api/plan/verify-renewal');
        $callbackUrl = url('/api/plan/payment-callback');

        $days = $package ? (int) ($package->day ?: 30) : 30;
        $periodLabel = ($days >= 28 && $days <= 31) ? 'Per Month' : (($days >= 360 && $days <= 366) ? 'Per Year' : "{$days} Days");

        $notes = json_decode($order->notes ?? '', true) ?: [];
        $backUrl = htmlspecialchars($request->input('back_url', $request->input('return_url', $notes['back_url'] ?? '')), ENT_QUOTES, 'UTF-8');
        $billingState = $notes['billing_state'] ?? 'Gujarat';
        $isGujarat = (stripos($billingState, 'gujarat') !== false || strcasecmp(trim($billingState), 'GJ') === 0);

        $gstRate = (float) ($order->gst_percentage ?? 18);
        $gstAmount = (float) ($order->gst_amount ?? 0);
        $cgstRate = round($gstRate / 2, 2);
        $cgstAmount = round($gstAmount / 2, 2);
        $sgstRate = round($gstRate / 2, 2);
        $sgstAmount = round($gstAmount / 2, 2);
        $igstRate = $gstRate;
        $igstAmount = $gstAmount;

        $cgstFormatted = number_format($cgstAmount, 2);
        $sgstFormatted = number_format($sgstAmount, 2);
        $igstFormatted = number_format($igstAmount, 2);

        $taxRowsHtml = $isGujarat
            ? "<div class=\"row\"><span>CGST ({$cgstRate}%)</span><span>₹{$cgstFormatted}</span></div><div class=\"row\"><span>SGST ({$sgstRate}%)</span><span>₹{$sgstFormatted}</span></div>"
            : "<div class=\"row\"><span>IGST ({$igstRate}%)</span><span>₹{$igstFormatted}</span></div>";

        return response()->view('payment-checkout', [
            'planName' => $planName,
            'userName' => $userName,
            'userEmail' => $userEmail,
            'userPhone' => $userPhone,
            'periodLabel' => $periodLabel,
            'order' => $order,
            'totalFormatted' => $totalFormatted,
            'taxRowsHtml' => $taxRowsHtml,
            'keyId' => $keyId,
            'orderId' => $orderId,
            'amountPaise' => $amountPaise,
            'receipt' => $receipt,
            'backUrl' => $backUrl,
            'verifyUrl' => $verifyUrl,
            'callbackUrl' => $callbackUrl,
        ], 200, ['Content-Type' => 'text/html']);
    }

    /**
     * Cancel an active payment link
     * POST /api/plan/payment-link/cancel or /api/v1/plan/payment-link/cancel
     */
    public function cancelPaymentLink(Request $request)
    {
        $receipt = $request->input('receipt', $request->input('payment_link_id', $request->input('order_id', $request->query('receipt'))));
        if (!$receipt) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Receipt or payment link identifier is required',
            ], 400);
        }

        $walletService = app(WalletService::class);
        $result = $walletService->cancelPaymentLink((string) $receipt, $request->user());

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get user active plan
     */
    public function getUserPlan(Request $request, $userId = null)
    {
        try {
            $targetUserId = $userId ?: $request->user()->id;
            $query = ActivePackage::whereIn('status', [ActivePackage::STATUS_ACTIVE, ActivePackage::STATUS_UPCOMING])->with('package');

            if (!$request->boolean('global', false) && !$request->boolean('all', false)) {
                $query->where('userId', $targetUserId);
            }

            $plans = $query->orderBy('created_at', 'desc')->get();

            if ($plans->isEmpty() && !$request->boolean('global', false)) {
                // Fallback to active plan if no user specific plan found
                $plans = ActivePackage::where('status', 1)
                    ->with('package')
                    ->orderBy('created_at', 'desc')
                    ->get();
            }

            // Update status from dates (upcoming / expired / active)
            foreach ($plans as $plan) {
                $computed = $plan->computedStatus();
                if ((int) $plan->status !== $computed) {
                    $plan->update(['status' => $computed]);
                }
            }

            return response()->json([
                'status' => true,
                'data' => $plans->map(fn (ActivePackage $plan) => array_merge(
                    $plan->toArray(),
                    $plan->toPlanPayload(),
                    ['package' => $plan->package]
                ))
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign a package to a user (Admin only)
     */
    public function assignPackage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'required|exists:users,id',
            'packageId' => 'required|exists:packages,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json([
                    'status' => false,
                    'message' => 'Only admins can assign packages to users'
                ], 403);
            }

            $targetUserId = $request->userId;
            $packageId = $request->packageId;

            // Fetch user
            $user = User::find($targetUserId);
            if (!$user) {
                return response()->json([
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Fetch package
            $package = Package::find($packageId);
            if (!$package) {
                return response()->json([
                    'status' => false,
                    'message' => 'Package not found'
                ], 404);
            }

            // Check if user already has an active package
            $existingActivePackage = ActivePackage::where('userId', $targetUserId)
                ->where('status', 1)
                ->first();

            $rolloverMessages = 0;
            $rolloverContacts = 0;
            $rolloverTemplates = 0;
            $remainingDays = 0;

            if ($existingActivePackage) {
                if ($request->boolean('replace', true) || $request->boolean('force', false) || $request->boolean('upgrade', false)) {
                    // Carry forward remaining unused quotas and remaining days if upgrade or requested
                    if ($request->boolean('upgrade') || $request->boolean('carry_forward', true)) {
                        $oldLimit = $existingActivePackage->resolvedMessageLimit();
                        $oldUsed = $existingActivePackage->is_yearly
                            ? (int) ($existingActivePackage->monthlyUsedMsgCount ?? 0)
                            : (int) ($existingActivePackage->usedMsgCount ?? 0);
                        $rolloverMessages = max(0, $oldLimit - $oldUsed);

                        $oldContactLimit = $existingActivePackage->resolvedContactLimit();
                        $usedContacts = max(
                            (int) ($existingActivePackage->monthlyUsedContactCount ?? 0),
                            \App\Models\Contact::where('userId', $targetUserId)->count()
                        );
                        $remainingContacts = max(0, $oldContactLimit - $usedContacts);
                        $rolloverContacts = max($remainingContacts, $existingActivePackage->addonContactCount());

                        $oldTemplateLimit = $existingActivePackage->resolvedTemplateLimit();
                        $usedTemplates = max(
                            (int) ($existingActivePackage->monthlyUsedTemplateCount ?? 0),
                            \App\Models\Template::where('userId', $targetUserId)->where('type', 'custom')->count()
                        );
                        $remainingTemplates = max(0, $oldTemplateLimit - $usedTemplates);
                        $baseOldTemplateLimit = (int) ($existingActivePackage->package?->templateCount ?? 0);
                        $addonTemplates = max(0, $oldTemplateLimit - $baseOldTemplateLimit);
                        $rolloverTemplates = max($remainingTemplates, $addonTemplates);

                        if ($existingActivePackage->endDate && Carbon::parse($existingActivePackage->endDate)->isFuture()) {
                            $remainingDays = (int) max(0, ceil(now()->floatDiffInDays(Carbon::parse($existingActivePackage->endDate), false)));
                        }
                    }
                    $existingActivePackage->update(['status' => ActivePackage::STATUS_EXPIRED]);
                } else {
                    return response()->json([
                        'status' => false,
                        'message' => 'User already has an active package. Pass replace=true or upgrade=true to update it.'
                    ], 400);
                }
            }

            $isYearly = (bool) $package->is_yearly || (int) ($package->day ?? 0) >= 300;
            $packageDays = (int) ($request->input('days') ?: ($package->day ?: ($isYearly ? 365 : 30)));
            if ($isYearly && $packageDays < 300) {
                $packageDays = 365;
            }
            $totalDays = $packageDays + $remainingDays;

            $startDate = $request->filled('startDate') ? Carbon::parse($request->input('startDate')) : now();
            $endDate = $request->filled('endDate') ? Carbon::parse($request->input('endDate')) : (clone $startDate)->addDays($totalDays);

            $baseMsg = (int) ($package->msgCount ?? 0);
            $customMsgCount = $request->input('custom_msg_count', $request->input('msgCount'));
            $finalMsgCount = $customMsgCount !== null ? (int) $customMsgCount : ($rolloverMessages > 0 ? ($baseMsg + $rolloverMessages) : null);

            $baseContacts = (int) ($package->contactCount ?? 1000);
            $customContactCount = $request->input('custom_contact_count', $request->input('contactCount'));
            $finalContactCount = $customContactCount !== null ? (int) $customContactCount : ($rolloverContacts > 0 ? ($baseContacts + $rolloverContacts) : null);

            $baseTemplates = (int) ($package->templateCount ?? 50);
            $customTemplateCount = $request->input('custom_template_count', $request->input('templateCount'));
            $finalTemplateCount = $customTemplateCount !== null ? (int) $customTemplateCount : ($rolloverTemplates > 0 ? ($baseTemplates + $rolloverTemplates) : null);

            $activePackage = ActivePackage::create([
                'userId' => $targetUserId,
                'packageId' => $packageId,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'day' => $totalDays,
                'status' => 1,
                'usedMsgCount' => 0,
                'custom_msg_count' => $finalMsgCount,
                'custom_contact_count' => $finalContactCount,
                'custom_template_count' => $finalTemplateCount,
                'custom_price' => $request->input('custom_price', $request->input('price')),
                'lastMonthlyReset' => $startDate,
                'monthlyUsedMsgCount' => 0,
                'monthlyUsedContactCount' => 0,
                'monthlyUsedTemplateCount' => 0,
            ]);

            if ($request->boolean('generate_invoice', true)) {
                try {
                    app(\App\Services\InvoiceService::class)->createPlanInvoice($user, $package, $activePackage, [
                        'type' => $request->boolean('upgrade') ? 'upgrade' : 'subscription',
                        'payment_method' => $request->input('payment_method', 'admin'),
                        'assigned_by' => $request->user()?->name ?: 'Admin',
                    ]);
                } catch (\Throwable $e) {}
            }

            // Log to UserPackageHistory (Idempotent - prevents duplicate assignment logs)
            try {
                $isCustom = $request->filled('custom_price') || $request->filled('custom_msg_count') || $request->filled('custom_contact_count') || $request->filled('custom_template_count');
                $assignedAction = $request->boolean('upgrade') ? 'upgrade' : ($isCustom ? 'custom_plan' : 'assigned');
                $assignedPlanName = $request->input('custom_plan_name', $request->input('packageName', $package->packageName));
                UserPackageHistory::recordOnce([
                    'userId' => $targetUserId,
                    'packageId' => $package->id,
                    'assignedBy' => $request->user()?->id,
                    'action' => $assignedAction,
                    'packageName' => $assignedPlanName,
                    'price' => (float) ($request->input('custom_price', $request->input('price', $package->price))),
                    'msgCount' => (int) ($finalMsgCount ?: $package->msgCount),
                    'contactCount' => (int) ($finalContactCount ?: ($package->contactCount ?? $package->contact_count ?? 1000)),
                    'templateCount' => (int) ($finalTemplateCount ?: ($package->templateCount ?? 50)),
                    'startDate' => $startDate,
                    'endDate' => $endDate,
                    'day' => $totalDays,
                    'notes' => $request->input('notes') ?: "Package '{$assignedPlanName}' assigned by " . ($request->user()?->name ?: 'Admin'),
                ]);
            } catch (\Throwable $histEx) {
                Log::warning("Failed to log package history in assignPackage: " . $histEx->getMessage());
            }

            return response()->json([
                'status' => true,
                'message' => 'Package assigned successfully to user',
                'data' => array_merge($activePackage->toArray(), $activePackage->toPlanPayload(), [
                    'package' => $package,
                    'user' => $user
                ])
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user plan & subscription history (buy, upgrade, renew, admin assign, custom plans)
     * GET /api/plan/history/{userId?}
     */
    public function getUserPlanHistory(Request $request, $userId = null)
    {
        try {
            $user = $request->user();
            $companyId = method_exists($user, 'getCompanyId') ? $user->getCompanyId() : $user->id;
            $isAdmin = strtolower((string) ($user->role ?? '')) === 'admin' || (method_exists($user, 'isAdmin') && $user->isAdmin());

            if ($userId !== null && $userId !== '') {
                $requestedUserId = (int) $userId;
                // If not admin, the user can only view their own company's history
                if (!$isAdmin && $requestedUserId !== (int) $companyId && $requestedUserId !== (int) $user->id) {
                    $targetUserId = (int) $companyId;
                } else {
                    $targetUserId = $requestedUserId;
                }
            } else {
                $targetUserId = (int) $companyId;
            }

            $query = UserPackageHistory::with(['package', 'assignedByUser', 'user']);

            if (!$isAdmin || (!$request->boolean('global', false) && !$request->boolean('all', false))) {
                $query->where('userId', $targetUserId);
            }

            $histories = $query->orderBy('created_at', 'desc')->get();

            // Deduplicate any identical legacy records in-memory so response is always clean
            $dedupedHistories = collect();
            $seenDedupKeys = [];
            foreach ($histories as $h) {
                $startStr = $h->startDate ? Carbon::parse($h->startDate)->toDateString() : '';
                $endStr = $h->endDate ? Carbon::parse($h->endDate)->toDateString() : '';
                $minuteKey = $h->created_at ? $h->created_at->format('Y-m-d H:i') : $h->id;
                $key = "{$h->userId}_{$h->action}_{$h->packageId}_{$startStr}_{$endStr}_{$minuteKey}";

                if (isset($seenDedupKeys[$key])) {
                    continue; // Skip exact duplicate
                }
                $seenDedupKeys[$key] = true;
                $dedupedHistories->push($h);
            }
            $histories = $dedupedHistories;

            // Fallback: If no history exists yet, build from current ActivePackage so data is never empty
            if ($histories->isEmpty() && $targetUserId > 0) {
                $activePkg = ActivePackage::where('userId', $targetUserId)->with('package')->latest('id')->first();
                if ($activePkg) {
                    $pkg = $activePkg->package;
                    $packageName = $pkg ? $pkg->packageName : 'Custom Plan';
                    $price = (float) ($activePkg->custom_price ?: ($pkg ? $pkg->price : 0.00));
                    $msgLimit = (int) ($activePkg->custom_msg_count ?: ($pkg ? $pkg->msgCount : 0));
                    $contactLimit = (int) ($activePkg->custom_contact_count ?: ($pkg ? ($pkg->contactCount ?? $pkg->contact_count ?? 0) : 0));
                    $templateLimit = (int) ($activePkg->custom_template_count ?: ($pkg ? ($pkg->templateCount ?? 0) : 0));
                    $days = (int) ($activePkg->day ?: ($pkg ? $pkg->day : 30));
                    $startDate = $activePkg->startDate ? Carbon::parse($activePkg->startDate) : null;
                    $endDate = $activePkg->endDate ? Carbon::parse($activePkg->endDate) : null;

                    $isYearly = (bool) ($activePkg->is_yearly || $days >= 300);
                    $period = $isYearly ? 'yearly' : 'monthly';
                    $periodLabel = $isYearly ? 'Yearly' : 'Monthly';

                    $pkgObj = [
                        'id' => $pkg ? (int) $pkg->id : (int) $activePkg->packageId,
                        'packageName' => $packageName,
                        'packageDesc' => $pkg ? ($pkg->packageDesc ?? '') : '',
                        'day' => $days,
                        'msgCount' => $msgLimit,
                        'templateCount' => $templateLimit,
                        'contactCount' => $contactLimit,
                        'price' => $price,
                        'period' => $period,
                        'periodLabel' => $periodLabel,
                        'billingPeriod' => $period,
                        'billingCycle' => $period,
                        'isYearly' => $isYearly,
                        'isMonthly' => !$isYearly,
                        'createdAt' => $pkg && $pkg->created_at ? $pkg->created_at->toISOString() : null,
                        'updatedAt' => $pkg && $pkg->updated_at ? $pkg->updated_at->toISOString() : null,
                    ];

                    $fallbackHistory = [
                        'id' => (int) $activePkg->id,
                        'userId' => (int) $activePkg->userId,
                        'packageId' => $pkgObj,
                        'packageName' => $packageName,
                        'action' => 'active_plan',
                        'action_label' => 'Current Active Plan',
                        'price' => $price,
                        'msgCount' => $msgLimit,
                        'contactCount' => $contactLimit,
                        'templateCount' => $templateLimit,
                        'startDate' => $startDate ? $startDate->toISOString() : null,
                        'endDate' => $endDate ? $endDate->toISOString() : null,
                        'day' => $days,
                        'period' => $period,
                        'periodLabel' => $periodLabel,
                        'billingCycle' => $period,
                        'isYearly' => $isYearly,
                        'isMonthly' => !$isYearly,
                        'status' => $activePkg->computedStatus(),
                        'statusLabel' => $activePkg->statusLabel(),
                        'notes' => 'Current active plan',
                        'assigned_by' => 'System',
                        'createdAt' => $activePkg->created_at ? $activePkg->created_at->toISOString() : null,
                        'updatedAt' => $activePkg->updated_at ? $activePkg->updated_at->toISOString() : null,
                    ];

                    return response()->json([
                        'success' => true,
                        'status' => true,
                        'count' => 1,
                        'total' => 1,
                        'data' => [$fallbackHistory],
                        'activePackage' => array_merge(
                            $activePkg->toArray(),
                            $activePkg->toPlanPayload(),
                            ['package' => $activePkg->package]
                        ),
                    ]);
                }
            }

            // Pre-load current active packages for relevant users to accurately determine active vs expired status
            $userIds = $histories->pluck('userId')->filter()->unique()->all();
            if (empty($userIds) && $targetUserId > 0) {
                $userIds = [$targetUserId];
            }

            $activePackagesByUser = ActivePackage::whereIn('userId', $userIds)
                ->latest('id')
                ->get()
                ->keyBy('userId');

            $activeAssignedPerUser = [];
            $lastPlanStartForUser = [];

            // Transform data to match desired format with full backward compatibility
            $transformedData = $histories->map(function (UserPackageHistory $h) use ($activePackagesByUser, &$activeAssignedPerUser, &$lastPlanStartForUser) {
                $pkg = $h->package;
                $packageName = $h->packageName ?: ($pkg ? $pkg->packageName : 'Custom Plan');
                $price = (float) ($h->price ?: ($pkg ? $pkg->price : 0.00));
                $msgLimit = (int) ($h->msgCount ?: ($pkg ? $pkg->msgCount : 0));
                $contactLimit = (int) ($h->contactCount ?: ($pkg ? ($pkg->contactCount ?? $pkg->contact_count ?? 0) : 0));
                $templateLimit = (int) ($h->templateCount ?: ($pkg ? ($pkg->templateCount ?? 0) : 0));
                $days = (int) ($h->day ?: ($pkg ? $pkg->day : 30));
                $startDate = $h->startDate ? Carbon::parse($h->startDate) : null;
                $endDate = $h->endDate ? Carbon::parse($h->endDate) : null;
                $now = now();
                $hUserId = (int) $h->userId;

                $userActivePkg = $activePackagesByUser->get($hUserId);
                $hasActivePlan = $userActivePkg
                    ? ((int) $userActivePkg->status !== ActivePackage::STATUS_EXPIRED && (!$userActivePkg->endDate || $now->lte(Carbon::parse($userActivePkg->endDate))))
                    : true;

                // Status resolution:
                // 1. Future start date -> upcoming
                // 2. Past end date -> expired
                // 3. Within date window (startDate <= now <= endDate):
                //    - Only the FIRST (most recent) valid plan for an active user is 'active'
                //    - All prior/older plans (which were upgraded, renewed, or superseded) are marked as 'expired'
                $isCurrentActivePlan = false;
                if ($startDate && $now->lt($startDate)) {
                    $computedStatus = ActivePackage::STATUS_UPCOMING;
                    $statusLabel = 'upcoming';
                } elseif ($endDate && $now->gt($endDate)) {
                    $computedStatus = ActivePackage::STATUS_EXPIRED;
                    $statusLabel = 'expired';
                } else {
                    if ($hasActivePlan && empty($activeAssignedPerUser[$hUserId])) {
                        $computedStatus = ActivePackage::STATUS_ACTIVE;
                        $statusLabel = 'active';
                        $activeAssignedPerUser[$hUserId] = true;
                        $isCurrentActivePlan = true;
                    } else {
                        // Superseded by newer plan / upgrade / renewal
                        $computedStatus = ActivePackage::STATUS_EXPIRED;
                        $statusLabel = 'expired';
                    }
                }

                // Effective lifecycle tracking:
                // When an upgrade occurs, the old plan was superseded when the newer plan started.
                $newerPlanStart = $lastPlanStartForUser[$hUserId] ?? null;
                $lastPlanStartForUser[$hUserId] = $startDate ?: ($h->created_at ? Carbon::parse($h->created_at) : $now);

                if ($isCurrentActivePlan) {
                    $effectiveEndDate = $endDate;
                    $lastActiveDate = null;
                    $daysRemaining = $endDate ? max(0, (int) ceil($now->floatDiffInDays($endDate, false))) : 0;
                } else {
                    // For expired/superseded plans: active lifecycle ended on the upgrade/replacement date
                    $lastActiveDate = $newerPlanStart ?: ($endDate && $now->gt($endDate) ? $endDate : $now);
                    $effectiveEndDate = $lastActiveDate;
                    $daysRemaining = 0; // Remaining days were transferred to the new upgraded plan
                }

                // Billing period resolution: monthly vs yearly
                $diffDays = ($startDate && $endDate) ? (int) abs($startDate->diffInDays($endDate)) : 0;
                $nameContainsYear = stripos((string) ($packageName ?: ($pkg?->packageName ?? '')), 'year') !== false;
                $pkgIsYearly = $pkg && $pkg->is_yearly;

                $isYearly = (bool) ($pkgIsYearly || $days >= 300 || $diffDays >= 300 || $nameContainsYear);
                $isMonthly = !$isYearly;

                $period = $isYearly ? 'yearly' : 'monthly';
                $periodLabel = $isYearly ? 'Yearly' : 'Monthly';
                $billingPeriod = $isYearly ? 'yearly' : 'monthly';

                $action = strtolower(trim((string) ($h->action ?: 'assigned')));
                $actionLabel = match ($action) {
                    'buy', 'subscribe', 'subscription', 'created' => 'Purchased',
                    'upgrade', 'upgraded' => 'Upgraded',
                    'renew', 'renewed' => 'Renewed',
                    'custom', 'custom_plan', 'customplan' => 'Custom Plan Assigned',
                    'assigned', 'assign' => 'Assigned by Admin',
                    'monthly_cycle_reset' => 'Monthly Cycle Reset',
                    default => ucwords(str_replace(['_', '-'], ' ', $action)),
                };

                $pkgObj = [
                    'id' => $pkg ? (int) $pkg->id : (int) ($h->packageId ?: $h->id),
                    'packageName' => $packageName,
                    'packageDesc' => $pkg ? ($pkg->packageDesc ?? '') : '',
                    'day' => $days,
                    'msgCount' => $msgLimit,
                    'templateCount' => $templateLimit,
                    'contactCount' => $contactLimit,
                    'price' => $price,
                    'period' => $period,
                    'periodLabel' => $periodLabel,
                    'billingPeriod' => $billingPeriod,
                    'billingCycle' => $billingPeriod,
                    'isYearly' => $isYearly,
                    'isMonthly' => $isMonthly,
                    'createdAt' => $pkg && $pkg->created_at ? $pkg->created_at->toISOString() : ($h->created_at ? $h->created_at->toISOString() : null),
                    'updatedAt' => $pkg && $pkg->updated_at ? $pkg->updated_at->toISOString() : ($h->updated_at ? $h->updated_at->toISOString() : null),
                ];

                $assignedByInfo = null;
                if ($h->assignedByUser) {
                    $assignedByInfo = [
                        'id' => (int) $h->assignedByUser->id,
                        'name' => (string) $h->assignedByUser->name,
                        'email' => (string) $h->assignedByUser->email,
                        'role' => (string) $h->assignedByUser->role,
                    ];
                } elseif ($h->assignedBy) {
                    $assignedByInfo = $h->assignedBy == $h->userId ? 'Self (Online Checkout)' : 'Super Admin';
                } else {
                    $assignedByInfo = 'System';
                }

                return [
                    'id' => (int) $h->id,
                    'userId' => (int) $h->userId,
                    'packageId' => $pkgObj,
                    'packageName' => $packageName,
                    'action' => $action,
                    'action_label' => $actionLabel,
                    'price' => $price,
                    'msgCount' => $msgLimit,
                    'contactCount' => $contactLimit,
                    'templateCount' => $templateLimit,
                    'startDate' => $startDate ? $startDate->toISOString() : null,
                    'endDate' => $endDate ? $endDate->toISOString() : null,
                    'effective_end_date' => $effectiveEndDate ? $effectiveEndDate->toISOString() : null,
                    'last_active_date' => $lastActiveDate ? $lastActiveDate->toISOString() : null,
                    'day' => $days,
                    'days_remaining' => $daysRemaining,
                    'period' => $period,
                    'periodLabel' => $periodLabel,
                    'billingCycle' => $billingPeriod,
                    'isYearly' => $isYearly,
                    'isMonthly' => $isMonthly,
                    'status' => $computedStatus,
                    'statusLabel' => $statusLabel,
                    'is_active' => ($statusLabel === 'active'),
                    'is_expired' => ($statusLabel === 'expired'),
                    'notes' => $h->notes,
                    'assigned_by' => $assignedByInfo,
                    'createdAt' => $h->created_at ? $h->created_at->toISOString() : null,
                    'updatedAt' => $h->updated_at ? $h->updated_at->toISOString() : null,
                ];
            });

            // Also attach current active package summary for convenience
            $activePkg = ActivePackage::where('userId', $targetUserId)->with('package')->latest('id')->first();

            return response()->json([
                'success' => true,
                'status' => true,
                'count' => $transformedData->count(),
                'total' => $transformedData->count(),
                'data' => $transformedData,
                'activePackage' => $activePkg ? array_merge(
                    $activePkg->toArray(),
                    $activePkg->toPlanPayload(),
                    ['package' => $activePkg->package]
                ) : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
