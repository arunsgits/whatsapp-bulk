<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminWalletController extends Controller
{
    /**
     * List all wallet transactions (all users)
     * GET /api/v1/admin/wallet/transactions
     *
     * Query: page, limit, user_id, type (credit|debit), source (razorpay|admin|usage|refund),
     *        date_from, date_to, search
     */
    public function transactions(Request $request)
    {
        try {
            app(WalletService::class)->ensureSchema();

            $page = max(1, (int) $request->input('page', 1));
            $limit = min(100, max(1, (int) $request->input('limit', 20)));
            $userId = $request->input('user_id', $request->input('userId'));
            $type = $request->input('type');
            $source = $request->input('source');
            $dateFrom = $request->input('date_from', $request->input('dateFrom', $request->input('from_date', $request->input('startDate'))));
            $dateTo = $request->input('date_to', $request->input('dateTo', $request->input('to_date', $request->input('endDate'))));
            $singleDate = $request->input('date');
            $search = $request->input('search', $request->input('q'));
            $category = $request->input('category', $request->input('categories', $request->input('template_category')));

            $query = WalletTransaction::query()->with([
                'user:id,name,email,number,companyName,wallet_enabled,wallet_balance',
                'order.package'
            ]);

            if ($userId) {
                $rawId = str_replace('usr_', '', (string) $userId);
                $query->where('userId', $rawId);
            }

            // Transaction Type filter (wallet vs plan vs all)
            $txnTypeFilter = strtolower(trim((string) (
                $request->input('transaction_type') ??
                $request->input('transactionType') ??
                $request->input('scope') ??
                $request->input('item_type') ??
                ''
            )));

            if ($txnTypeFilter === 'plan' || strtolower((string)$type) === 'plan' || strtolower((string)$category) === 'plan' || $request->boolean('is_plan', false)) {
                $query->where(function ($q) {
                    $q->where('description', 'like', '%Plan renewal%')
                      ->orWhere('description', 'like', '%Plan subscription%')
                      ->orWhere('description', 'like', '%Plan upgrade%')
                      ->orWhere('description', 'like', '%Custom plan%')
                      ->orWhere('category', 'PLAN')
                      ->orWhereHas('order', function ($oq) {
                          $oq->whereNotNull('package_id')
                             ->orWhereIn('payment_type', ['plan', 'subscription', 'upgrade', 'renewal', 'custom_plan']);
                      });
                });
            } elseif ($txnTypeFilter === 'wallet' || strtolower((string)$type) === 'wallet' || $request->has('wallet_only') || ($request->has('is_plan') && !$request->boolean('is_plan'))) {
                $query->where(function ($q) {
                    $q->where(function ($sub) {
                        $sub->whereNull('description')
                            ->orWhere(function ($d) {
                                $d->where('description', 'not like', '%Plan renewal%')
                                  ->where('description', 'not like', '%Plan subscription%')
                                  ->where('description', 'not like', '%Plan upgrade%')
                                  ->where('description', 'not like', '%Custom plan%');
                            });
                    })
                    ->where(function ($sub) {
                        $sub->whereNull('category')
                            ->orWhere('category', '!=', 'PLAN');
                    })
                    ->where(function ($sub) {
                        $sub->whereNull('wallet_order_id')
                            ->orWhereDoesntHave('order', function ($oq) {
                                $oq->whereNotNull('package_id')
                                   ->orWhereIn('payment_type', ['plan', 'subscription', 'upgrade', 'renewal', 'custom_plan']);
                            });
                    });
                });
            }

            // Category filter (Marketing, Utility, Authentication)
            if ($category) {
                $rawCategories = is_array($category) ? $category : explode(',', (string) $category);
                $normalizedCategories = [];
                foreach ($rawCategories as $cat) {
                    $c = strtoupper(trim((string) $cat));
                    if ($c === '' || $c === 'ALL') {
                        continue;
                    }
                    if (str_contains($c, 'MARKET')) {
                        $normalizedCategories[] = 'MARKETING';
                    } elseif (str_contains($c, 'UTIL')) {
                        $normalizedCategories[] = 'UTILITY';
                    } elseif (str_contains($c, 'AUTH')) {
                        $normalizedCategories[] = 'AUTHENTICATION';
                    } else {
                        $normalizedCategories[] = $c;
                    }
                }
                $normalizedCategories = array_values(array_unique($normalizedCategories));

                if (!empty($normalizedCategories)) {
                    $hasCategoryCol = \Illuminate\Support\Facades\Schema::hasColumn('wallet_transactions', 'category');
                    $query->where(function ($q) use ($normalizedCategories, $hasCategoryCol) {
                        foreach ($normalizedCategories as $idx => $cat) {
                            $matcher = function ($sub) use ($cat, $hasCategoryCol) {
                                if ($hasCategoryCol) {
                                    $sub->where('category', $cat)
                                        ->orWhere('description', 'like', "%{$cat}%");
                                } else {
                                    $sub->where('description', 'like', "%{$cat}%");
                                }
                            };
                            if ($idx === 0) {
                                $q->where($matcher);
                            } else {
                                $q->orWhere($matcher);
                            }
                        }
                    });
                }
            }

            if ($type && in_array(strtolower($type), ['credit', 'debit'], true)) {
                $query->where('type', strtolower($type));
            } elseif ($type && in_array(strtolower($type), ['marketing', 'utility', 'authentication'], true) && !$category) {
                $cat = strtoupper($type);
                $query->where(function ($q) use ($cat) {
                    $q->where('category', $cat)->orWhere('description', 'like', "%{$cat}%");
                });
            }

            if ($source) {
                $query->where('source', strtolower($source));
            }

            if ($singleDate && !$dateFrom && !$dateTo) {
                try {
                    $parsed = Carbon::parse($singleDate);
                    $query->where('created_at', '>=', $parsed->copy()->startOfDay())
                        ->where('created_at', '<=', $parsed->copy()->endOfDay());
                } catch (\Throwable $e) {
                }
            } else {
                if ($dateFrom) {
                    try {
                        $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                    } catch (\Throwable $e) {
                    }
                }

                if ($dateTo) {
                    try {
                        $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                    } catch (\Throwable $e) {
                    }
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('reference_id', 'like', "%{$search}%")
                        ->orWhere('source', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('companyName', 'like', "%{$search}%")
                                ->orWhere('number', 'like', "%{$search}%");
                        });
                });
            }

            $total = (clone $query)->count();
            $totalPages = (int) max(1, ceil($total / $limit));

            $sumCredit = (clone $query)->where('type', 'credit')->sum('amount');
            $sumDebit = (clone $query)->where('type', 'debit')->sum('amount');

            $rows = $query->orderByDesc('id')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get()
                ->map(function (WalletTransaction $txn) {
                    $user = $txn->user;
                    $order = $txn->order;

                    $isPlan = false;
                    $descLower = strtolower($txn->description ?? '');
                    if (str_contains($descLower, 'plan renewal') || str_contains($descLower, 'plan subscription') || strtoupper($txn->category ?? '') === 'PLAN') {
                        $isPlan = true;
                    } elseif ($order && (!empty($order->package_id) || ($order->payment_type ?? '') === 'plan')) {
                        $isPlan = true;
                    }

                    $packageName = null;
                    if ($order && $order->package) {
                        $packageName = $order->package->packageName;
                    } elseif ($isPlan && preg_match('/Plan renewal.*?\(Paid/i', $txn->description ?? '')) {
                        $packageName = 'Plan Renewal';
                    }

                    return [
                        'id' => $txn->id,
                        'transactionId' => 'TXN-' . $txn->id,
                        'transaction_type' => $isPlan ? 'plan' : 'wallet',
                        'is_plan' => $isPlan,
                        'is_wallet' => !$isPlan,
                        'userId' => $txn->userId,
                        'user' => $user ? [
                            'id' => 'usr_' . $user->id,
                            'dbId' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email,
                            'phone' => $user->number,
                            'companyName' => $user->companyName ?? '',
                            'walletEnabled' => (bool) ($user->wallet_enabled ?? false),
                            'walletBalance' => round((float) ($user->wallet_balance ?? 0), 2),
                        ] : null,
                        'type' => $txn->type,
                        'source' => $txn->source,
                        'category' => $txn->category ?: ($isPlan ? 'PLAN' : null),
                        'amount' => round((float) $txn->amount, 2),
                        'balanceAfter' => round((float) $txn->balance_after, 2),
                        'balance_after' => round((float) $txn->balance_after, 2),
                        'referenceId' => $txn->reference_id,
                        'reference_id' => $txn->reference_id,
                        'walletOrderId' => $txn->wallet_order_id,
                        'package_name' => $packageName,
                        'description' => $txn->description,
                        'createdAt' => $txn->created_at ? $txn->created_at->toIso8601String() : null,
                        'created_at' => $txn->created_at ? $txn->created_at->toIso8601String() : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $rows,
                'summary' => [
                    'totalCredit' => round((float) $sumCredit, 2),
                    'totalDebit' => round((float) $sumDebit, 2),
                    'net' => round((float) $sumCredit - (float) $sumDebit, 2),
                    'currency' => 'INR',
                ],
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => $totalPages,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching wallet transactions: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Wallet overview across all users
     * GET /api/v1/admin/wallet/summary
     */
    public function summary(Request $request)
    {
        try {
            $wallet = app(WalletService::class);
            $wallet->ensureSchema();

            $enabledUsers = User::where('wallet_enabled', true)->count();
            $totalBalance = (float) User::where('wallet_enabled', true)->sum('wallet_balance');

            $credits = (float) WalletTransaction::where('type', 'credit')->sum('amount');
            $debits = (float) WalletTransaction::where('type', 'debit')->sum('amount');
            $recharges = (float) WalletTransaction::where('type', 'credit')->where('source', 'razorpay')->sum('amount');
            $usage = (float) WalletTransaction::where('type', 'debit')->where('source', 'usage')->sum('amount');
            $adminCredits = (float) WalletTransaction::where('source', 'admin')->where('type', 'credit')->sum('amount');

            return response()->json([
                'success' => true,
                'data' => [
                    'walletEnabledUsers' => $enabledUsers,
                    'totalWalletBalance' => round($totalBalance, 2),
                    'totalCredits' => round($credits, 2),
                    'totalDebits' => round($debits, 2),
                    'totalRecharges' => round($recharges, 2),
                    'totalUsage' => round($usage, 2),
                    'totalAdminCredits' => round($adminCredits, 2),
                    'currency' => 'INR',
                    'pricing' => $wallet->pricingConfig(),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching wallet summary: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List users with wallet enabled + balances
     * GET /api/v1/admin/wallet/users
     */
    public function users(Request $request)
    {
        try {
            app(WalletService::class)->ensureSchema();

            $page = max(1, (int) $request->input('page', 1));
            $limit = min(100, max(1, (int) $request->input('limit', 20)));
            $search = $request->input('search');
            $onlyEnabled = $request->boolean('walletEnabled', $request->boolean('wallet_enabled', false));

            $query = User::query()->where('role', 'admin');

            if ($onlyEnabled || $request->has('walletEnabled') || $request->has('wallet_enabled')) {
                if ($request->has('walletEnabled') || $request->has('wallet_enabled')) {
                    $enabled = filter_var(
                        $request->input('walletEnabled', $request->input('wallet_enabled')),
                        FILTER_VALIDATE_BOOLEAN
                    );
                    $query->where('wallet_enabled', $enabled);
                }
            }

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('companyName', 'like', "%{$search}%")
                        ->orWhere('number', 'like', "%{$search}%");
                });
            }

            $total = $query->count();
            $users = $query->orderByDesc('wallet_balance')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get()
                ->map(function (User $user) {
                    return [
                        'id' => 'usr_' . $user->id,
                        'dbId' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->number,
                        'companyName' => $user->companyName ?? '',
                        'walletEnabled' => (bool) ($user->wallet_enabled ?? false),
                        'walletBalance' => round((float) ($user->wallet_balance ?? 0), 2),
                        'status' => $user->status,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $users,
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => (int) max(1, ceil($total / $limit)),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching wallet users: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Super admin manually credit or debit wallet of a user.
     * POST /api/v1/admin/wallet/credit
     * POST /api/v1/admin/wallet/adjust
     * POST /api/v1/admin/wallet/users/{userId}/credit
     */
    public function credit(Request $request, $userId = null)
    {
        $targetUserId = $userId ?: $request->input('userId', $request->input('user_id'));
        if (!$targetUserId) {
            return response()->json([
                'success' => false,
                'message' => 'userId is required',
            ], 400);
        }

        $rawId = str_replace('usr_', '', (string) $targetUserId);
        $user = User::find($rawId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        $amount = (float) $request->input('amount', 0);
        if ($amount == 0) {
            return response()->json([
                'success' => false,
                'message' => 'Amount must be non-zero (positive for credit, negative for debit)',
            ], 400);
        }

        $type = strtolower((string) $request->input('type', ''));
        if ($type === 'debit' && $amount > 0) {
            $amount = -$amount;
        } elseif ($type === 'credit' && $amount < 0) {
            $amount = abs($amount);
        }

        $description = trim((string) $request->input('description', 'Manual adjustment by Super Admin'));

        try {
            $wallet = app(WalletService::class);
            $result = $wallet->adminCredit($user, $amount, $description);

            return response()->json([
                'success' => true,
                'message' => $amount >= 0 ? 'Wallet credited successfully' : 'Wallet debited successfully',
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'user' => [
                        'id' => 'usr_' . $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                    'balance' => $result['balance'],
                    'transaction' => $result['transaction'],
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error adjusting wallet: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get specific user's wallet details and transaction history.
     * GET /api/v1/admin/wallet/users/{userId}
     */
    public function userWallet(Request $request, $userId)
    {
        try {
            app(WalletService::class)->ensureSchema();
            $rawId = str_replace('usr_', '', (string) $userId);
            $user = User::find($rawId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $page = max(1, (int) $request->input('page', 1));
            $limit = min(100, max(1, (int) $request->input('limit', 20)));
            $onlyManual = $request->boolean('manual_only', false);

            $query = WalletTransaction::where('userId', $user->id);
            if ($onlyManual) {
                $query->where('source', 'admin');
            }

            $total = (clone $query)->count();
            $adminCredits = (clone $query)->where('source', 'admin')->where('type', 'credit')->sum('amount');
            $adminDebits = (clone $query)->where('source', 'admin')->where('type', 'debit')->sum('amount');

            $txns = $query->orderByDesc('id')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get()
                ->map(function ($txn) {
                    return [
                        'id' => $txn->id,
                        'transactionId' => 'TXN-' . $txn->id,
                        'type' => $txn->type,
                        'source' => $txn->source,
                        'is_manual_admin' => $txn->source === 'admin',
                        'amount' => round((float) $txn->amount, 2),
                        'balance_after' => round((float) $txn->balance_after, 2),
                        'description' => $txn->description,
                        'created_at' => $txn->created_at ? $txn->created_at->toIso8601String() : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->number,
                    'walletEnabled' => (bool) ($user->wallet_enabled ?? false),
                    'walletBalance' => round((float) ($user->wallet_balance ?? 0), 2),
                    'manualSummary' => [
                        'totalAdminCredit' => round((float) $adminCredits, 2),
                        'totalAdminDebit' => round((float) $adminDebits, 2),
                        'netAdminAdjustment' => round((float) $adminCredits - (float) $adminDebits, 2),
                    ],
                    'transactions' => $txns,
                ],
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => (int) max(1, ceil($total / $limit)),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user wallet: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Enable or disable wallet for a specific user.
     * POST/PUT /api/v1/admin/wallet/users/{userId}/toggle
     */
    public function toggle(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', (string) $userId);
            $user = User::find($rawId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $enabled = filter_var(
                $request->input('walletEnabled', $request->input('wallet_enabled', true)),
                FILTER_VALIDATE_BOOLEAN
            );

            app(WalletService::class)->setEnabled($user, $enabled);

            return response()->json([
                'success' => true,
                'message' => $enabled ? 'Wallet enabled for this user' : 'Wallet disabled for this user',
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'walletEnabled' => $enabled,
                    'balance' => round((float) $user->wallet_balance, 2),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error toggling wallet: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all manual wallet adjustments made by Super Admin across all users.
     * GET /api/v1/admin/wallet/manual-history
     */
    public function manualHistory(Request $request)
    {
        $request->merge(['source' => 'admin']);
        return $this->transactions($request);
    }
}
