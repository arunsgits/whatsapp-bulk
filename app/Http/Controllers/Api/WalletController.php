<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class WalletController extends Controller
{
    public function __construct(protected WalletService $wallet)
    {
    }

    public function show(Request $request)
    {
        $summary = $this->wallet->summary($request->user());

        return response()->json([
            'status' => true,
            'success' => true,
            'data' => $summary,
        ]);
    }

    public function history(Request $request)
    {
        try {
            $this->wallet->ensureSchema();
            $owner = $this->wallet->ownerUser($request->user());

            $page = max(1, (int) $request->input('page', 1));
            $limit = min(100, max(1, (int) $request->input('limit', $request->input('per_page', 50))));

            $query = WalletTransaction::with('order.package')->where('userId', $owner->id);

            $typeInput = strtolower(trim((string) $request->input('type', '')));
            $sourceInput = strtolower(trim((string) $request->input('source', '')));
            $categoryInput = $request->input('category', $request->input('categories', $request->input('template_category')));
            if (!$categoryInput && in_array($typeInput, ['marketing', 'utility', 'authentication'], true)) {
                $categoryInput = $typeInput;
            }

            // Transaction Type filter (wallet vs plan vs all)
            $txnTypeFilter = strtolower(trim((string) (
                $request->input('transaction_type') ??
                $request->input('transactionType') ??
                $request->input('scope') ??
                $request->input('item_type') ??
                ''
            )));

            if ($txnTypeFilter === 'plan' || $typeInput === 'plan' || strtolower((string)$categoryInput) === 'plan' || $request->boolean('is_plan', false)) {
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
            } elseif ($txnTypeFilter === 'wallet' || $typeInput === 'wallet' || $request->has('wallet_only') || ($request->has('is_plan') && !$request->boolean('is_plan'))) {
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

            // 1. Category Filter (Marketing, Utility, Authentication)
            $normalizedCategories = [];
            if ($categoryInput) {
                $rawCategories = is_array($categoryInput) ? $categoryInput : explode(',', (string) $categoryInput);
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
            }

            if (!empty($normalizedCategories)) {
                $hasCategoryCol = Schema::hasColumn('wallet_transactions', 'category');
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

            // 2. Type & Source filter (if credit/debit or source specified)
            if (in_array($typeInput, ['credit', 'debit'], true)) {
                $query->where('type', $typeInput);
            }

            if ($sourceInput !== '' && $sourceInput !== 'all') {
                $query->where('source', $sourceInput);
            }

            // 3. Search Filter
            $search = trim((string) $request->input('search', $request->input('q', $request->input('query', $request->input('keyword', '')))));
            if ($search !== '') {
                $hasCategoryCol = Schema::hasColumn('wallet_transactions', 'category');
                $query->where(function ($q) use ($search, $hasCategoryCol) {
                    $q->where('description', 'like', "%{$search}%")
                        ->orWhere('reference_id', 'like', "%{$search}%")
                        ->orWhere('source', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%");

                    if ($hasCategoryCol) {
                        $q->orWhere('category', 'like', "%{$search}%");
                    }

                    if (is_numeric($search)) {
                        $q->orWhere('amount', '=', (float) $search);
                    }
                });
            }

            // 4. Date Wise Filter
            $dateFromInput = $request->input('date_from', $request->input('from_date', $request->input('startDate', $request->input('start_date', $request->input('dateFrom', $request->input('from'))))));
            $dateToInput = $request->input('date_to', $request->input('to_date', $request->input('endDate', $request->input('end_date', $request->input('dateTo', $request->input('to'))))));
            $singleDateInput = $request->input('date');

            if ($singleDateInput && !$dateFromInput && !$dateToInput) {
                try {
                    $parsed = Carbon::parse($singleDateInput);
                    $query->where('created_at', '>=', $parsed->copy()->startOfDay())
                        ->where('created_at', '<=', $parsed->copy()->endOfDay());
                } catch (\Throwable $e) {
                    // Silently ignore invalid date format
                }
            } else {
                if ($dateFromInput) {
                    try {
                        $query->where('created_at', '>=', Carbon::parse($dateFromInput)->startOfDay());
                    } catch (\Throwable $e) {
                        // Silently ignore invalid date format
                    }
                }
                if ($dateToInput) {
                    try {
                        $query->where('created_at', '<=', Carbon::parse($dateToInput)->endOfDay());
                    } catch (\Throwable $e) {
                        // Silently ignore invalid date format
                    }
                }
            }

            // Counts and pagination
            $total = (clone $query)->count();
            $totalPages = (int) max(1, ceil($total / $limit));

            $sumCredit = (clone $query)->where('type', 'credit')->sum('amount');
            $sumDebit = (clone $query)->where('type', 'debit')->sum('amount');

            $rows = $query->orderByDesc('id')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get()
                ->map(function ($txn) {
                    $order = $txn->order;
                    $isPlan = false;
                    $descLower = strtolower($txn->description ?? '');
                    if (str_contains($descLower, 'plan renewal') || str_contains($descLower, 'plan subscription') || strtoupper($txn->category ?? '') === 'PLAN') {
                        $isPlan = true;
                    } elseif ($order && (!empty($order->package_id) || ($order->payment_type ?? '') === 'plan')) {
                        $isPlan = true;
                    }

                    $arr = $txn->toArray();
                    $arr['transactionId'] = 'TXN-' . $txn->id;
                    $arr['transaction_type'] = $isPlan ? 'plan' : 'wallet';
                    $arr['is_plan'] = $isPlan;
                    $arr['is_wallet'] = !$isPlan;
                    $arr['package_name'] = $order && $order->package ? $order->package->packageName : ($isPlan ? 'Plan Renewal' : null);
                    return $arr;
                });

            return response()->json([
                'status' => true,
                'success' => true,
                'data' => $rows,
                'wallet' => $this->wallet->summary($request->user()),
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => $totalPages,
                    'has_more' => ($page * $limit) < $total,
                ],
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => $totalPages,
                ],
                'summary' => [
                    'totalCredit' => round((float) $sumCredit, 2),
                    'totalDebit' => round((float) $sumDebit, 2),
                    'net' => round((float) $sumCredit - (float) $sumDebit, 2),
                    'currency' => 'INR',
                ],
                'filters' => [
                    'category' => !empty($normalizedCategories) ? $normalizedCategories : null,
                    'search' => $search !== '' ? $search : null,
                    'date_from' => $dateFromInput ?: ($singleDateInput ?: null),
                    'date_to' => $dateToInput ?: ($singleDateInput ?: null),
                    'type' => in_array($typeInput, ['credit', 'debit'], true) ? $typeInput : null,
                    'source' => ($sourceInput !== '' && $sourceInput !== 'all') ? $sourceInput : null,
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Error retrieving wallet history: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'currency' => 'sometimes|string|in:INR',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $options = [];
            $packageId = $request->input('package_id', $request->input('packageId', $request->input('plan_id', $request->input('planId'))));
            if ($packageId) {
                $options['package_id'] = is_numeric($packageId) ? (int) $packageId : (int) str_replace('plan_', '', (string) $packageId);
                $options['purpose'] = 'plan_purchase';
            }
            if ($request->has('tax_inclusive') || $request->has('taxInclusive')) {
                $options['tax_inclusive'] = $request->boolean('tax_inclusive', $request->boolean('taxInclusive'));
            }

            $order = $this->wallet->createRazorpayOrder(
                $request->user(),
                (float) $request->amount,
                $request->input('currency', 'INR'),
                $options
            );

            return response()->json([
                'status' => true,
                'success' => true,
                'message' => 'Order created successfully',
                'data' => $order,
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function verify(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'razorpay_order_id' => 'required_without:order_id|string',
            'order_id' => 'required_without:razorpay_order_id|string',
            'razorpay_payment_id' => 'required_without:payment_id|string',
            'payment_id' => 'required_without:razorpay_payment_id|string',
            'razorpay_signature' => 'required_without:signature|string',
            'signature' => 'required_without:razorpay_signature|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $result = $this->wallet->verifyAndCredit(
                $request->user(),
                $request->input('razorpay_order_id', $request->input('order_id')),
                $request->input('razorpay_payment_id', $request->input('payment_id')),
                $request->input('razorpay_signature', $request->input('signature'))
            );

            return response()->json([
                'status' => true,
                'success' => true,
                'message' => $result['already_credited']
                    ? 'Payment already verified'
                    : 'Payment verified and wallet credited',
                'data' => [
                    'payment_id' => $result['payment_id'],
                    'amount_added' => $result['amount'],
                    'balance' => $result['balance'],
                    'wallet' => $this->wallet->summary($request->user()),
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
