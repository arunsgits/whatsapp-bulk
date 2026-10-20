<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PackageController extends Controller
{
    /**
     * Get all available packages / subscription plans
     */
    public function index(Request $request)
    {
        try {
            $query = Package::query();

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

            $walletService = app(\App\Services\WalletService::class);
            $gst = $walletService->gstConfig();

            // Check if authenticated user to mark current plan
            $authUser = null;
            try {
                $authUser = auth('api')->user() ?: $request->user();
            } catch (\Throwable $e) {}

            $activePackage = null;
            if ($authUser) {
                $owner = $walletService->ownerUser($authUser);
                $activePackage = \App\Models\ActivePackage::where('userId', $owner->id)
                    ->where('status', \App\Models\ActivePackage::STATUS_ACTIVE)
                    ->latest('id')
                    ->first();
            }

            $currentPackageId = $activePackage ? (int) $activePackage->packageId : null;

            $packages = $query->orderBy('price', 'asc')
                ->get()
                ->map(function (Package $pkg) use ($gst, $currentPackageId) {
                    $rawPrice = (float) preg_replace('/[^\d.]/', '', (string) ($pkg->price ?? 0));
                    $renewalPrice = $pkg->getEffectiveRenewalPrice();

                    $gstRate = $gst['enabled']
                        ? ((isset($pkg->gst_percentage) && is_numeric($pkg->gst_percentage)) ? (float) $pkg->gst_percentage : $gst['percentage'])
                        : 0.0;
                    $gstAmount = $gstRate > 0 ? round(($rawPrice * $gstRate) / 100, 2) : 0.0;
                    $totalPrice = round($rawPrice + $gstAmount, 2);

                    $renewalGstAmount = $gstRate > 0 ? round(($renewalPrice * $gstRate) / 100, 2) : 0.0;
                    $totalRenewalPrice = round($renewalPrice + $renewalGstAmount, 2);

                    $isCurrent = $currentPackageId !== null && (int) $pkg->id === $currentPackageId;

                    $isYearly = (bool) $pkg->is_yearly;
                    $isMonthly = (bool) $pkg->is_monthly;
                    $billingCycle = $isYearly ? 'yearly' : 'monthly';
                    $durationText = $isYearly ? '1 Year' : (((int)$pkg->day >= 28 && (int)$pkg->day <= 31) ? 'Per Month' : ($pkg->day ? "{$pkg->day} days" : 'Per Month'));

                    $arr = $pkg->toArray();
                    $arr['isCurrentPlan'] = $isCurrent;
                    $arr['canUpgrade'] = !$isCurrent;
                    $arr['isYearly'] = $isYearly;
                    $arr['isMonthly'] = $isMonthly;
                    $arr['billingCycle'] = $billingCycle;
                    $arr['period'] = $isYearly ? '/year' : '/month';
                    $arr['durationText'] = $durationText;
                    $arr['periodLabel'] = $isYearly ? 'Yearly' : 'Monthly';
                    $arr['renewalPrice'] = $renewalPrice;
                    $arr['basePrice'] = $rawPrice;
                    $arr['gst_percentage'] = $gstRate;
                    $arr['gst_amount'] = $gstAmount;
                    $arr['total_price'] = $totalPrice;
                    $arr['renewal_total_price'] = $totalRenewalPrice;
                    $arr['formatted_price'] = '₹' . number_format($totalPrice, 2);
                    $arr['formatted_renewal_total_price'] = '₹' . number_format($totalRenewalPrice, 2);
                    $arr['price_breakdown'] = [
                        'base_price' => $rawPrice,
                        'gst_percentage' => $gstRate,
                        'gst_amount' => $gstAmount,
                        'total_amount' => $totalPrice,
                    ];
                    $arr['renewal_price_breakdown'] = [
                        'base_price' => $renewalPrice,
                        'gst_percentage' => $gstRate,
                        'gst_amount' => $renewalGstAmount,
                        'total_amount' => $totalRenewalPrice,
                    ];
                    return $arr;
                });

            return response()->json([
                'status' => true,
                'success' => true,
                'data' => $packages,
                'current_plan' => $activePackage ? $activePackage->toPlanPayload() : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new package (admin only)
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'packageName' => 'required|string|max:255',
            'packageDesc' => 'nullable|string',
            'day' => 'required|integer|min:1',
            'msgCount' => 'required|integer|min:1',
            'templateCount' => 'required|integer|min:1',
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
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $package = Package::create($request->all());

            return response()->json([
                'status' => true,
                'message' => 'Package created successfully',
                'data' => $package
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific package
     */
    public function show(Request $request, $id)
    {
        try {
            $package = Package::find($id);

            if (!$package) {
                return response()->json([
                    'status' => false,
                    'message' => 'Package not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $package
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a package (admin only)
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'packageName' => 'sometimes|string|max:255',
            'packageDesc' => 'nullable|string',
            'day' => 'sometimes|integer|min:1',
            'msgCount' => 'sometimes|integer|min:1',
            'templateCount' => 'sometimes|integer|min:1',
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
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $package = Package::find($id);

            if (!$package) {
                return response()->json([
                    'status' => false,
                    'message' => 'Package not found'
                ], 404);
            }

            $package->update($request->all());

            return response()->json([
                'status' => true,
                'message' => 'Package updated successfully',
                'data' => $package
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a package (admin only)
     */
    public function destroy(Request $request, $id)
    {
        try {
            // Check if user is admin
            if ($request->user()->role !== 'admin') {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized. Admin access required.'
                ], 403);
            }

            $package = Package::find($id);

            if (!$package) {
                return response()->json([
                    'status' => false,
                    'message' => 'Package not found'
                ], 404);
            }

            $package->delete();

            return response()->json([
                'status' => true,
                'message' => 'Package deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
