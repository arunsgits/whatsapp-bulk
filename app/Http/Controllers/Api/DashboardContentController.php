<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DashboardContentController extends Controller
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

        if (strtolower((string) ($user->role ?? '')) === 'admin' && $request->filled('user_id')) {
            return (int) $request->query('user_id', $request->input('user_id'));
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
            if ($isAdmin && !$request->filled('user_id')) {
                return Settings::whereNull('userId')->first() ?: Settings::first();
            }

            $ownerId = $this->resolveOwnerId($request);
            if ($ownerId !== null) {
                $setting = Settings::where('userId', $ownerId)->first();
                if ($setting && ($setting->dashboard_content_title || $setting->dashboard_content_image)) {
                    return $setting;
                }
            }
        }

        // Return first existing setting record, or where userId is null
        return Settings::whereNull('userId')->first() 
            ?: Settings::whereNotNull('dashboard_content_title')
                ->orWhereNotNull('dashboard_content_image')
                ->orWhereNotNull('dashboard_content_status')
                ->first() 
            ?: Settings::first();
    }

    /**
     * Display dashboard-content stored in settings table.
     * GET /api/dashboard-content
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $setting = $this->getSettingsRecord($request);

            $data = [
                'title' => $setting->dashboard_content_title ?? null,
                'description' => $setting->dashboard_content_description ?? null,
                'image' => $setting->dashboard_content_image ?? null,
                'image_url' => Settings::formatImageUrl($setting->dashboard_content_image ?? null),
                'link' => $setting->dashboard_content_link ?? null,
                'status' => isset($setting->dashboard_content_status) ? (int) $setting->dashboard_content_status : 1,
            ];

            return response()->json([
                'status' => true,
                'message' => 'Dashboard content fetched successfully',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch dashboard content: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show method (returns the dashboard content record).
     * GET /api/dashboard-content/{id}
     */
    public function show(Request $request, $id = null): JsonResponse
    {
        return $this->index($request);
    }

    /**
     * Store/Update dashboard content in settings table.
     * POST /api/dashboard-content
     * Optional fields: title, description, image, link, status (0,1)
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable',
            'link' => 'nullable|string|max:1000',
            'status' => 'nullable|in:0,1,"0","1",true,false',
            'dashboard_content_status' => 'nullable|in:0,1,"0","1",true,false',
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
                // Admin configuring global dashboard content
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

            if ($request->has('title')) {
                $setting->dashboard_content_title = $request->input('title');
            }
            if ($request->has('description')) {
                $setting->dashboard_content_description = $request->input('description');
            }
            if ($request->has('link')) {
                $setting->dashboard_content_link = $request->input('link');
            }

            // Status handling (0 or 1)
            if ($request->has('status') || $request->has('dashboard_content_status')) {
                $rawStatus = $request->has('status') ? $request->input('status') : $request->input('dashboard_content_status');
                $setting->dashboard_content_status = filter_var($rawStatus, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null
                    ? (filter_var($rawStatus, FILTER_VALIDATE_BOOLEAN) ? 1 : 0)
                    : ((int) $rawStatus === 1 ? 1 : 0);
            } elseif (!$isUpdate && !isset($setting->dashboard_content_status)) {
                $setting->dashboard_content_status = 1;
            }

            // Image handling (upload or string URL)
            if ($request->hasFile('image')) {
                // Delete previous local image if updating
                if ($setting->dashboard_content_image && !str_starts_with($setting->dashboard_content_image, 'http')) {
                    $oldPath = public_path(str_replace('/public/', '/', $setting->dashboard_content_image));
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension() ?: 'png';
                $filename = 'dc_' . time() . '_' . Str::uuid() . '.' . $extension;

                $uploadDir = public_path('uploads/dashboard-content');
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $file->move($uploadDir, $filename);

                $isProduction = app()->isProduction();
                $setting->dashboard_content_image = $isProduction
                    ? '/public/uploads/dashboard-content/' . $filename
                    : '/uploads/dashboard-content/' . $filename;
            } elseif ($request->has('image') && is_string($request->input('image'))) {
                $setting->dashboard_content_image = $request->input('image');
            }

            $setting->save();

            $data = [
                'title' => $setting->dashboard_content_title,
                'description' => $setting->dashboard_content_description,
                'image' => $setting->dashboard_content_image,
                'image_url' => Settings::formatImageUrl($setting->dashboard_content_image),
                'link' => $setting->dashboard_content_link,
                'status' => isset($setting->dashboard_content_status) ? (int) $setting->dashboard_content_status : 1,
            ];

            return response()->json([
                'status' => true,
                'action' => $isUpdate ? 'updated' : 'created',
                'message' => $isUpdate 
                    ? 'Dashboard content updated successfully' 
                    : 'Dashboard content created successfully',
                'data' => $data,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to save dashboard content: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update dashboard content in settings table.
     * PUT/POST /api/dashboard-content/{id}
     */
    public function update(Request $request, $id = null): JsonResponse
    {
        return $this->store($request);
    }

    /**
     * Delete/Clear dashboard content from settings table.
     * DELETE /api/dashboard-content/{id}
     */
    public function destroy(Request $request, $id = null): JsonResponse
    {
        try {
            $setting = $this->getSettingsRecord($request);

            if ($setting) {
                // Delete local file if present
                if ($setting->dashboard_content_image && !str_starts_with($setting->dashboard_content_image, 'http')) {
                    $oldPath = public_path(str_replace('/public/', '/', $setting->dashboard_content_image));
                    if (file_exists($oldPath)) {
                        @unlink($oldPath);
                    }
                }

                $setting->dashboard_content_title = null;
                $setting->dashboard_content_description = null;
                $setting->dashboard_content_image = null;
                $setting->dashboard_content_link = null;
                $setting->save();
            }

            return response()->json([
                'status' => true,
                'message' => 'Dashboard content cleared from settings table successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete dashboard content: ' . $e->getMessage(),
            ], 500);
        }
    }
}
