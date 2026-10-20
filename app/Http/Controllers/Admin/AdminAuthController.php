<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    /**
     * Admin Login
     * POST /api/v1/auth/login or /api/v1/admin/auth/login
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Email and password are required',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $user = User::where('email', $request->email)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            if (!Hash::check($request->password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid email or password'
                ], 401);
            }

            // Verify user has Super Admin role only (reject admin, staff, user)
            if (!$user->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only Super Admin can log in here.'
                ], 403);
            }

            if ($user->status !== 'active') {
                return response()->json([
                    'success' => false,
                    'message' => 'Your account is inactive. Please contact support.'
                ], 403);
            }

            // If staff member, verify parent company account is active
            if (($user->role === 'staff' || $user->parent_id) && $user->parent_id) {
                $parentCompany = User::find($user->parent_id);
                if ($parentCompany && $parentCompany->status !== 'active') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Your company account is inactive. Please contact support.'
                    ], 403);
                }
            }

            $token = $user->createToken('Admin Access Token')->accessToken;

        
            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'data' => $this->formatAdminProfile($user),
                'user' => $this->formatAdminProfile($user)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Server error during login: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Admin Profile
     * GET /api/v1/profile or /api/v1/admin/profile
     */
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();

            return response()->json([
                'success' => true,
                'data' => $this->formatAdminProfile($user)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update Admin / Super Admin Profile
     * PUT or POST /api/v1/profile or /api/v1/admin/profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            // Handle raw PUT request file bag parsing
            $this->parseRawPutMultipart($request);

            $phoneInput = $request->input('phone', $request->input('number', $user->number));

            $validator = Validator::make(array_merge($request->all(), ['phone' => $phoneInput]), [
                'firstName' => 'nullable|string|max:255',
                'lastName' => 'nullable|string|max:255',
                'name' => 'nullable|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'phone' => 'nullable|string',
                'company' => 'nullable|string',
                'companyName' => 'nullable|string',
                'companyAddress' => 'nullable|string',
                'companyEmail' => 'nullable|email',
                'companyMobile' => 'nullable|string',
                'bio' => 'nullable|string',
                'timezone' => 'nullable|string',
                'profileImage' => 'nullable',
                'companyLogo' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $firstName = $request->input('firstName', $user->firstName);
            $lastName = $request->input('lastName', $user->lastName);
            $nameInput = $request->input('name');

            if ($nameInput) {
                $fullName = trim($nameInput);
            } else {
                $fullName = trim($firstName . ' ' . $lastName);
            }

            $updateData = [
                'name' => $fullName ?: $user->name,
                'email' => $request->input('email', $user->email),
                'number' => $phoneInput,
                'companyName' => $request->input('company', $request->input('companyName', $user->companyName)),
            ];

            if ($request->has('bio')) {
                $updateData['bio'] = $request->input('bio');
            }
            if ($request->has('companyAddress')) {
                $updateData['companyAddress'] = $request->input('companyAddress');
            }
            if ($request->has('companyEmail')) {
                $updateData['companyEmail'] = $request->input('companyEmail');
            }
            if ($request->has('companyMobile')) {
                $updateData['companyMobile'] = $request->input('companyMobile');
            }

            $isProduction = app()->isProduction();

            // Handle Profile Image Upload / Base64 / URL
            $profileImagePath = $this->processImageInput($request, 'profileImage', 'profiles', $user->id, $isProduction);
            if ($profileImagePath !== null) {
                $updateData['profileImage'] = $profileImagePath;
            }

            // Handle Company Logo Upload / Base64 / URL
            $companyLogoPath = $this->processImageInput($request, 'companyLogo', 'company', $user->id, $isProduction);
            if ($companyLogoPath !== null) {
                $updateData['companyLogo'] = $companyLogoPath;
            }

            $user->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $this->formatAdminProfile($user->fresh())
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating profile: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Change Password
     * POST /api/v1/profile/change-password or /api/v1/admin/profile/change-password
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:8',
            'confirmPassword' => 'required|string|same:newPassword',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $user = $request->user();

            if (!Hash::check($request->input('currentPassword'), $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password does not match'
                ], 400);
            }

            $user->password = Hash::make($request->input('newPassword'));
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper to format admin profile JSON structure
     */
    private function formatAdminProfile(User $user): array
    {
        $roleTitle = match (strtolower($user->role ?? '')) {
            'super_admin', 'super administrator' => 'Super Administrator',
            'admin', 'administrator' => 'Administrator',
            default => ucfirst($user->role ?? 'User')
        };

        return [
            'id' => $user->id,
            'dbId' => $user->id,
            'name' => $user->name,
            'firstName' => $user->firstName,
            'lastName' => $user->lastName,
            'email' => $user->email,
            'phone' => $user->number,
            'number' => $user->number,
            'company' => $user->companyName ?? '',
            'companyName' => $user->companyName ?? '',
            'companyLogo' => $user->companyLogo ?? '',
            'companyLogoUrl' => $user->company_logo_url ?? '',
            'companyMobile' => $user->companyMobile ?? '',
            'companyEmail' => $user->companyEmail ?? '',
            'companyAddress' => $user->companyAddress ?? '',
            'profileImage' => $user->profileImage ?? '',
            'profileImageUrl' => $user->profile_image_url ?? '',
            'bio' => $user->bio ?? '',
            'timezone' => 'Asia/Kolkata',
            'role' => $roleTitle,
            'roleRaw' => $user->role,
            'status' => $user->status,
        ];
    }

    /**
     * Helper to process image input (Uploaded file, base64 data string, or existing URL/path)
     */
    private function processImageInput(Request $request, string $fieldKey, string $uploadFolder, int $userId, bool $isProduction): ?string
    {
        // 1. Check if uploaded file exists in request
        if ($request->hasFile($fieldKey)) {
            $file = $request->file($fieldKey);
            if ($file && $file->isValid()) {
                $ext = $file->getClientOriginalExtension() ?: 'png';
                $filename = time() . '_' . $userId . '_' . $fieldKey . '.' . $ext;
                $destinationPath = public_path('uploads/' . $uploadFolder);
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                $file->move($destinationPath, $filename);
                return $isProduction ? '/public/uploads/' . $uploadFolder . '/' . $filename : '/uploads/' . $uploadFolder . '/' . $filename;
            }
        }

        // 2. Check if string input is provided (base64 string or image URL/path)
        if ($request->has($fieldKey)) {
            $inputVal = $request->input($fieldKey);
            if (is_string($inputVal) && !empty(trim($inputVal))) {
                $trimmed = trim($inputVal);

                // Base64 data URI (e.g. data:image/png;base64,iVBORw0...)
                if (preg_match('/^data:image\/(\w+);base64,/', $trimmed, $matches)) {
                    $imageType = strtolower($matches[1]);
                    if ($imageType === 'jpeg') {
                        $imageType = 'jpg';
                    }
                    $imageData = substr($trimmed, strpos($trimmed, ',') + 1);
                    $decodedData = base64_decode($imageData);

                    if ($decodedData !== false) {
                        $filename = time() . '_' . $userId . '_' . $fieldKey . '.' . $imageType;
                        $destinationPath = public_path('uploads/' . $uploadFolder);
                        if (!file_exists($destinationPath)) {
                            mkdir($destinationPath, 0755, true);
                        }
                        file_put_contents($destinationPath . '/' . $filename, $decodedData);
                        return $isProduction ? '/public/uploads/' . $uploadFolder . '/' . $filename : '/uploads/' . $uploadFolder . '/' . $filename;
                    }
                }

                // If full URL passed (e.g. http://domain.com/uploads/profiles/123.png), extract relative path
                if (preg_match('/(\/(?:public\/)?uploads\/.+)$/i', $trimmed, $pathMatches)) {
                    return $pathMatches[1];
                }

                // Return clean relative path
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * Parse raw PUT multipart/form-data when PHP's $_FILES is empty
     */
    private function parseRawPutMultipart(Request $request): void
    {
        if (!$request->isMethod('put')) {
            return;
        }

        $contentType = $request->header('Content-Type', '');
        if (!str_contains($contentType, 'multipart/form-data')) {
            return;
        }

        if (!empty($request->allFiles())) {
            return;
        }

        if (!preg_match('/boundary=(.*)$/', $contentType, $matches)) {
            return;
        }
        $boundary = trim($matches[1], '"\'');

        $rawInput = file_get_contents('php://input');
        if (empty($rawInput)) {
            return;
        }

        $blocks = explode('--' . $boundary, $rawInput);
        foreach ($blocks as $block) {
            if (empty(trim($block)) || trim($block) === '--') {
                continue;
            }

            if (str_contains($block, "\r\n\r\n")) {
                list($headerStr, $bodyStr) = explode("\r\n\r\n", $block, 2);
            } elseif (str_contains($block, "\n\n")) {
                list($headerStr, $bodyStr) = explode("\n\n", $block, 2);
            } else {
                continue;
            }

            $bodyStr = preg_replace("/\r\n$/", '', $bodyStr);
            $bodyStr = preg_replace("/\n$/", '', $bodyStr);

            if (preg_match('/name="([^"]+)"(?:;\s*filename="([^"]+)")?/', $headerStr, $nameMatches)) {
                $name = $nameMatches[1];
                $filename = $nameMatches[2] ?? null;

                if ($filename && !empty($filename)) {
                    $tmpPath = sys_get_temp_dir() . '/' . uniqid('put_file_', true);
                    file_put_contents($tmpPath, $bodyStr);

                    $mimeType = 'application/octet-stream';
                    if (preg_match('/Content-Type:\s*([^\r\n]+)/i', $headerStr, $mimeMatches)) {
                        $mimeType = trim($mimeMatches[1]);
                    }

                    $uploadedFile = new \Illuminate\Http\UploadedFile(
                        $tmpPath,
                        $filename,
                        $mimeType,
                        null,
                        true
                    );

                    $request->files->set($name, $uploadedFile);
                } else {
                    $request->merge([$name => $bodyStr]);
                }
            }
        }
    }
}
