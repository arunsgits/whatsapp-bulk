<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Credit;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CreditController extends Controller
{
    /**
     * Get all credits for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $query = Credit::where('userId', $userId);
            if ($request->filled('user_id')) {
                $query = Credit::where('userId', $request->query('user_id'));
            } elseif ($request->boolean('global', false) || $request->boolean('all', false)) {
                $query = Credit::query();
            }

            $credits = $query->with('user')
                ->orderBy('created_at', 'desc')
                ->paginate(15);

            return response()->json([
                'status' => true,
                'data' => $credits
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new credit entry
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'userId' => 'sometimes|exists:users,id',
            'amount' => 'required|numeric',
            'type' => 'required|string|in:purchased,earned,bonus,refund,used,expired',
            'description' => 'nullable|string',
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

            $credit = Credit::create([
                'userId' => $targetUserId,
                'amount' => $request->amount,
                'type' => $request->type,
                'description' => $request->description,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Credit added successfully',
                'data' => $credit->load('user')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific credit
     */
    public function show(Request $request, $id)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $query = Credit::where('userId', $userId);
            if ($request->filled('user_id')) {
                $query = Credit::where('userId', $request->query('user_id'));
            } elseif ($request->boolean('global', false) || $request->boolean('all', false)) {
                $query = Credit::query();
            }
            $credit = $query->with('user')->find($id);

            if (!$credit) {
                return response()->json([
                    'status' => false,
                    'message' => 'Credit not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $credit
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update a credit
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'sometimes|numeric',
            'type' => 'sometimes|string|in:purchased,earned,bonus,refund,used,expired',
            'description' => 'nullable|string',
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

            $query = $isAdmin ? Credit::query() : Credit::where('userId', $userId);
            $credit = $query->find($id);

            if (!$credit) {
                return response()->json([
                    'status' => false,
                    'message' => 'Credit not found'
                ], 404);
            }

            $credit->update($request->only(['balance', 'history', 'payments']));

            return response()->json([
                'status' => true,
                'message' => 'Credit updated successfully',
                'data' => $credit->load('user')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a credit
     */
    public function destroy(Request $request, $id)
    {
        try {
            $userId = $request->user()->getCompanyId();
            $isAdmin = $request->user()->role === 'admin';

            $query = $isAdmin ? Credit::query() : Credit::where('userId', $userId);
            $credit = $query->find($id);

            if (!$credit) {
                return response()->json([
                    'status' => false,
                    'message' => 'Credit not found'
                ], 404);
            }

            $credit->delete();

            return response()->json([
                'status' => true,
                'message' => 'Credit deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user's credit balance
     */
    public function getBalance(Request $request, $userId = null)
    {
        try {
            $wallet = app(WalletService::class);
            $authUser = $request->user();
            $isAdmin = $authUser->role === 'admin' || $authUser->isSuperAdmin();
            $target = $authUser;

            if ($userId && $isAdmin) {
                $target = \App\Models\User::find($userId) ?: $authUser;
            }

            if (!$isAdmin && $userId && (int) $userId !== (int) $authUser->getCompanyId()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $summary = $wallet->summary($target);

            return response()->json([
                'status' => true,
                'data' => [
                    'total_credits' => $summary['balance'],
                    'balance' => $summary['balance'],
                    'walletEnabled' => $summary['walletEnabled'],
                    'currency' => 'INR',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Razorpay payment order for wallet recharge
     */
    public function createOrder(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|min:1',
            'currency' => 'sometimes|string|in:INR,USD',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
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

            $order = app(WalletService::class)->createRazorpayOrder(
                $request->user(),
                (float) $request->amount,
                $request->input('currency', 'INR'),
                $options
            );

            return response()->json([
                'status' => true,
                'message' => 'Order created successfully',
                'data' => $order
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Verify Razorpay payment and credit this user's wallet
     */
    public function verifyPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required_without:razorpay_order_id|string',
            'razorpay_order_id' => 'required_without:order_id|string',
            'payment_id' => 'required_without:razorpay_payment_id|string',
            'razorpay_payment_id' => 'required_without:payment_id|string',
            'signature' => 'required_without:razorpay_signature|string',
            'razorpay_signature' => 'required_without:signature|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $result = app(WalletService::class)->verifyAndCredit(
                $request->user(),
                $request->input('razorpay_order_id', $request->input('order_id')),
                $request->input('razorpay_payment_id', $request->input('payment_id')),
                $request->input('razorpay_signature', $request->input('signature'))
            );

            return response()->json([
                'status' => true,
                'message' => $result['already_credited']
                    ? 'Payment already verified'
                    : 'Payment verified successfully',
                'data' => [
                    'payment_id' => $result['payment_id'],
                    'credits_added' => $result['amount'],
                    'amount_added' => $result['amount'],
                    'balance' => $result['balance'],
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Deduct credits
     */
    public function deductCredits(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'reference_id' => 'nullable|string',
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
            $amount = $request->amount;

            // Check if user has sufficient credits
            $currentBalance = Credit::where('userId', $userId)
                ->where('type', 'purchased')
                ->sum('amount') -
                Credit::where('userId', $userId)
                ->where('type', 'deduct')
                ->sum('amount');

            if ($currentBalance < $amount) {
                return response()->json([
                    'status' => false,
                    'message' => 'Insufficient credits'
                ], 400);
            }

            Credit::create([
                'userId' => $userId,
                'amount' => $amount,
                'type' => 'deduct',
                'description' => $request->description ?? 'Credits deducted',
                'reference_id' => $request->reference_id
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Credits deducted successfully',
                'data' => [
                    'amount_deducted' => $amount,
                    'remaining_balance' => $currentBalance - $amount
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get credit history
     */
    public function getHistory(Request $request)
    {
        try {
            return app(WalletController::class)->history($request);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get credit report
     */
    public function creditReport(Request $request)
    {
        try {
            $wallet = app(WalletService::class);
            $owner = $wallet->ownerUser($request->user());
            $rows = \App\Models\WalletTransaction::where('userId', $owner->id)->get();

            $credited = $rows->where('type', 'credit')->sum('amount');
            $debited = $rows->where('type', 'debit')->sum('amount');

            return response()->json([
                'status' => true,
                'data' => [
                    'total_purchased' => (float) $credited,
                    'total_deducted' => (float) $debited,
                    'current_balance' => $wallet->getBalance($request->user()),
                    'walletEnabled' => $wallet->isEnabled($request->user()),
                    'transactions_count' => $rows->count(),
                    'transactions' => $rows->groupBy('source'),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
