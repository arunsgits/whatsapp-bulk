<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ActivePackage;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Helper to get target user roles array
     */
    private function getTargetRoles(?string $roleInput): array
    {
        if (empty($roleInput) || in_array(strtolower($roleInput), ['admin', 'super_admin', 'all'])) {
            return ['admin', 'administrator', 'super_admin', 'super administrator', 'superadmin'];
        }
        return [strtolower($roleInput)];
    }

    /**
     * Get Overview Statistics
     * GET /api/v1/dashboard/stats or /api/v1/admin/dashboard/stats
     */
    public function getStats(Request $request)
    {
        try {
            $now = Carbon::now();
            $sevenDaysFromNow = $now->copy()->addDays(7);

            // Target Admin Users (role = admin / administrator)
            $totalUsers = User::whereIn('role', ['admin', 'administrator'])->count();
            $activeUsers = User::whereIn('role', ['admin', 'administrator'])->where('status', 'active')->count();
            $inactiveUsers = User::whereIn('role', ['admin', 'administrator'])->where('status', 'inactive')->count();

            // Target Staff Members (role = staff)
            $totalStaff = User::where('role', 'staff')->count();
            $activeStaff = User::where('role', 'staff')->where('status', 'active')->count();
            $inactiveStaff = User::where('role', 'staff')->where('status', 'inactive')->count();

            // Fetch active packages for admin users
            $allUserPackages = ActivePackage::whereHas('user', function ($q) {
                $q->whereIn('role', ['admin', 'administrator']);
            })->with(['user', 'package'])->get();

            $expiringSoonCount = 0;
            $expiredCount = 0;
            $processedUserIds = [];

            foreach ($allUserPackages as $ap) {
                $user = $ap->user;
                if (!$user) continue;

                if (in_array($user->id, $processedUserIds)) {
                    continue;
                }
                $processedUserIds[] = $user->id;

                $endDate = $ap->endDate ? Carbon::parse($ap->endDate) : null;
                $renewalDate = $ap->nextRenewalDate() ?: ($endDate ?: null);

                $isExpired = false;
                $isExpiringSoon = false;

                if ($user->status === 'expired' || $ap->status == 0) {
                    $isExpired = true;
                } elseif ($endDate && $endDate->lt($now)) {
                    $isExpired = true;
                } elseif ($renewalDate && $renewalDate->lt($now)) {
                    $isExpired = true;
                } else {
                    $targetDate = $endDate ?: $renewalDate;
                    if ($targetDate && $targetDate->gte($now) && $targetDate->lte($sevenDaysFromNow)) {
                        $isExpiringSoon = true;
                    }
                }

                if ($isExpired) {
                    $expiredCount++;
                } elseif ($isExpiringSoon) {
                    $expiringSoonCount++;
                }
            }

            // Count users with status 'expired' who do not have an ActivePackage record
            $usersNoPackageExpired = User::whereIn('role', ['admin', 'administrator'])
                ->where('status', 'expired')
                ->whereNotIn('id', $processedUserIds)
                ->count();
            $expiredCount += $usersNoPackageExpired;

            // Total credits assigned & Revenue Calculations across active packages
            $totalCreditsAssigned = 0;
            $allTimeRevenue = 0.0;
            $thisYearRevenue = 0.0;
            $thisMonthRevenue = 0.0;

            $startOfYear = $now->copy()->startOfYear();
            $startOfMonth = $now->copy()->startOfMonth();

            foreach ($allUserPackages as $ap) {
                // Messages Limit
                if (isset($ap->custom_msg_count) && $ap->custom_msg_count !== null && $ap->custom_msg_count > 0) {
                    $totalCreditsAssigned += (int) $ap->custom_msg_count;
                } elseif ($ap->package) {
                    $totalCreditsAssigned += (int) $ap->package->msgCount;
                }

                // Price & Revenue
                $price = (isset($ap->custom_price) && $ap->custom_price !== null)
                    ? (float) $ap->custom_price
                    : ($ap->package ? (float) $ap->package->price : 0.0);

                $allTimeRevenue += $price;

                $createdAt = $ap->created_at ? Carbon::parse($ap->created_at) : null;
                if ($createdAt) {
                    if ($createdAt->gte($startOfYear)) {
                        $thisYearRevenue += $price;
                    }
                    if ($createdAt->gte($startOfMonth)) {
                        $thisMonthRevenue += $price;
                    }
                }
            }

            $activeSubscriptions = ActivePackage::currentlyActive()
                ->whereHas('user', function ($q) {
                    $q->whereIn('role', ['admin', 'administrator']);
                })
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'totalUsers' => $totalUsers,
                    'activeUsers' => $activeUsers,
                    'inactiveUsers' => $inactiveUsers,
                    'expiringSoonUsers' => $expiringSoonCount,
                    'expiredUsers' => $expiredCount,

                    'totalStaff' => $totalStaff,
                    'activeStaff' => $activeStaff,
                    'inactiveStaff' => $inactiveStaff,

                    'totalCreditsAssigned' => (int) $totalCreditsAssigned,
                    'allTimeRevenue' => $allTimeRevenue,
                    'thisYearRevenue' => $thisYearRevenue,
                    'thisMonthRevenue' => $thisMonthRevenue,
                    'activeSubscriptions' => $activeSubscriptions,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching dashboard statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Expiring & Expired Subscriptions Alerts
     * GET /api/v1/dashboard/expiring or /api/v1/admin/dashboard/expiring
     */
    public function getExpiring(Request $request)
    {
        try {
            $now = Carbon::now();
            $sevenDaysFromNow = $now->copy()->addDays(7);
            $targetRoles = $this->getTargetRoles($request->input('role', 'admin'));
            $filter = strtolower($request->input('filter', 'all')); // 'all', 'expired', 'expiring'

            $allUserPackages = ActivePackage::whereHas('user', function ($q) use ($targetRoles) {
                $q->whereIn('role', $targetRoles);
            })->with(['package', 'user'])->orderByDesc('id')->get();

            $alerts = collect();
            $processedUserIds = [];

            foreach ($allUserPackages as $ap) {
                $user = $ap->user;
                if (!$user) continue;

                if (in_array($user->id, $processedUserIds)) {
                    continue;
                }
                $processedUserIds[] = $user->id;

                $endDate = $ap->endDate ? Carbon::parse($ap->endDate) : null;
                $renewalDate = $ap->nextRenewalDate() ?: ($endDate ?: null);

                $isExpired = false;
                $isExpiringSoon = false;
                $daysRemaining = 0;

                if ($user->status === 'expired' || $ap->status == 0) {
                    $isExpired = true;
                    $daysRemaining = $endDate ? (int) ceil($now->diffInDays($endDate, false)) : -1;
                    if ($daysRemaining > 0) {
                        $daysRemaining = 0;
                    }
                } elseif ($endDate && $endDate->lt($now)) {
                    $isExpired = true;
                    $daysRemaining = (int) ceil($now->diffInDays($endDate, false));
                } elseif ($renewalDate && $renewalDate->lt($now)) {
                    $isExpired = true;
                    $daysRemaining = (int) ceil($now->diffInDays($renewalDate, false));
                } else {
                    $targetDate = $endDate ?: $renewalDate;
                    if ($targetDate) {
                        $daysRemaining = (int) ceil($now->diffInDays($targetDate, false));
                        if ($targetDate->gte($now) && $targetDate->lte($sevenDaysFromNow)) {
                            $isExpiringSoon = true;
                        }
                    }
                }

                // If not expired and not expiring within 7 days, skip
                if (!$isExpired && !$isExpiringSoon) {
                    continue;
                }

                $planName = isset($ap->custom_msg_count) && $ap->custom_msg_count > 0 && !$ap->package
                    ? 'Custom Plan'
                    : ($ap->package->packageName ?? 'Standard');

                $msgCount = isset($ap->custom_msg_count) && $ap->custom_msg_count > 0
                    ? (int) $ap->custom_msg_count
                    : ($ap->package->msgCount ?? 0);

                $alerts->push([
                    'id' => 'usr_' . $user->id,
                    'dbId' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->number,
                    'companyName' => $user->companyName ?? '',
                    'plan' => $planName,
                    'status' => $user->status,
                    'packageStatus' => $isExpired ? 0 : $ap->computedStatus(),
                    'packageStatusLabel' => $isExpired ? 'expired' : $ap->statusLabel(),
                    'startDate' => $ap->startDate ? Carbon::parse($ap->startDate)->toIso8601String() : null,
                    'endDate' => $endDate ? $endDate->toIso8601String() : null,
                    'renewalDate' => $renewalDate ? $renewalDate->toIso8601String() : null,
                    'daysRemaining' => $daysRemaining,
                    'isExpired' => $isExpired,
                    'isExpiringSoon' => $isExpiringSoon,
                    'statusLabel' => $isExpired ? 'expired' : 'expiring',
                    'messageCredits' => $msgCount,
                ]);
            }

            // Also check users with status 'expired' who don't have an ActivePackage entry
            $usersNoPackageExpired = User::whereIn('role', $targetRoles)
                ->where('status', 'expired')
                ->whereDoesntHave('activePackage')
                ->get();

            foreach ($usersNoPackageExpired as $user) {
                $alerts->push([
                    'id' => 'usr_' . $user->id,
                    'dbId' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->number,
                    'companyName' => $user->companyName ?? '',
                    'plan' => 'None',
                    'status' => 'expired',
                    'packageStatus' => 0,
                    'startDate' => null,
                    'endDate' => null,
                    'renewalDate' => null,
                    'daysRemaining' => -1,
                    'isExpired' => true,
                    'isExpiringSoon' => false,
                    'statusLabel' => 'expired',
                    'messageCredits' => 0,
                ]);
            }

            // Apply filter parameter if provided ('expired', 'expiring')
            if ($filter === 'expired') {
                $filteredAlerts = $alerts->where('isExpired', true)->values();
            } elseif ($filter === 'expiring' || $filter === 'expiring7d') {
                $filteredAlerts = $alerts->where('isExpiringSoon', true)->values();
            } else {
                $filteredAlerts = $alerts->values();
            }

            return response()->json([
                'success' => true,
                'data' => $filteredAlerts,
                'meta' => [
                    'totalAlerts' => $alerts->count(),
                    'expiredCount' => $alerts->where('isExpired', true)->count(),
                    'expiringCount' => $alerts->where('isExpiringSoon', true)->count(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching expiring subscriptions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Revenue Statistics
     * GET /api/v1/dashboard/revenue or /api/v1/admin/dashboard/revenue
     */
    public function getRevenue(Request $request)
    {
        try {
            $now = Carbon::now();
            $targetRoles = $this->getTargetRoles($request->input('role', 'admin'));

            $startOfYear = $now->copy()->startOfYear();
            $startOfMonth = $now->copy()->startOfMonth();

            $allTimeRevenue = (float) ActivePackage::whereHas('user', function ($q) use ($targetRoles) {
                    $q->whereIn('role', $targetRoles);
                })
                ->join('packages', 'active_packages.packageId', '=', 'packages.id')
                ->sum('packages.price');

            $thisYearRevenue = (float) ActivePackage::whereHas('user', function ($q) use ($targetRoles) {
                    $q->whereIn('role', $targetRoles);
                })
                ->join('packages', 'active_packages.packageId', '=', 'packages.id')
                ->where('active_packages.created_at', '>=', $startOfYear)
                ->sum('packages.price');

            $thisMonthRevenue = (float) ActivePackage::whereHas('user', function ($q) use ($targetRoles) {
                    $q->whereIn('role', $targetRoles);
                })
                ->join('packages', 'active_packages.packageId', '=', 'packages.id')
                ->where('active_packages.created_at', '>=', $startOfMonth)
                ->sum('packages.price');

            $activeSubscriptions = ActivePackage::currentlyActive()
                ->whereHas('user', function ($q) use ($targetRoles) {
                    $q->whereIn('role', $targetRoles);
                })
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'allTimeRevenue' => $allTimeRevenue,
                    'thisYearRevenue' => $thisYearRevenue,
                    'thisMonthRevenue' => $thisMonthRevenue,
                    'activeSubscriptions' => $activeSubscriptions,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching revenue statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
