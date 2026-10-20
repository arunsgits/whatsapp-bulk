<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\ActivePackage;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemplateController extends Controller
{
    /**
     * Resolve company/owner id (staff → parent). Admin may pass ?user_id=.
     */
    protected function resolveOwnerId(Request $request): int
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        if (strtolower((string) ($user->role ?? '')) === 'admin' && ($request->filled('userId') || $request->filled('user_id'))) {
            return (int) $request->input('userId', $request->input('user_id'));
        }

        return (int) $user->getCompanyId();
    }

    /**
     * Templates belonging to this user/company only.
     * Admin + ?all=1 can see every template.
     */
    protected function scopedQuery(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $query = Template::query();
        $isAdmin = strtolower((string) ($user->role ?? '')) === 'admin';

        if ($isAdmin && $request->boolean('all')) {
            return $query;
        }

        // Strictly this company — never shared/global (userId = null) templates
        return $query->where('userId', $this->resolveOwnerId($request));
    }

    protected function findOwnedTemplate(Request $request, $id): ?Template
    {
        return $this->scopedQuery($request)->find($id);
    }

    /**
     * Active WhatsApp settings for this user/company.
     */
    protected function userSettings(Request $request): ?Settings
    {
        $userId = $this->resolveOwnerId($request);

        return Settings::where('userId', $userId)->where('isActive', true)->first()
            ?: Settings::where('userId', $userId)->first();
    }

    /**
     * True when this user has their own WABA credentials (required to list Meta templates).
     */
    protected function hasWhatsAppCredentials(Request $request): bool
    {
        $settings = $this->userSettings($request);
        if (! $settings) {
            return false;
        }

        $wabaId = trim((string) ($settings->waba_id ?? ''));
        $token = trim((string) ($settings->access_token ?? ''));
        $phoneId = trim((string) ($settings->phoneNumberId ?? ''));

        return $wabaId !== '' && $token !== '' && $phoneId !== '';
    }

    protected function decorate(Template $template): Template
    {
        $template->isCustom = $template->isCustom;
        $template->isRequest = $template->isRequest;

        return $template;
    }

    /**
     * Get all templates for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $type = $request->query('type');
            $query = $this->scopedQuery($request);

            // No WhatsApp credentials → no Meta/system templates (only locally created custom)
            if (! $this->hasWhatsAppCredentials($request)) {
                $query->where('type', 'custom');
            }

            if ($type === 'custom') {
                $query->where('type', 'custom');
            } elseif ($type === 'system') {
                if (! $this->hasWhatsAppCredentials($request)) {
                    $query->whereRaw('1 = 0');
                } else {
                    $query->where('type', 'system');
                }
            }

            $templates = $query->orderBy('created_at', 'desc')->get();
            $templates->transform(fn ($template) => $this->decorate($template));

            return response()->json([
                'status' => true,
                'count' => $templates->count(),
                'meta' => [
                    'userId' => $this->resolveOwnerId($request),
                ],
                'data' => $templates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new template
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'content' => 'required|string',
            'type' => 'required|string|in:text,custom,system',
            'variables' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $userId = $this->resolveOwnerId($request);

            // Check active package for custom templates (this user only)
            if ($request->type === 'custom') {
                $activePackage = ActivePackage::where('userId', $userId)
                    ->where('status', 1)
                    ->with('package')
                    ->latest()
                    ->first();

                if (! $activePackage) {
                    return response()->json([
                        'status' => false,
                        'message' => 'No active plan found. Please purchase a plan first.',
                    ], 400);
                }

                $allowedTemplateCount = $activePackage->resolvedTemplateLimit();
                $currentTemplateCount = Template::where('userId', $userId)
                    ->where('type', 'custom')
                    ->count();

                Log::info('Template Limit Check', [
                    'userId' => $userId,
                    'userName' => $request->user()->name,
                    'packageName' => $activePackage->package->packageName,
                    'allowedTemplateCount' => $allowedTemplateCount,
                    'currentTemplateCount' => $currentTemplateCount,
                    'remainingTemplates' => $allowedTemplateCount - $currentTemplateCount,
                ]);

                if ($currentTemplateCount >= $allowedTemplateCount) {
                    return response()->json([
                        'status' => false,
                        'message' => "You can only create {$allowedTemplateCount} custom templates as per your plan. You currently have {$currentTemplateCount} templates.",
                    ], 403);
                }
            }

            $exists = Template::where('name', $request->name)
                ->where('userId', $userId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template name already exists for your account',
                ], 400);
            }

            $template = Template::create([
                'name' => $request->name,
                'content' => $request->content,
                'type' => $request->type,
                'variables' => $request->variables ?? [],
                'status' => 'active',
                'userId' => $userId,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Template created successfully',
                'data' => $this->decorate($template),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user's template limits and usage (user-wise)
     */
    public function getLimits(Request $request)
    {
        try {
            $userId = $this->resolveOwnerId($request);

            $activePackage = ActivePackage::where('userId', $userId)
                ->where('status', 1)
                ->with('package')
                ->latest()
                ->first();

            if (! $activePackage) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active plan found',
                ], 404);
            }

            $hasWaba = $this->hasWhatsAppCredentials($request);

            $customTemplateCount = Template::where('userId', $userId)
                ->where('type', 'custom')
                ->count();

            $systemTemplateCount = $hasWaba
                ? Template::where('userId', $userId)->where('type', 'system')->count()
                : 0;

            $activePackage->checkAndPerformMonthlyReset();
            $allowedTemplateCount = $activePackage->resolvedTemplateLimit();
            $remainingTemplates = max(0, $allowedTemplateCount - $customTemplateCount);
            $usedMessages = $activePackage->is_yearly
                ? (int) ($activePackage->monthlyUsedMsgCount ?? 0)
                : (int) ($activePackage->usedMsgCount ?? 0);
            $messageLimit = $activePackage->resolvedMessageLimit();

            return response()->json([
                'status' => true,
                'data' => [
                    'userId' => $userId,
                    'package' => [
                        'name' => $activePackage->package->packageName,
                        'templateLimit' => $allowedTemplateCount,
                        'messageLimit' => $messageLimit,
                        'usedMessages' => $usedMessages,
                        'remainingMessages' => max(0, $messageLimit - $usedMessages),
                    ],
                    'templates' => [
                        'custom' => [
                            'current' => $customTemplateCount,
                            'allowed' => $allowedTemplateCount,
                            'remaining' => $remainingTemplates,
                        ],
                        'system' => [
                            'current' => $systemTemplateCount,
                        ],
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a specific template
     */
    public function show(Request $request, $id)
    {
        try {
            $template = $this->findOwnedTemplate($request, $id);

            if (! $template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $this->decorate($template),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a template
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'content' => 'sometimes|string',
            'variables' => 'nullable|array',
            'status' => 'sometimes|string|in:active,inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $userId = $this->resolveOwnerId($request);
            $template = $this->findOwnedTemplate($request, $id);

            if (! $template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found',
                ], 404);
            }

            if ($request->has('name')) {
                $exists = Template::where('name', $request->name)
                    ->where('id', '!=', $id)
                    ->where('userId', $userId)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Template name already exists',
                    ], 400);
                }
            }

            $template->update($request->only(['name', 'content', 'variables', 'status']));

            return response()->json([
                'status' => true,
                'message' => 'Template updated successfully',
                'data' => $this->decorate($template->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a template
     */
    public function destroy(Request $request, $id)
    {
        try {
            $template = $this->findOwnedTemplate($request, $id);

            if (! $template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found',
                ], 404);
            }

            $template->delete();

            return response()->json([
                'status' => true,
                'message' => 'Template deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update template status
     */
    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:PENDING,APPROVED,REJECTED',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $user = $request->user();
            $isAdmin = strtolower((string) ($user->role ?? '')) === 'admin';

            // Admin can update any pending request; users only their own
            $template = $isAdmin
                ? Template::find($id)
                : $this->findOwnedTemplate($request, $id);

            if (! $template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found',
                ], 404);
            }

            $template->update(['status' => $request->status]);

            return response()->json([
                'status' => true,
                'message' => "Template status updated to '{$request->status}' successfully",
                'data' => $this->decorate($template->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user templates (strictly this user's library)
     */
    public function getUserTemplates(Request $request)
    {
        try {
            $query = $this->scopedQuery($request)->where('status', '!=', 'inactive');
            if (! $this->hasWhatsAppCredentials($request)) {
                $query->where('type', 'custom');
            }

            $templates = $query->orderBy('created_at', 'desc')->get();

            $templates->transform(fn ($template) => $this->decorate($template));

            return response()->json([
                'status' => true,
                'count' => $templates->count(),
                'meta' => [
                    'userId' => $this->resolveOwnerId($request),
                ],
                'data' => $templates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get filtered templates (user-wise)
     */
    public function getFilteredTemplates(Request $request)
    {
        try {
            $type = $request->query('type');
            $status = $request->query('status');
            $category = $request->query('category');

            $query = $this->scopedQuery($request);

            if (! $this->hasWhatsAppCredentials($request)) {
                $query->where('type', 'custom');
            }

            if ($type) {
                $query->where('type', $type);
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($category) {
                $query->where('category', $category);
            }

            $templates = $query->orderBy('created_at', 'desc')->get();
            $templates->transform(fn ($template) => $this->decorate($template));

            return response()->json([
                'status' => true,
                'count' => $templates->count(),
                'meta' => [
                    'userId' => $this->resolveOwnerId($request),
                ],
                'data' => $templates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add custom template
     */
    public function addCustomTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'language' => 'required|string',
            'category' => 'required|string',
            'components' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $userId = $this->resolveOwnerId($request);

            $activePackage = ActivePackage::where('userId', $userId)
                ->where('status', 1)
                ->with('package')
                ->first();

            if (! $activePackage) {
                return response()->json([
                    'status' => false,
                    'message' => 'No active plan found. Please purchase a plan first.',
                ], 400);
            }

            $allowedTemplateCount = $activePackage->resolvedTemplateLimit();
            $currentTemplateCount = Template::where('userId', $userId)
                ->where('type', 'custom')
                ->count();

            if ($currentTemplateCount >= $allowedTemplateCount) {
                return response()->json([
                    'status' => false,
                    'message' => "You can only create {$allowedTemplateCount} custom templates as per your plan.",
                ], 403);
            }

            $exists = Template::where('name', $request->name)
                ->where('userId', $userId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => "Template '{$request->name}' already exists for your account",
                ], 400);
            }

            $components = [
                ['type' => 'BODY', 'text' => $request->components],
            ];

            $template = Template::create([
                'name' => $request->name,
                'language' => $request->language,
                'category' => $request->category,
                'content' => $request->components,
                'type' => 'custom',
                'status' => 'PENDING',
                'components' => $components,
                'userId' => $userId,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Custom template added successfully',
                'data' => $this->decorate($template),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Edit custom template
     */
    public function editCustomTemplate(Request $request, $templateId)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'language' => 'required|string',
            'category' => 'required|string',
            'components' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $userId = $this->resolveOwnerId($request);

            $template = $this->scopedQuery($request)
                ->where('type', 'custom')
                ->find($templateId);

            if (! $template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Custom template not found',
                ], 404);
            }

            $exists = Template::where('name', $request->name)
                ->where('userId', $userId)
                ->where('id', '!=', $templateId)
                ->exists();

            if ($exists) {
                return response()->json([
                    'status' => false,
                    'message' => "Template '{$request->name}' already exists for your account",
                ], 400);
            }

            $components = [
                ['type' => 'BODY', 'text' => $request->components],
            ];

            $template->update([
                'name' => $request->name,
                'language' => $request->language,
                'category' => $request->category,
                'content' => $request->components,
                'components' => $components,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Custom template updated successfully',
                'data' => $this->decorate($template->fresh()),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete custom template
     */
    public function deleteCustomTemplate(Request $request, $templateId)
    {
        try {
            $template = $this->scopedQuery($request)
                ->where('type', 'custom')
                ->find($templateId);

            if (! $template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Custom template not found',
                ], 404);
            }

            $template->delete();

            return response()->json([
                'status' => true,
                'message' => 'Custom template deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user template by ID
     */
    public function getUserTemplateById(Request $request, $templateId)
    {
        try {
            $template = $this->findOwnedTemplate($request, $templateId);

            if (! $template) {
                return response()->json([
                    'status' => false,
                    'message' => 'Template not found',
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $this->decorate($template),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync templates from WhatsApp Business API into THIS user's library
     */
    public function syncTemplates(Request $request)
    {
        try {
            $userId = $this->resolveOwnerId($request);
            $settings = $this->userSettings($request);

            if (! $settings) {
                return response()->json([
                    'status' => false,
                    'message' => 'Active WhatsApp settings not found',
                ], 404);
            }

            $wabaId = $settings->waba_id;
            $accessToken = $settings->access_token;

            if (! $wabaId || ! $accessToken) {
                return response()->json([
                    'status' => false,
                    'message' => 'WhatsApp configuration incomplete',
                ], 400);
            }

            $url = "https://graph.facebook.com/v18.0/{$wabaId}/message_templates?status=APPROVED";

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->get($url);

            if (! $response->successful()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to fetch templates from WhatsApp API',
                    'error' => $response->body(),
                ], 400);
            }

            $templatesData = $response->json();
            $templates = $templatesData['data'] ?? [];

            if (empty($templates)) {
                return response()->json([
                    'status' => true,
                    'message' => 'No templates found',
                    'count' => 0,
                    'meta' => ['userId' => $userId],
                ]);
            }

            $syncedCount = 0;

            foreach ($templates as $templateData) {
                $template = Template::updateOrCreate(
                    [
                        'name' => $templateData['name'],
                        'language' => $templateData['language'] ?? 'en_US',
                        'userId' => $userId,
                    ],
                    [
                        'name' => $templateData['name'],
                        'language' => $templateData['language'] ?? 'en_US',
                        'category' => $templateData['category'] ?? 'UTILITY',
                        'status' => $templateData['status'] ?? 'APPROVED',
                        'components' => $templateData['components'] ?? [],
                        'type' => 'system',
                        'userId' => $userId,
                    ]
                );

                if ($template->wasRecentlyCreated || $template->wasChanged()) {
                    $syncedCount++;
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Templates synced successfully',
                'count' => $syncedCount,
                'total_fetched' => count($templates),
                'meta' => ['userId' => $userId],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error syncing templates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create WhatsApp template (stored under this user)
     */
    public function createWhatsAppTemplate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'language' => 'required|string',
            'category' => 'required|string|in:UTILITY,MARKETING,AUTHENTICATION',
            'components' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $userId = $this->resolveOwnerId($request);
            $settings = $this->userSettings($request);

            if (! $settings) {
                return response()->json([
                    'status' => false,
                    'message' => 'Active WhatsApp settings not found',
                ], 404);
            }

            $wabaId = $settings->waba_id;
            $accessToken = $settings->access_token;

            if (! $wabaId || ! $accessToken) {
                return response()->json([
                    'status' => false,
                    'message' => 'WhatsApp configuration incomplete',
                ], 400);
            }

            $url = "https://graph.facebook.com/v18.0/{$wabaId}/message_templates";

            $response = Http::withHeaders([
                'Authorization' => "Bearer {$accessToken}",
                'Content-Type' => 'application/json',
            ])->post($url, [
                'name' => $request->name,
                'language' => $request->language,
                'category' => $request->category,
                'components' => $request->components,
            ]);

            if (! $response->successful()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Failed to create template via WhatsApp API',
                    'error' => $response->body(),
                ], 400);
            }

            $templateData = $response->json();

            $template = Template::create([
                'name' => $request->name,
                'language' => $request->language,
                'category' => $request->category,
                'status' => 'PENDING',
                'components' => $request->components,
                'type' => 'system',
                'userId' => $userId,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'WhatsApp template created successfully',
                'data' => [
                    'template' => $this->decorate($template),
                    'whatsapp_response' => $templateData,
                ],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error creating WhatsApp template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get templates by status (user-wise; admin sees pending requests)
     */
    public function getTemplatesByStatus(Request $request)
    {
        try {
            $userId = $this->resolveOwnerId($request);
            $isAdmin = strtolower((string) ($request->user()->role ?? '')) === 'admin';

            if ($isAdmin && ! $request->filled('userId') && ! $request->filled('user_id') && ! $request->boolean('mine')) {
                // Admin review queue: pending request-type templates across users
                $query = Template::query()
                    ->where('status', 'PENDING')
                    ->where('type', 'request');
            } else {
                $query = Template::where('userId', $userId)
                    ->whereIn('status', ['PENDING', 'APPROVED', 'REJECTED']);
            }

            $templates = $query->orderBy('created_at', 'desc')->get();
            $templates->transform(fn ($template) => $this->decorate($template));

            return response()->json([
                'status' => true,
                'count' => $templates->count(),
                'meta' => [
                    'userId' => $userId,
                ],
                'data' => $templates,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
