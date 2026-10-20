<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class CheckStaffPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $modulePermission
     */
    public function handle(Request $request, Closure $next, string $modulePermission = '*'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        // 1. Verify user account status is active
        if ($user->status !== 'active') {
            return response()->json([
                'status' => false,
                'message' => 'Your account is inactive. Please contact support.'
            ], 403);
        }

        // 2. If staff member, verify parent company account status is active
        if (($user->role === 'staff' || !empty($user->parent_id)) && !empty($user->parent_id)) {
            $parentCompany = User::find($user->parent_id);
            if ($parentCompany && $parentCompany->status !== 'active') {
                return response()->json([
                    'status' => false,
                    'message' => 'Your company account is inactive. Please contact support.'
                ], 403);
            }
        }

        // 3. Check module permission if user is staff
        if ($modulePermission !== '*' && !$user->hasPermission($modulePermission)) {
            return response()->json([
                'status' => false,
                'message' => "Access denied. You do not have permission to access the '{$modulePermission}' module."
            ], 403);
        }

        return $next($request);
    }
}
