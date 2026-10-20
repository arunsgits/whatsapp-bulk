<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppLiveController extends Controller
{
    /**
     * Get current is_app_live value.
     *
     * GET /api/is-app-live
     * GET /api/app-live
     */
    public function getAppLive(Request $request): JsonResponse
    {
        try {
            $isAppLive = Settings::getAppLiveStatus();

            return response()->json([
                'status' => true,
                'message' => 'App live status retrieved successfully',
                'is_app_live' => $isAppLive,
                'data' => [
                    'is_app_live' => $isAppLive,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve app live status: ' . $e->getMessage(),
                'is_app_live' => true,
                'data' => [
                    'is_app_live' => true,
                ],
            ], 500);
        }
    }

    /**
     * Toggle or explicitly update is_app_live value.
     *
     * POST /api/is-app-live/toggle
     * POST /api/is-app-live
     * PUT  /api/is-app-live
     */
    public function toggleAppLive(Request $request): JsonResponse
    {
        try {
            $currentStatus = Settings::getAppLiveStatus();

            if ($request->has('is_app_live')) {
                $rawVal = $request->input('is_app_live');
                $newStatus = filter_var($rawVal, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($newStatus === null) {
                    $newStatus = $request->boolean('is_app_live');
                }
            } else {
                // If not provided in body, toggle the current boolean value
                $newStatus = !$currentStatus;
            }

            Settings::setAppLiveStatus($newStatus);

            return response()->json([
                'status' => true,
                'message' => 'App live status updated successfully',
                'is_app_live' => $newStatus,
                'data' => [
                    'is_app_live' => $newStatus,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update app live status: ' . $e->getMessage(),
            ], 500);
        }
    }
}
