<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class UserPackageHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'userId',
        'packageId',
        'assignedBy',
        'action',
        'packageName',
        'price',
        'msgCount',
        'msgPrice',
        'contactCount',
        'contactPrice',
        'templateCount',
        'templatePrice',
        'startDate',
        'endDate',
        'day',
        'notes',
        'notified_6day',
    ];

    protected $casts = [
        'startDate' => 'datetime',
        'endDate' => 'datetime',
        'price' => 'float',
        'msgPrice' => 'float',
        'contactPrice' => 'float',
        'templatePrice' => 'float',
    ];

    /**
     * Get the user for whom the package was assigned.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'userId');
    }

    /**
     * Get the package associated with this history record.
     */
    public function package()
    {
        return $this->belongsTo(Package::class, 'packageId');
    }

    /**
     * Get the super admin user who assigned this package.
     */
    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assignedBy');
    }

    /**
     * Reset 6-day notification flag when a user renews their plan.
     * Call this whenever a new plan entry is created for the user.
     */
    public static function resetNotificationForUser(int $userId): void
    {
        static::where('userId', $userId)
            ->where('notified_6day', true)
            ->update(['notified_6day' => false]);
    }

    /**
     * Create a new history entry ONLY if an identical entry does not already exist.
     * Prevents duplicate history records caused by:
     *  - Rapid double-clicks / UI double-submissions
     *  - Network retries / idempotency replays
     *  - Webhook + Client callback concurrent execution
     *  - Exact duplicate subscription period logs
     *
     * @param array $attributes
     * @param int $debounceSeconds (default: 60s)
     * @return UserPackageHistory
     */
    public static function recordOnce(array $attributes, int $debounceSeconds = 60): UserPackageHistory
    {
        $userId = $attributes['userId'] ?? null;
        $packageId = $attributes['packageId'] ?? null;
        $action = $attributes['action'] ?? null;
        $notes = $attributes['notes'] ?? '';

        if (!$userId) {
            return static::create($attributes);
        }

        // 1. Order ID deduplication: If notes references an order (e.g. "Order #123"), check if already logged
        if (preg_match('/Order\s*#(\d+)/i', (string) $notes, $orderMatches)) {
            $orderId = $orderMatches[1];
            $existingOrderLog = static::where('userId', $userId)
                ->where('notes', 'LIKE', "%Order #{$orderId}%")
                ->latest('id')
                ->first();

            if ($existingOrderLog) {
                Log::info("UserPackageHistory::recordOnce: Deduplicated by Order #{$orderId} for userId={$userId}");
                return $existingOrderLog;
            }
        }

        // 2. Exact period deduplication: Same user, package, action, and identical startDate + endDate
        if (!empty($attributes['startDate']) && !empty($attributes['endDate'])) {
            try {
                $start = Carbon::parse($attributes['startDate'])->startOfMinute();
                $end = Carbon::parse($attributes['endDate'])->startOfMinute();

                $existingPeriod = static::where('userId', $userId)
                    ->when($packageId, fn($q) => $q->where('packageId', $packageId))
                    ->when($action, fn($q) => $q->where('action', $action))
                    ->whereBetween('startDate', [$start->copy()->subMinutes(2), $start->copy()->addMinutes(2)])
                    ->whereBetween('endDate', [$end->copy()->subMinutes(2), $end->copy()->addMinutes(2)])
                    ->latest('id')
                    ->first();

                if ($existingPeriod) {
                    Log::info("UserPackageHistory::recordOnce: Deduplicated identical subscription period (userId={$userId}, action={$action}, packageId={$packageId})");
                    return $existingPeriod;
                }
            } catch (\Throwable $e) {
                // If date parsing fails, fall through to rapid debounce check
            }
        }

        // 3. Debounce rapid identical submissions: If same user, action, and package within debounceSeconds window
        $recentDuplicate = static::where('userId', $userId)
            ->when($action, fn($q) => $q->where('action', $action))
            ->when($packageId, fn($q) => $q->where('packageId', $packageId))
            ->where('created_at', '>=', now()->subSeconds($debounceSeconds))
            ->latest('id')
            ->first();

        if ($recentDuplicate) {
            Log::info("UserPackageHistory::recordOnce: Debounced duplicate submission within {$debounceSeconds}s (userId={$userId}, action={$action})");
            return $recentDuplicate;
        }

        // 4. Safely create the record
        return static::create($attributes);
    }
}
