<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\UploadedFile;

class AdminStaffController extends Controller
{
    /**
     * List all staff members
     * GET /api/v1/admin/staff?user_id={company_user_id}
     */
    public function index(Request $request)
    {
        try {
            $query = User::where(function ($q) {
                $q->where('role', 'staff')
                  ->orWhereNotNull('parent_id');
            });

            // Filter by specific company/user ID if passed
            $targetUserId = $request->query('user_id') ?? $request->query('company_id');
            if (!empty($targetUserId)) {
                $query->where('parent_id', $targetUserId);
            }

            $staffList = $query->orderBy('created_at', 'desc')
                ->get()
                ->map(fn($staff) => $this->formatStaffData($staff));

            return response()->json([
                'success' => true,
                'data' => $staffList
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch staff list: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get staff member details
     * GET /api/v1/admin/staff/{id}
     */
    public function show(Request $request, $id)
    {
        try {
            $staff = User::where(function ($q) {
                $q->where('role', 'staff')
                  ->orWhereNotNull('parent_id');
            })->find($id);

            if (!$staff) {
                // Fallback to find any user by id
                $staff = User::find($id);
            }

            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff member not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->formatStaffData($staff)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching staff details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a new staff member
     * POST /api/v1/admin/staff
     */
    public function store(Request $request)
    {
        $phoneNumber = $request->input('phone') ?? $request->input('number');
        $targetUserId = $request->input('user_id') ?? $request->input('company_id') ?? $request->input('parent_id');

        $validator = Validator::make(array_merge($request->all(), [
            'phone_check' => $phoneNumber,
            'user_id_check' => $targetUserId,
        ]), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone_check' => 'required|string|unique:users,number',
            'user_id_check' => 'required|exists:users,id',
            'status' => 'required|string|in:active,inactive,pending',
            'password' => 'required|string|min:6',
        ], [
            'phone_check.required' => 'The phone/number field is required.',
            'phone_check.unique' => 'The phone number has already been taken.',
            'user_id_check.required' => 'The user_id (Company ID) is required.',
            'user_id_check.exists' => 'The selected company user does not exist.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $isProduction = app()->isProduction();

            // Find target company user to fetch companyName
            $companyUser = User::find($targetUserId);
            $companyName = $companyUser ? ($companyUser->companyName ?? $companyUser->name) : null;

            // Handle Profile Image
            $profileImagePath = $this->processImageInput($request, 'profileImage', 'profiles', $targetUserId, $isProduction);

            // Handle permissions input
            $defaultPermissions = ['contacts', 'campaigns', 'templates', 'conversations', 'reports', 'settings', 'billing'];
            $permissionsInput = $request->input('permissions', $defaultPermissions);
            if (is_string($permissionsInput)) {
                $decoded = json_decode($permissionsInput, true);
                $permissionsInput = is_array($decoded) ? $decoded : array_map('trim', explode(',', $permissionsInput));
            }

            $staff = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'number' => $phoneNumber,
                'password' => Hash::make($request->password),
                'temp_password' => $request->password,
                'role' => 'staff',
                'parent_id' => $targetUserId,
                'status' => $request->input('status', 'active'),
                'companyName' => $companyName,
                'profileImage' => $profileImagePath ?: '/uploads/default.png',
                'permissions' => is_array($permissionsInput) ? array_values(array_unique($permissionsInput)) : $defaultPermissions,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Staff member created successfully',
                'data' => $this->formatStaffData($staff)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating staff member: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing staff member
     * POST /api/v1/admin/staff/{id} (with _method="PUT") OR PUT /api/v1/admin/staff/{id}
     */
    public function update(Request $request, $id)
    {
        // Parse raw multipart stream if request is raw HTTP PUT
        if ($request->isMethod('put') && count($request->allFiles()) === 0) {
            $this->parseRawPutMultipart($request);
        }

        $staff = User::find($id);
        if (!$staff) {
            return response()->json([
                'success' => false,
                'message' => 'Staff member not found'
            ], 404);
        }

        $phoneNumber = $request->input('phone') ?? $request->input('number') ?? $staff->number;
        $targetUserId = $request->input('user_id') ?? $request->input('company_id') ?? $request->input('parent_id') ?? $staff->parent_id;

        $validator = Validator::make([
            'name' => $request->input('name', $staff->name),
            'email' => $request->input('email', $staff->email),
            'number' => $phoneNumber,
            'user_id' => $targetUserId,
            'status' => $request->input('status', $staff->status),
            'password' => $request->input('password'),
        ], [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'number' => 'sometimes|required|string|unique:users,number,' . $id,
            'user_id' => 'sometimes|required|exists:users,id',
            'status' => 'sometimes|required|string|in:active,inactive,pending',
            'password' => 'nullable|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $isProduction = app()->isProduction();
            $updateData = [];

            if ($request->filled('name')) {
                $updateData['name'] = $request->name;
            }
            if ($request->filled('email')) {
                $updateData['email'] = $request->email;
            }
            if ($phoneNumber !== null) {
                $updateData['number'] = $phoneNumber;
            }
            if ($request->filled('status')) {
                $updateData['status'] = $request->status;
            }
            if ($targetUserId !== null) {
                $updateData['parent_id'] = $targetUserId;
                $companyUser = User::find($targetUserId);
                if ($companyUser) {
                    $updateData['companyName'] = $companyUser->companyName ?? $companyUser->name;
                }
            }
            if ($request->filled('password')) {
                $updateData['password'] = Hash::make($request->password);
                $updateData['temp_password'] = $request->password;
            }

            if ($request->has('permissions')) {
                $permissionsInput = $request->input('permissions');
                if (is_string($permissionsInput)) {
                    $decoded = json_decode($permissionsInput, true);
                    $permissionsInput = is_array($decoded) ? $decoded : array_map('trim', explode(',', $permissionsInput));
                }
                if (is_array($permissionsInput)) {
                    $updateData['permissions'] = array_values(array_unique($permissionsInput));
                }
            }

            // Handle Profile Image Upload
            $profileImagePath = $this->processImageInput($request, 'profileImage', 'profiles', $staff->id, $isProduction);
            if ($profileImagePath !== null) {
                $updateData['profileImage'] = $profileImagePath;
            }

            $staff->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Staff member updated successfully',
                'data' => $this->formatStaffData($staff->fresh())
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating staff member: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update staff status (active / inactive / pending)
     * POST or PUT /api/v1/admin/staff/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $staff = User::find($id);

            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff member not found'
                ], 404);
            }

            $status = $request->input('status');
            if (!$status) {
                // Toggle status if not explicitly passed
                $status = $staff->status === 'active' ? 'inactive' : 'active';
            }

            if (!in_array($status, ['active', 'inactive', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status value. Allowed: active, inactive, pending'
                ], 400);
            }

            $staff->update(['status' => $status]);

            return response()->json([
                'success' => true,
                'message' => "Staff status updated to '{$status}' successfully",
                'data' => $this->formatStaffData($staff->fresh())
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating staff status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a staff member
     * DELETE /api/v1/admin/staff/{id}
     */
    public function destroy(Request $request, $id)
    {
        try {
            $staff = User::find($id);

            if (!$staff) {
                return response()->json([
                    'success' => false,
                    'message' => 'Staff member not found'
                ], 404);
            }

            // Remove profile image file if exists
            if ($staff->profileImage && file_exists(public_path($staff->profileImage)) && !str_contains($staff->profileImage, 'default')) {
                @unlink(public_path($staff->profileImage));
            }

            $staff->delete();

            return response()->json([
                'success' => true,
                'message' => 'Staff member deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting staff member: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format Staff model data for API response
     */
    private function formatStaffData(User $staff): array
    {
        $companyUser = $staff->parent_id ? User::find($staff->parent_id) : null;
        $companyName = $staff->companyName ?? ($companyUser ? ($companyUser->companyName ?? $companyUser->name) : null);

        $defaultPermissions = ['contacts', 'campaigns', 'templates', 'conversations', 'reports', 'settings', 'billing'];
        $staffPermissions = $staff->permissions;
        if (is_string($staffPermissions)) {
            $staffPermissions = json_decode($staffPermissions, true);
        }
        if (empty($staffPermissions) || !is_array($staffPermissions)) {
            $staffPermissions = $defaultPermissions;
        }

        $parentUserData = null;
        if ($companyUser) {
            $parentUserData = [
                'id' => 'usr_' . $companyUser->id,
                'dbId' => $companyUser->id,
                'name' => $companyUser->name,
                'email' => $companyUser->email,
                'phone' => $companyUser->number,
                'number' => $companyUser->number,
                'role' => $companyUser->role ?? 'admin',
                'companyName' => $companyUser->companyName ?? $companyUser->name,
                'companyMobile' => $companyUser->companyMobile ?? '',
                'companyEmail' => $companyUser->companyEmail ?? '',
                'profileImage' => $companyUser->profileImage ?? '',
                'profileImageUrl' => $companyUser->profile_image_url ?? '',
                'status' => $companyUser->status,
            ];
        }

        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'email' => $staff->email,
            'number' => $staff->number,
            'parentId' => $staff->parent_id,
            'parent_name' => $companyUser ? $companyUser->name : null,
            'parent_email' => $companyUser ? $companyUser->email : null,
            'parentUser' => $parentUserData,
            'companyName' => $companyName,
            'status' => $staff->status,
            'role' => $staff->role ?? 'staff',
            'password' => $staff->temp_password ?? 'Password123!',
            'permissions' => array_values(array_unique($staffPermissions)),
            'profileImage' => $staff->profile_image_url ?? $staff->profileImage,
            'created_at' => optional($staff->created_at)->toISOString(),
            'updated_at' => optional($staff->updated_at)->toISOString(),
        ];
    }

    /**
     * Process Image File Upload / Base64 / String Path
     */
    private function processImageInput(Request $request, string $fieldKey, string $folder, $entityId, bool $isProduction): ?string
    {
        // 1. Uploaded File
        if ($request->hasFile($fieldKey)) {
            $file = $request->file($fieldKey);
            $filename = time() . '_' . $entityId . '_' . $fieldKey . '.' . $file->getClientOriginalExtension();
            $destinationPath = public_path("uploads/{$folder}");
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }
            $file->move($destinationPath, $filename);
            return $isProduction ? "/public/uploads/{$folder}/" . $filename : "/uploads/{$folder}/" . $filename;
        }

        // 2. Base64 String or String Path
        $inputValue = $request->input($fieldKey);
        if (is_string($inputValue) && !empty(trim($inputValue))) {
            $trimmed = trim($inputValue);
            if (str_starts_with($trimmed, 'data:image/')) {
                return $this->saveBase64Image($trimmed, $folder, $entityId, $isProduction);
            }

            // Clean full domain prefix if present
            $cleaned = preg_replace('#^https?://[^/]+#i', '', $trimmed);
            if ($isProduction && !str_starts_with($cleaned, '/public/uploads/')) {
                $cleaned = '/public' . ltrim($cleaned, '/');
            }
            return $cleaned;
        }

        return null;
    }

    /**
     * Save base64 encoded image string to disk
     */
    private function saveBase64Image(string $base64String, string $folder, $entityId, bool $isProduction): ?string
    {
        try {
            if (!preg_match('/^data:image\/(\w+);base64,/', $base64String, $type)) {
                return null;
            }

            $data = substr($base64String, strpos($base64String, ',') + 1);
            $type = strtolower($type[1]); // jpg, png, gif, etc.
            if (!in_array($type, ['jpg', 'jpeg', 'gif', 'png', 'webp', 'svg'])) {
                $type = 'png';
            }

            $data = base64_decode($data);
            if ($data === false) {
                return null;
            }

            $filename = time() . '_' . $entityId . '_' . uniqid() . '.' . $type;
            $destinationPath = public_path("uploads/{$folder}");
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            file_put_contents($destinationPath . '/' . $filename, $data);
            return $isProduction ? "/public/uploads/{$folder}/" . $filename : "/uploads/{$folder}/" . $filename;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Parse raw multipart PUT request stream
     */
    private function parseRawPutMultipart(Request $request): void
    {
        $rawInput = file_get_contents('php://input');
        if (empty($rawInput)) {
            return;
        }

        $contentType = $request->header('Content-Type', '');
        if (!preg_match('/boundary=(.*)$/i', $contentType, $matches)) {
            return;
        }

        $boundary = trim($matches[1], '"');
        $blocks = explode('--' . $boundary, $rawInput);

        $parsedInputs = [];

        foreach ($blocks as $block) {
            if (empty($block) || str_contains($block, '--')) {
                continue;
            }

            if (str_contains($block, 'filename=')) {
                preg_match('/name="([^"]+)";\s*filename="([^"]+)"/i', $block, $headerMatches);
                preg_match('/Content-Type:\s*([^\r\n]+)/i', $block, $typeMatches);

                if (count($headerMatches) === 3) {
                    $fieldName = $headerMatches[1];
                    $originalName = $headerMatches[2];
                    $mimeType = $typeMatches[1] ?? 'application/octet-stream';

                    $content = substr($block, strpos($block, "\r\n\r\n") + 4);
                    $content = substr($content, 0, -2);

                    $tmpFilePath = tempnam(sys_get_temp_dir(), 'put_file_');
                    file_put_contents($tmpFilePath, $content);

                    $uploadedFile = new UploadedFile(
                        $tmpFilePath,
                        $originalName,
                        $mimeType,
                        null,
                        true
                    );

                    $request->files->set($fieldName, $uploadedFile);
                }
            } else {
                preg_match('/name="([^"]+)"/i', $block, $headerMatches);
                if (count($headerMatches) === 2) {
                    $fieldName = $headerMatches[1];
                    $value = substr($block, strpos($block, "\r\n\r\n") + 4);
                    $value = substr($value, 0, -2);
                    $parsedInputs[$fieldName] = $value;
                }
            }
        }

        if (!empty($parsedInputs)) {
            $request->merge($parsedInputs);
        }
    }

    /**
     * Get available system staff permissions list
     * GET /api/v1/admin/staff/permissions
     */
    public function getAvailablePermissions(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                ['key' => 'contacts', 'label' => 'Contact Management', 'description' => 'Manage contacts and contact groups'],
                ['key' => 'campaigns', 'label' => 'Campaign & Broadcast', 'description' => 'Send bulk messages and create scheduled campaigns'],
                ['key' => 'templates', 'label' => 'Template Management', 'description' => 'View, create and update Meta message templates'],
                ['key' => 'conversations', 'label' => 'Live Chat & Messages', 'description' => 'Access chat inbox and send single messages'],
                ['key' => 'reports', 'label' => 'Analytics & Reports', 'description' => 'View delivery statistics and campaign logs'],
                ['key' => 'settings', 'label' => 'API Credentials & Settings', 'description' => 'View and update WABA Meta settings and keys'],
                ['key' => 'billing', 'label' => 'Subscription & Credits', 'description' => 'View active plan and credit limits'],
            ]
        ], 200);
    }
}
