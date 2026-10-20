<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DashboardImageController extends Controller
{
    /**
     * Resolve company/owner ID for the user if authenticated.
     */
    protected function resolveOwnerId(Request $request): ?int
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        if (strtolower((string) ($user->role ?? '')) === 'admin' && ($request->filled('userId') || $request->filled('user_id'))) {
            return (int) $request->input('userId', $request->input('user_id'));
        }

        return method_exists($user, 'getCompanyId') ? (int) $user->getCompanyId() : (int) $user->id;
    }

    /**
     * Retrieve target Settings record.
     */
    protected function getSettingsRecord(Request $request): ?Settings
    {
        $user = $request->user();

        if ($user) {
            $isAdmin = strtolower((string) ($user->role ?? '')) === 'admin';
            if ($isAdmin && !$request->filled('userId') && !$request->filled('user_id')) {
                return Settings::whereNull('userId')->first() ?: Settings::first();
            }

            $ownerId = $this->resolveOwnerId($request);
            if ($ownerId !== null) {
                $setting = Settings::where('userId', $ownerId)->first();
                if ($setting && $setting->dashboard_image_image) {
                    return $setting;
                }
            }
        }

        // Return first existing setting record, or where userId is null
        return Settings::whereNull('userId')->first()
            ?: Settings::whereNotNull('dashboard_image_image')
                ->orWhereNotNull('dashboard_image_status')
                ->first() 
            ?: Settings::first();
    }

    /**
     * Display dashboard-image stored in settings table.
     * GET /api/dashboard-image
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $setting = $this->getSettingsRecord($request);

            $data = [
                'image' => $setting->dashboard_image_image ?? null,
                'image_url' => Settings::formatImageUrl($setting->dashboard_image_image ?? null),
                'status' => isset($setting->dashboard_image_status) ? (int) $setting->dashboard_image_status : 1,
            ];

            return response()->json([
                'status' => true,
                'message' => 'Dashboard image fetched successfully',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch dashboard image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show method (returns the dashboard image record).
     * GET /api/dashboard-image/{id}
     */
    public function show(Request $request, $id = null): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * Store/Update dashboard image in settings table.
     * POST /api/dashboard-image
     * Optional fields: image, dashboard_image_image, status (0,1)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'image' => 'nullable',
            'dashboard_image_image' => 'nullable',
            'status' => 'nullable|in:0,1,"0","1",true,false',
            'dashboard_image_status' => 'nullable|in:0,1,"0","1",true,false',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $user = $request->user();
            $isAdmin = $user && strtolower((string) ($user->role ?? '')) === 'admin';

            $setting = null;
            if ($isAdmin && !$request->filled('user_id')) {
                // Admin configuring global dashboard image
                $setting = Settings::whereNull('userId')->first() ?: Settings::first();
                if (!$setting) {
                    $setting = new Settings();
                }
            } else {
                $ownerId = $this->resolveOwnerId($request);
                if ($ownerId !== null) {
                    $setting = Settings::where('userId', $ownerId)->first();
                }
                if (!$setting) {
                    $setting = Settings::whereNull('userId')->first() ?: Settings::first();
                }
                if (!$setting) {
                    $setting = new Settings(['userId' => $ownerId]);
                }
            }
            $isUpdate = $setting->exists;

            // Status handling (0 or 1)
            if ($request->has('status') || $request->has('dashboard_image_status')) {
                $rawStatus = $request->has('status') ? $request->input('status') : $request->input('dashboard_image_status');
                $setting->dashboard_image_status = filter_var($rawStatus, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null
                    ? (filter_var($rawStatus, FILTER_VALIDATE_BOOLEAN) ? 1 : 0)
                    : ((int) $rawStatus === 1 ? 1 : 0);
            } elseif (!$isUpdate && !isset($setting->dashboard_image_status)) {
                $setting->dashboard_image_status = 1;
            }

            // Support either 'image' or 'dashboard_image_image' in request
            $hasFile = $request->hasFile('image') || $request->hasFile('dashboard_image_image');
            $file = $request->file('image') ?: $request->file('dashboard_image_image');

            if ($hasFile && $file) {
                // Delete previous file if local
                if ($setting->dashboard_image_image && !str_starts_with($setting->dashboard_image_image, 'http')) {
                    $oldPath = public_path(str_replace('/public/', '/', $setting->dashboard_image_image));
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $extension = $file->getClientOriginalExtension() ?: 'png';
                $filename = 'di_' . time() . '_' . Str::uuid() . '.' . $extension;

                $uploadDir = public_path('uploads/dashboard-images');
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $file->move($uploadDir, $filename);

                $isProduction = app()->isProduction();
                $setting->dashboard_image_image = $isProduction
                    ? '/public/uploads/dashboard-images/' . $filename
                    : '/uploads/dashboard-images/' . $filename;
            } elseif ($request->has('image') && is_string($request->input('image'))) {
                $setting->dashboard_image_image = $request->input('image');
            } elseif ($request->has('dashboard_image_image') && is_string($request->input('dashboard_image_image'))) {
                $setting->dashboard_image_image = $request->input('dashboard_image_image');
            }

            $setting->save();

            $data = [
                'image' => $setting->dashboard_image_image,
                'image_url' => Settings::formatImageUrl($setting->dashboard_image_image),
                'status' => isset($setting->dashboard_image_status) ? (int) $setting->dashboard_image_status : 1,
            ];

            return response()->json([
                'status' => true,
                'action' => $isUpdate ? 'updated' : 'created',
                'message' => $isUpdate 
                    ? 'Dashboard image updated successfully' 
                    : 'Dashboard image created successfully',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to save dashboard image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update dashboard image in settings table.
     * PUT/POST /api/dashboard-image/{id}
     */
    public function update(Request $request, $id = null): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Delete/Clear dashboard image from settings table.
     * DELETE /api/dashboard-image/{id}
     */
    public function destroy(Request $request, $id = null): JsonResponse
    {
        try {
            $setting = $this->getSettingsRecord($request);

            if ($setting) {
                // Delete local file if present
                if ($setting->dashboard_image_image && !str_starts_with($setting->dashboard_image_image, 'http')) {
                    $oldPath = public_path(str_replace('/public/', '/', $setting->dashboard_image_image));
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $setting->dashboard_image_image = null;
                $setting->save();
            }

            return response()->json([
                'status' => true,
                'message' => 'Dashboard image cleared from settings table successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete dashboard image: ' . $e->getMessage(),
            ], 500);
        }
    }
}
