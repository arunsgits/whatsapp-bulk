<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Package;
use App\Models\ActivePackage;
use App\Models\UserPackageHistory;
use App\Models\Settings;
use App\Models\Contact;
use App\Models\Group;
use App\Models\WhatsappCampaign;
use App\Models\BulkMessage;
use App\Models\WalletTransaction;
use App\Services\MetaPhoneNumberRegistrationService;
use App\Services\MetaWabaSubscribeService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AdminUserController extends Controller
{
    /**
     * List Admin Users (with pagination, search, status, and plan filters)
     * GET /api/v1/users or /api/v1/admin/users
     */
    public function index(Request $request)
    {
        try {
            $page = max(1, (int) $request->input('page', 1));
            $limit = max(1, (int) $request->input('limit', 10));
            $search = $request->input('search');
            $status = $request->input('status');
            $planFilter = $request->input('plan');
            $roleFilter = $request->input('role', 'admin');

            $query = User::query()->where('role', $roleFilter);

            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('number', 'like', "%{$search}%")
                      ->orWhere('companyName', 'like', "%{$search}%");
                });
            }

            if ($status) {
                $query->where('status', $status);
            }

            if ($planFilter) {
                $query->where(function ($q) use ($planFilter) {
                    $q->whereHas('activePackage.package', function ($sub) use ($planFilter) {
                        $sub->where('packageName', 'like', "%{$planFilter}%");
                    })->orWhereHas('packageHistories', function ($sub) use ($planFilter) {
                        $sub->where('packageName', 'like', "%{$planFilter}%");
                    });

                    if (stripos($planFilter, 'custom') !== false) {
                        $q->orWhereHas('activePackage', function ($sub) {
                            $sub->whereNotNull('custom_price')
                                ->orWhereNotNull('custom_msg_count')
                                ->orWhereNotNull('custom_contact_count')
                                ->orWhereNotNull('custom_template_count');
                        });
                    }
                });
            }

            $total = $query->count();
            $totalPages = (int) ceil($total / $limit);

            $usersCollection = $query->with([
                    'activePackage.package',
                    'packageHistories' => function ($q) {
                        $q->orderBy('id', 'desc');
                    }
                ])
                ->orderBy('created_at', 'desc')
                ->skip(($page - 1) * $limit)
                ->take($limit)
                ->get();

            $userIds = $usersCollection->pluck('id')->toArray();
            $settingsMap = Settings::whereIn('userId', $userIds)->get()->keyBy('userId');

            $users = $usersCollection->map(function ($user) use ($settingsMap) {
                    $activePkg = $user->activePackage;
                    $pkg = $activePkg ? $activePkg->package : null;
                    $isCustom = $activePkg ? (bool) $activePkg->is_custom : false;

                    $latestHistory = ($user->relationLoaded('packageHistories') && $user->packageHistories->isNotEmpty())
                        ? $user->packageHistories->first()
                        : null;
                    $customPlanName = ($isCustom && $latestHistory) ? $latestHistory->packageName : null;
                    $displayName = ($isCustom && $customPlanName)
                        ? $customPlanName
                        : ($pkg ? $pkg->packageName : ($isCustom ? 'Custom Plan' : 'Standard'));

                    $startDate = $activePkg && $activePkg->startDate ? Carbon::parse($activePkg->startDate) : null;
                    $endDate = $activePkg && $activePkg->endDate ? Carbon::parse($activePkg->endDate) : null;
                    $renewalDate = $activePkg ? ($activePkg->nextRenewalDate() ?: ($endDate ?: null)) : null;
                    $daysRemaining = $endDate ? (int) max(0, ceil(now()->diffInDays($endDate, false))) : 0;

                    $baseMsgCount = $pkg ? (int) $pkg->msgCount : 0;
                    $totalMessages = ($activePkg && isset($activePkg->custom_msg_count) && $activePkg->custom_msg_count !== null && $activePkg->custom_msg_count > 0)
                        ? (int) $activePkg->custom_msg_count
                        : $baseMsgCount;
                    $addonMsgCount = (int) max(0, $totalMessages - $baseMsgCount);

                    $baseContactCount = $pkg ? (int) ($pkg->contactCount ?? $pkg->contact_count ?? 150000) : 0;
                    $totalContactsLimit = ($activePkg && isset($activePkg->custom_contact_count) && $activePkg->custom_contact_count !== null && $activePkg->custom_contact_count > 0)
                        ? (int) $activePkg->custom_contact_count
                        : $baseContactCount;
                    $addonContactCount = (int) max(0, $totalContactsLimit - $baseContactCount);

                    $baseTemplateCount = $pkg ? (int) ($pkg->templateCount ?? $pkg->template_count ?? 0) : 0;
                    $totalTemplateLimit = ($activePkg && isset($activePkg->custom_template_count) && $activePkg->custom_template_count !== null && $activePkg->custom_template_count > 0)
                        ? (int) $activePkg->custom_template_count
                        : ($activePkg ? $activePkg->resolvedTemplateLimit() : $baseTemplateCount);

                    $basePrice = $pkg ? (float) $pkg->price : 0.00;
                    $effectivePrice = ($activePkg && isset($activePkg->custom_price) && $activePkg->custom_price !== null)
                        ? (float) $activePkg->custom_price
                        : $basePrice;

                    $totalStaffCount = User::where('parent_id', $user->id)->count();
                    $totalContactsCount = Contact::where('userId', $user->id)->count();
                    $totalCampaignsCount = WhatsappCampaign::where('userId', $user->id)->count() + BulkMessage::where('userId', $user->id)->count();

                    $uSettings = $settingsMap->get($user->id);
                    $hasWabaCreds = $uSettings 
                        && !empty($uSettings->phoneNumberId) 
                        && !empty($uSettings->access_token);
                    $whatsappStatus = !$uSettings 
                        ? 'pending' 
                        : (!$hasWabaCreds ? 'pending' : ($uSettings->isActive ? 'live' : 'inactive'));

                    return [
                        'id' => 'usr_' . $user->id,
                        'dbId' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                        'phone' => $user->number,
                        'number' => $user->number,
                        'role' => $user->role,
                        'companyName' => $user->companyName ?? '',
                        'companyLogo' => $user->companyLogo ?? '',
                        'companyLogoUrl' => $user->company_logo_url ?? '',
                        'companyMobile' => $user->companyMobile ?? '',
                        'companyEmail' => $user->companyEmail ?? '',
                        'companyAddress' => $user->companyAddress ?? '',
                        'profileImage' => $user->profileImage ?? '',
                        'profileImageUrl' => $user->profile_image_url ?? '',
                        'bio' => $user->bio ?? '',
                        'packageName' => $displayName,
                        'packageId' => $activePkg ? $activePkg->packageId : null,
                        'activePackageId' => $activePkg ? $activePkg->id : null,
                        'isCustom' => $isCustom,
                        'customPlanName' => $isCustom ? ($customPlanName ?: $displayName) : null,
                        'customPrice' => $isCustom ? $effectivePrice : null,
                        'custom_msg_count' => $activePkg ? $activePkg->custom_msg_count : null,
                        'custom_contact_count' => $activePkg ? $activePkg->custom_contact_count : null,
                        'custom_template_count' => $activePkg ? $activePkg->custom_template_count : null,
                        'password' => $user->temp_password ?? 'Password123!',
                        'status' => $user->status,
                        'packageStatus' => $activePkg ? $activePkg->computedStatus() : 0,
                        'packageStatusLabel' => $activePkg ? $activePkg->statusLabel() : 'expired',
                        'walletEnabled' => (bool) ($user->wallet_enabled ?? false),
                        'walletBalance' => round((float) ($user->wallet_balance ?? 0), 2),
                        'startDate' => $startDate ? $startDate->toIso8601String() : null,
                        'endDate' => $endDate ? $endDate->toIso8601String() : null,
                        'renewalDate' => $renewalDate ? $renewalDate->toIso8601String() : null,
                        'daysRemaining' => $daysRemaining,
                        'days' => $activePkg ? (int) $activePkg->day : 0,

                        'baseMsgCount' => $baseMsgCount,
                        'addonMsgCount' => $addonMsgCount,
                        'msgCount' => $totalMessages,

                        'baseContactCount' => $baseContactCount,
                        'addonContactCount' => $addonContactCount,
                        'contactCount' => $totalContactsLimit,

                        'baseTemplateCount' => $baseTemplateCount,
                        'templateCount' => $totalTemplateLimit,

                        'basePrice' => $basePrice,
                        'price' => $effectivePrice,

                        'totalStaff' => $totalStaffCount,
                        'totalContacts' => $totalContactsCount,
                        'totalCampaigns' => $totalCampaignsCount,
                        'permissions' => $this->formatPermissionsObj($this->resolvePermissionsArray($user)),
                        'isWhatsappConfigured' => (bool) $hasWabaCreds,
                        'whatsappStatus' => $whatsappStatus,
                        'createdAt' => $user->created_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $users,
                'meta' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => $totalPages ?: 1,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error listing admin users: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Admin User Details
     * GET /api/v1/users/{userId} or /api/v1/admin/users/{userId}
     */
    public function show(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::with(['activePackage.package'])->find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin user not found'
                ], 404);
            }

            $activePkg = $user->activePackage;
            $pkg = $activePkg ? $activePkg->package : null;

            // Dates & Durations
            $startDate = $activePkg && $activePkg->startDate ? Carbon::parse($activePkg->startDate) : null;
            $endDate = $activePkg && $activePkg->endDate ? Carbon::parse($activePkg->endDate) : null;
            $renewalDate = $activePkg ? ($activePkg->nextRenewalDate() ?: ($endDate ?: null)) : null;

            $daysRemaining = $endDate ? (int) max(0, ceil(now()->diffInDays($endDate, false))) : 0;

            // Message Quotas & Addon Breakdown
            $baseMsgCount = $pkg ? (int) $pkg->msgCount : 0;
            $totalMessages = 0;
            if ($activePkg && isset($activePkg->custom_msg_count) && $activePkg->custom_msg_count !== null && $activePkg->custom_msg_count > 0) {
                $totalMessages = (int) $activePkg->custom_msg_count;
            } elseif ($pkg) {
                $totalMessages = $baseMsgCount;
            }
            $addonMsgCount = (int) max(0, $totalMessages - $baseMsgCount);
            $usedMessages = $activePkg ? (int) $activePkg->usedMsgCount : 0;
            $remainingMessages = (int) max(0, $totalMessages - $usedMessages);

            // Contacts Quotas & Addon Breakdown
            $baseContactCount = $pkg ? (int) ($pkg->contactCount ?? $pkg->contact_count ?? 150000) : 0;
            $totalContactLimit = ($activePkg && isset($activePkg->custom_contact_count) && $activePkg->custom_contact_count !== null && $activePkg->custom_contact_count > 0)
                ? (int) $activePkg->custom_contact_count
                : $baseContactCount;
            $addonContactCount = (int) max(0, $totalContactLimit - $baseContactCount);

            // Pricing (package price only; add-ons do not add price)
            $basePrice = $pkg ? (float) $pkg->price : 0.00;
            $totalPrice = ($activePkg && isset($activePkg->custom_price) && $activePkg->custom_price !== null)
                ? (float) $activePkg->custom_price
                : $basePrice;

            // User Entity Metric Counts
            $totalContacts = Contact::where('userId', $user->id)->count();
            $totalGroups = Group::where('userId', $user->id)->count();
            $totalStaff = User::where('parent_id', $user->id)->count();
            $totalCampaigns = WhatsappCampaign::where('userId', $user->id)->count() + BulkMessage::where('userId', $user->id)->count();

            // List of staff members for this admin user
            $staffList = User::where('parent_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($staff) {
                    return [
                        'id' => 'usr_' . $staff->id,
                        'dbId' => $staff->id,
                        'name' => $staff->name,
                        'email' => $staff->email,
                        'phone' => $staff->number,
                        'number' => $staff->number,
                        'role' => $staff->role ?? 'staff',
                        'status' => $staff->status,
                        'profileImage' => $staff->profileImage ?? '',
                        'profileImageUrl' => $staff->profile_image_url ?? '',
                        'createdAt' => $staff->created_at ? $staff->created_at->toIso8601String() : null,
                    ];
                });

            // Fetch user settings if existing in settings table
            $userSettings = Settings::where('userId', $user->id)->first();

            // Available subscription packages
            $allPackages = Package::all()->map(function ($p) {
                return [
                    'id' => $p->id,
                    'packageName' => $p->packageName,
                    'price' => $p->price,
                    'msgCount' => (int) $p->msgCount,
                    'contactCount' => (int) ($p->contactCount ?? $p->contact_count ?? 150000),
                    'day' => (int) $p->day,
                ];
            });

            $isCustom = $activePkg ? (bool) $activePkg->is_custom : false;
            $latestHistory = UserPackageHistory::where('userId', $user->id)->orderByDesc('id')->first();
            $customPlanName = ($isCustom && $latestHistory) ? $latestHistory->packageName : null;
            $planName = ($isCustom && $customPlanName)
                ? $customPlanName
                : ($pkg ? $pkg->packageName : ($isCustom ? 'Custom Plan' : 'Standard'));

            $baseTemplateCount = $pkg ? (int) ($pkg->templateCount ?? $pkg->template_count ?? 0) : 0;
            $totalTemplateLimit = ($activePkg && isset($activePkg->custom_template_count) && $activePkg->custom_template_count !== null && $activePkg->custom_template_count > 0)
                ? (int) $activePkg->custom_template_count
                : ($activePkg ? $activePkg->resolvedTemplateLimit() : $baseTemplateCount);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => 'usr_' . $user->id,
                    'dbId' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->number,
                    'number' => $user->number,
                    'role' => $user->role,
                    'bio' => $user->bio ?? '',
                    'companyName' => $user->companyName ?? '',
                    'companyLogo' => $user->companyLogo ?? '',
                    'companyLogoUrl' => $user->company_logo_url ?? '',
                    'companyMobile' => $user->companyMobile ?? '',
                    'companyEmail' => $user->companyEmail ?? '',
                    'companyAddress' => $user->companyAddress ?? '',
                    'profileImage' => $user->profileImage ?? '',
                    'profileImageUrl' => $user->profile_image_url ?? '',
                    'password' => $user->temp_password ?? 'Password123!',
                    'status' => $user->status,
                    'packageStatus' => $activePkg ? $activePkg->computedStatus() : 0,
                    'packageStatusLabel' => $activePkg ? $activePkg->statusLabel() : 'expired',
                    'walletEnabled' => (bool) ($user->wallet_enabled ?? false),
                    'walletBalance' => round((float) ($user->wallet_balance ?? 0), 2),

                    // Entity metrics
                    'totalContacts' => $totalContacts,
                    'totalGroups' => $totalGroups,
                    'totalStaff' => $totalStaff,
                    'totalCampaigns' => $totalCampaigns,
                    'staff' => $staffList,

                    // Active Plan metrics & quotas
                    'packageName' => $planName,
                    'activePackageId' => $activePkg ? $activePkg->id : null,
                    'packageId' => $activePkg ? $activePkg->packageId : null,
                    'isCustom' => $isCustom,
                    'customPlanName' => $isCustom ? ($customPlanName ?: $planName) : null,
                    'customPrice' => $isCustom ? ($activePkg->custom_price ?? $totalPrice) : null,
                    'custom_msg_count' => $activePkg ? $activePkg->custom_msg_count : null,
                    'custom_contact_count' => $activePkg ? $activePkg->custom_contact_count : null,
                    'custom_template_count' => $activePkg ? $activePkg->custom_template_count : null,
                    'startDate' => $startDate ? $startDate->toIso8601String() : null,
                    'endDate' => $endDate ? $endDate->toIso8601String() : null,
                    'renewalDate' => $renewalDate ? $renewalDate->toIso8601String() : null,
                    'days' => $activePkg ? (int) $activePkg->day : 0,
                    'daysRemaining' => $daysRemaining,

                    'baseMsgCount' => $baseMsgCount,
                    'addonMsgCount' => $addonMsgCount,
                    'msgCount' => $totalMessages,
                    'usedMessages' => $usedMessages,
                    'remainingMessages' => $remainingMessages,

                    'baseContactCount' => $baseContactCount,
                    'addonContactCount' => $addonContactCount,
                    'contactCount' => $totalContactLimit,

                    'baseTemplateCount' => $baseTemplateCount,
                    'templateCount' => $totalTemplateLimit,

                    'basePrice' => $basePrice,
                    'price' => $totalPrice,

                    // Plan Information Structure (matches Frontend modal requirements)
                    'planInformation' => [
                        'activePackageId' => $activePkg ? $activePkg->id : null,
                        'packageId' => $activePkg ? $activePkg->packageId : null,
                        'packageName' => $planName,
                        'isCustom' => $isCustom,
                        'customPlanName' => $isCustom ? ($customPlanName ?: $planName) : null,
                        'customPrice' => $isCustom ? ($activePkg->custom_price ?? $totalPrice) : null,
                        'custom_msg_count' => $activePkg ? $activePkg->custom_msg_count : null,
                        'custom_contact_count' => $activePkg ? $activePkg->custom_contact_count : null,
                        'custom_template_count' => $activePkg ? $activePkg->custom_template_count : null,
                        'status' => $activePkg ? $activePkg->computedStatus() : 0,
                        'statusLabel' => $activePkg ? $activePkg->statusLabel() : 'expired',
                        'isActive' => $activePkg ? $activePkg->isCurrentlyActive() : false,
                        'isUpcoming' => $activePkg ? $activePkg->isUpcoming() : false,
                        'accountStatus' => ucfirst($user->status),
                        'startDate' => $startDate ? $startDate->toIso8601String() : null,
                        'endDate' => $endDate ? $endDate->toIso8601String() : null,
                        'renewalDate' => $renewalDate ? $renewalDate->toIso8601String() : null,
                        'days' => $activePkg ? (int) $activePkg->day : 0,
                        'daysRemaining' => $daysRemaining,
                        'baseMsgCount' => $baseMsgCount,
                        'addonMsgCount' => $addonMsgCount,
                        'totalMessages' => $totalMessages,
                        'msgCount' => $totalMessages,
                        'usedMessages' => $usedMessages,
                        'remainingMessages' => $remainingMessages,
                        'baseContactCount' => $baseContactCount,
                        'addonContactCount' => $addonContactCount,
                        'contactCount' => $totalContactLimit,
                        'baseTemplateCount' => $baseTemplateCount,
                        'templateCount' => $totalTemplateLimit,
                        'templateLimit' => $totalTemplateLimit,
                        'basePrice' => $basePrice,
                        'price' => $totalPrice,
                    ],

                    'activePackage' => $activePkg ? $activePkg->toPlanPayload() : null,

                    'availablePackages' => $allPackages,

                    // Subscription Package Assignment History Logs
                    'packageHistory' => UserPackageHistory::where('userId', $user->id)->with('assignedByUser')->orderBy('created_at', 'desc')->get()->map(fn($h) => [
                        'id' => $h->id,
                        'action' => $h->action,
                        'packageName' => $h->packageName,
                        'price' => $h->price,
                        'msgCount' => $h->msgCount,
                        'contactCount' => $h->contactCount,
                        'startDate' => $h->startDate ? $h->startDate->toIso8601String() : null,
                        'endDate' => $h->endDate ? $h->endDate->toIso8601String() : null,
                        'assigned_by' => $h->assignedByUser ? $h->assignedByUser->name : 'Super Admin',
                        'createdAt' => $h->created_at ? $h->created_at->toIso8601String() : null,
                        'notes' => $h->notes,
                    ]),

                    'notes' => 'Admin account managed via Super Admin panel',
                    'fbAppId' => $userSettings?->fb_app_id ?? '',
                    'fbAppSecret' => $userSettings ? ($userSettings->fb_app_secret ? '********' : '') : '',
                    'fbAccessToken' => $userSettings?->access_token ?? '',
                    'wabaAccountId' => $userSettings?->waba_id ?? '',
                    'phoneId' => $userSettings?->phoneNumberId ?? '',
                    'webhookUrl' => $userSettings?->webhook_url ?? (config('app.url') . '/api/v1/webhook/usr_' . $user->id),
                    'first_time_message' => $userSettings?->first_time_message ?? '',
                    'auto_ai_enabled' => (bool) ($userSettings?->auto_ai_enabled ?? false),
                    'business_name' => $userSettings?->business_name ?? '',
                    'business_type' => $userSettings?->business_type ?? '',
                    'business_details' => $userSettings?->business_details ?? '',
                    'business_hours' => $userSettings?->business_hours ?? '',
                    'auto_ai_tone' => $userSettings?->auto_ai_tone ?? 'friendly',
                    'apiKey' => 'fa_live_sk_usr_' . $user->id . '_' . substr(md5($user->created_at), 0, 8),
                    'isActive' => $userSettings ? (bool) $userSettings->isActive : false,
                    'is_configured' => (bool) ($userSettings && !empty($userSettings->phoneNumberId) && !empty($userSettings->access_token)),
                    'credentials_status' => !$userSettings ? 'pending' : ((!empty($userSettings->phoneNumberId) && !empty($userSettings->access_token)) ? ($userSettings->isActive ? 'live' : 'inactive') : 'pending'),
                    'permissions' => $this->formatPermissionsObj($this->resolvePermissionsArray($user)),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user details: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add New Admin User
     * POST /api/v1/users or /api/v1/admin/users
     */
    public function store(Request $request)
    {
        $phoneNumber = $request->input('phone', $request->input('number'));
        $password = $request->input('password', $request->input('tempPassword'));

        $validator = Validator::make(array_merge($request->all(), [
            'number' => $phoneNumber,
            'password_check' => $password
        ]), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'number' => 'required|string|unique:users,number',
            'password_check' => 'required|string|min:6',
            'companyName' => 'nullable|string',
            'companyMobile' => 'nullable|string',
            'companyEmail' => 'nullable|email',
            'companyAddress' => 'nullable|string',
            'bio' => 'nullable|string',
            'plan' => 'nullable',
            'status' => 'nullable|string',
            'role' => 'nullable|string',
        ], [
            'password_check.required' => 'The password field is required when creating a role admin user.',
            'password_check.min' => 'The password must be at least 6 characters.',
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

            // Handle Profile Image Upload
            $profileImagePath = null;
            if ($request->hasFile('profileImage')) {
                $file = $request->file('profileImage');
                $filename = time() . '_profile_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $destinationPath = public_path('uploads/profiles');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                $file->move($destinationPath, $filename);
                $profileImagePath = $isProduction ? '/public/uploads/profiles/' . $filename : '/uploads/profiles/' . $filename;
            } elseif ($request->filled('profileImage') && is_string($request->input('profileImage'))) {
                $profileImagePath = $request->input('profileImage');
            }

            // Handle Company Logo Upload
            $companyLogoPath = null;
            if ($request->hasFile('companyLogo')) {
                $file = $request->file('companyLogo');
                $filename = time() . '_logo_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $destinationPath = public_path('uploads/company');
                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }
                $file->move($destinationPath, $filename);
                $companyLogoPath = $isProduction ? '/public/uploads/company/' . $filename : '/uploads/company/' . $filename;
            } elseif ($request->filled('companyLogo') && is_string($request->input('companyLogo'))) {
                $companyLogoPath = $request->input('companyLogo');
            }

            // Handle Permissions
            $permissions = $this->parsePermissionsInput($request->input('permissions'));

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'number' => $phoneNumber,
                'password' => Hash::make($password),
                'temp_password' => $password,
                'role' => $request->input('role', 'admin'),
                'status' => $request->input('status', 'active'),
                'bio' => $request->input('bio'),
                'companyName' => $request->input('companyName'),
                'companyLogo' => $companyLogoPath ?: '/uploads/company/default.png',
                'companyMobile' => $request->input('companyMobile'),
                'companyEmail' => $request->input('companyEmail'),
                'companyAddress' => $request->input('companyAddress'),
                'profileImage' => $profileImagePath ?: '/uploads/default.png',
                'wallet_enabled' => $this->parseBool($request->input('walletEnabled', $request->input('wallet_enabled', false))),
                'permissions' => $permissions,
            ]);

            // Find plan or default first plan
            $planInput = $request->input('plan');
            $package = null;
            if ($planInput) {
                $package = Package::where('id', $planInput)
                    ->orWhere('packageName', 'like', "%{$planInput}%")
                    ->first();
            }
            if (!$package) {
                $package = Package::first();
            }

            if ($package) {
                $days = $package->day ?: 30;
                $activeData = [
                    'userId' => $user->id,
                    'packageId' => $package->id,
                    'startDate' => now(),
                    'endDate' => now()->addDays($days),
                    'day' => $days,
                    'status' => 1,
                    'usedMsgCount' => 0,
                ];
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'lastMonthlyReset')) {
                    $activeData['lastMonthlyReset'] = now();
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedMsgCount')) {
                    $activeData['monthlyUsedMsgCount'] = 0;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedTemplateCount')) {
                    $activeData['monthlyUsedTemplateCount'] = 0;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedContactCount')) {
                    $activeData['monthlyUsedContactCount'] = 0;
                }
                $activePkg = ActivePackage::create($activeData);
                $this->logPackageHistory($user, $activePkg, 'created', $request->user()?->id, 'Initial package assigned on account creation');
            }

            // Save user settings credentials if provided
            $wabaSubscribe = null;
            $storeSettingsInput = array_filter([
                'fb_app_id' => $request->input('fbAppId'),
                'fb_app_secret' => $request->input('fbAppSecret'),
                'access_token' => $request->input('fbAccessToken'),
                'waba_id' => $request->input('wabaAccountId'),
                'phoneNumberId' => $request->input('phoneId'),
                'first_time_message' => $request->input('first_time_message', $request->input('firstTimeMessage')),
                'business_name' => $request->input('business_name', $request->input('businessName')),
                'business_type' => $request->input('business_type', $request->input('businessType')),
                'business_details' => $request->input('business_details', $request->input('businessDetails')),
                'business_hours' => $request->input('business_hours', $request->input('businessHours')),
                'auto_ai_tone' => $request->input('auto_ai_tone', $request->input('autoAiTone')),
            ], fn ($value) => $value !== null && $value !== '');

            if ($request->has('auto_ai_enabled') || $request->has('autoAiEnabled')) {
                $storeSettingsInput['auto_ai_enabled'] = (bool) $request->input('auto_ai_enabled', $request->input('autoAiEnabled'));
            }

            if (!empty($storeSettingsInput)) {
                $storeSettingsInput['webhook_url'] = url('/api/whatsapp/webhook/' . $user->id);
                $storeSettingsInput['isActive'] = true;
                $userSettings = Settings::updateOrCreate(
                    ['userId' => $user->id],
                    $storeSettingsInput
                );

                if ($request->hasAny(['fbAppId', 'fbAppSecret', 'fbAccessToken', 'wabaAccountId', 'phoneId'])) {
                    $wabaSubscribe = app(MetaWabaSubscribeService::class)->subscribe($userSettings->fresh());
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Admin user created successfully',
                'wabaSubscribe' => $wabaSubscribe,
                'data' => [
                    'id' => 'usr_' . $user->id,
                    'dbId' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->number,
                    'role' => $user->role,
                    'companyName' => $user->companyName ?? '',
                    'companyLogo' => $user->companyLogo ?? '',
                    'companyLogoUrl' => $user->company_logo_url ?? '',
                    'companyMobile' => $user->companyMobile ?? '',
                    'companyEmail' => $user->companyEmail ?? '',
                    'companyAddress' => $user->companyAddress ?? '',
                    'profileImage' => $user->profileImage ?? '',
                    'profileImageUrl' => $user->profile_image_url ?? '',
                    'plan' => $package ? $package->packageName : 'Standard',
                    'status' => $user->status,
                    'walletEnabled' => (bool) ($user->wallet_enabled ?? false),
                    'walletBalance' => 0,
                    'totalStaff' => 0,
                    'createdAt' => $user->created_at->toIso8601String(),
                ]
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating admin user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Edit/Update Admin User
     * PUT /api/v1/users/{userId} or /api/v1/admin/users/{userId}
     */
    public function update(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Parse raw PUT request multipart data if $_FILES is empty
            $this->parseRawPutMultipart($request);

            $phoneNumber = $request->input('phone', $request->input('number', $user->number));

            $validator = Validator::make(array_merge($request->all(), ['number' => $phoneNumber]), [
                'name' => 'nullable|string|max:255',
                'email' => 'nullable|email|unique:users,email,' . $user->id,
                'number' => 'nullable|string|unique:users,number,' . $user->id,
                'password' => 'nullable|string|min:6',
                'tempPassword' => 'nullable|string|min:6',
                'status' => 'nullable|string',
                'role' => 'nullable|string',
                'companyName' => 'nullable|string',
                'companyMobile' => 'nullable|string',
                'companyEmail' => 'nullable|email',
                'companyAddress' => 'nullable|string',
                'bio' => 'nullable|string',
                'plan' => 'nullable',
                'profileImage' => 'nullable',
                'companyLogo' => 'nullable',
                'walletEnabled' => 'nullable',
                'wallet_enabled' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $updateData = [];

            if ($request->filled('name')) {
                $updateData['name'] = $request->input('name');
            }
            if ($request->filled('email')) {
                $updateData['email'] = $request->input('email');
            }
            if ($phoneNumber) {
                $updateData['number'] = $phoneNumber;
            }
            if ($request->filled('status')) {
                $updateData['status'] = $request->input('status');
            }
            if ($request->has('bio')) {
                $updateData['bio'] = $request->input('bio');
            }
            if ($request->has('companyName')) {
                $updateData['companyName'] = $request->input('companyName');
            }
            if ($request->has('companyMobile')) {
                $updateData['companyMobile'] = $request->input('companyMobile');
            }
            if ($request->has('companyEmail')) {
                $updateData['companyEmail'] = $request->input('companyEmail');
            }
            if ($request->has('companyAddress')) {
                $updateData['companyAddress'] = $request->input('companyAddress');
            }
            if ($request->filled('role')) {
                $updateData['role'] = $request->input('role');
            } else {
                $updateData['role'] = $user->role ?: 'admin';
            }
            if ($request->exists('walletEnabled') || $request->exists('wallet_enabled')) {
                $updateData['wallet_enabled'] = $this->parseBool($request->input('walletEnabled', $request->input('wallet_enabled')));
            }

            // Handle Password Update if password or tempPassword is provided
            $newPassword = $request->input('password', $request->input('tempPassword'));
            if ($newPassword && strlen($newPassword) > 0) {
                $updateData['password'] = Hash::make($newPassword);
                $updateData['temp_password'] = $newPassword;
            }

            $isProduction = app()->isProduction();

            // Handle Profile Image Upload / Base64 / Path
            $profileImagePath = $this->processImageInput($request, 'profileImage', 'profiles', $user->id, $isProduction);
            if ($profileImagePath !== null) {
                $updateData['profileImage'] = $profileImagePath;
            }

            // Handle Company Logo Upload / Base64 / Path
            $companyLogoPath = $this->processImageInput($request, 'companyLogo', 'company', $user->id, $isProduction);
            if ($companyLogoPath !== null) {
                $updateData['companyLogo'] = $companyLogoPath;
            }

            // Handle Permissions
            if ($request->has('permissions')) {
                $parsedPermissions = $this->parsePermissionsInput($request->input('permissions'));
                if (is_array($parsedPermissions)) {
                    $updateData['permissions'] = $parsedPermissions;
                }
            }

            if (!empty($updateData)) {
                $user->update($updateData);

                // If super admin updates user role "admin" status, update all staff of that admin user
                if (isset($updateData['status']) && ($user->isAdmin() || strtolower($user->role) === 'admin')) {
                    User::where('parent_id', $user->id)->update(['status' => $updateData['status']]);
                }
            }

            // Update or customize active plan if plan, dates, or message limit parameters are provided
            if ($request->hasAny(['plan', 'packageId', 'startDate', 'start_date', 'endDate', 'end_date', 'msgCount', 'custom_msg_count', 'messageLimit', 'totalMessages', 'allocatedMessages', 'usedMsgCount', 'usedMessages'])) {
                $this->syncUserActivePackage($user, $request);
            }

            // Update user settings if credentials or business/Auto-AI settings passed
            $wabaSubscribe = null;
            $settingsInput = array_filter([
                'fb_app_id' => $request->input('fbAppId'),
                'fb_app_secret' => $request->input('fbAppSecret'),
                'access_token' => $request->input('fbAccessToken'),
                'waba_id' => $request->input('wabaAccountId'),
                'phoneNumberId' => $request->input('phoneId'),
                'first_time_message' => $request->input('first_time_message', $request->input('firstTimeMessage')),
                'business_name' => $request->input('business_name', $request->input('businessName')),
                'business_type' => $request->input('business_type', $request->input('businessType')),
                'business_details' => $request->input('business_details', $request->input('businessDetails')),
                'business_hours' => $request->input('business_hours', $request->input('businessHours')),
                'auto_ai_tone' => $request->input('auto_ai_tone', $request->input('autoAiTone')),
            ], fn ($value) => $value !== null && $value !== '');

            if ($request->has('auto_ai_enabled') || $request->has('autoAiEnabled')) {
                $settingsInput['auto_ai_enabled'] = (bool) $request->input('auto_ai_enabled', $request->input('autoAiEnabled'));
            }

            if (!empty($settingsInput)) {
                $settingsInput['webhook_url'] = url('/api/whatsapp/webhook/' . $user->id);
                $userSettings = Settings::updateOrCreate(
                    ['userId' => $user->id],
                    $settingsInput
                );

                if ($request->hasAny(['fbAppId', 'fbAppSecret', 'fbAccessToken', 'wabaAccountId', 'phoneId'])) {
                    $wabaSubscribe = app(MetaWabaSubscribeService::class)->subscribe($userSettings->fresh());
                }
            }

            $user->refresh();
            $activePkg = $user->activePackage;
            $pkg = $activePkg ? $activePkg->package : null;

            return response()->json([
                'success' => true,
                'message' => 'Admin user updated successfully',
                'wabaSubscribe' => $wabaSubscribe,
                'data' => [
                    'id' => 'usr_' . $user->id,
                    'dbId' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->number,
                    'role' => $user->role,
                    'companyName' => $user->companyName ?? '',
                    'companyLogo' => $user->companyLogo ?? '',
                    'companyLogoUrl' => $user->company_logo_url ?? '',
                    'companyMobile' => $user->companyMobile ?? '',
                    'companyEmail' => $user->companyEmail ?? '',
                    'companyAddress' => $user->companyAddress ?? '',
                    'profileImage' => $user->profileImage ?? '',
                    'profileImageUrl' => $user->profile_image_url ?? '',
                    'bio' => $user->bio ?? '',
                    'plan' => $pkg->packageName ?? 'Standard',
                    'status' => $user->status,
                    'walletEnabled' => (bool) ($user->wallet_enabled ?? false),
                    'walletBalance' => round((float) ($user->wallet_balance ?? 0), 2),
                    'totalStaff' => User::where('parent_id', $user->id)->count(),
                    'updatedAt' => $user->updated_at->toIso8601String(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating admin user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user status (active / inactive / pending)
     * POST or PUT /api/v1/admin/users/{userId}/status
     */
    public function updateStatus(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $status = $request->input('status');
            if (!$status) {
                // Toggle status if not explicitly passed
                $status = $user->status === 'active' ? 'inactive' : 'active';
            }

            if (!in_array($status, ['active', 'inactive', 'pending'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid status value. Allowed: active, inactive, pending'
                ], 400);
            }

            $user->update(['status' => $status]);

            // If super admin updates user role "admin" status, update all staff of that admin user
            if ($user->isAdmin() || strtolower($user->role) === 'admin') {
                User::where('parent_id', $user->id)->update(['status' => $status]);
            }

            return response()->json([
                'success' => true,
                'message' => "User status updated to '{$status}' successfully",
                'data' => [
                    'id' => 'usr_' . $user->id,
                    'dbId' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->status,
                    'role' => $user->role
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating user status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete Admin User
     * DELETE /api/v1/users/{userId} or /api/v1/admin/users/{userId}
     */
    public function destroy(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Delete associated active package and user record
            ActivePackage::where('userId', $user->id)->delete();
            $user->delete();

            return response()->json([
                'success' => true,
                'message' => 'Admin user and associated data successfully deleted'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting admin user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Renew Admin User Subscription
     * POST /api/v1/users/{userId}/renew or /api/v1/admin/users/{userId}/renew
     */
    public function renew(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // If payment link requested by admin:
            if ($request->boolean('payment_link') || $request->boolean('generate_payment_link') || $request->input('action') === 'payment_link') {
                $packageId = $request->input('packageId', $request->input('package_id'));
                $walletService = app(WalletService::class);
                $result = $walletService->createRenewalPaymentLink($user, $packageId, [
                    'callback_url' => $request->input('callbackUrl', $request->input('callback_url')),
                    'back_url' => $request->input('back_url', $request->input('backUrl', $request->input('return_url', $request->input('cancel_url')))),
                    'billing_state' => $request->input('billing_state', $request->input('billingState', $request->input('state', $request->input('customer_state')))),
                    'custom_price' => $request->input('custom_price', $request->input('customPrice', $request->input('renewal_price', $request->input('renewalPrice', $request->input('price'))))),
                    'custom_plan_name' => $request->input('custom_plan_name', $request->input('customPlanName', $request->input('plan_name', $request->input('packageName')))),
                    'days' => $request->input('day', $request->input('days', $request->input('daysToAdd', $request->input('days_to_add')))),
                    'msg_count' => $request->input('msg_count', $request->input('msgCount', $request->input('custom_msg_count'))),
                    'contact_count' => $request->input('contact_count', $request->input('contactCount', $request->input('custom_contact_count'))),
                    'template_count' => $request->input('template_count', $request->input('templateCount', $request->input('custom_template_count'))),
                    'is_custom_plan' => $request->boolean('is_custom_plan', $request->boolean('custom_plan', false)),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Renewal payment link generated successfully',
                    'data' => $result
                ], 200);
            }

            $activePkg = ActivePackage::where('userId', $user->id)
                ->with('package')
                ->orderByDesc('id')
                ->first();

            $defaultDays = ($activePkg && ($activePkg->is_yearly || (int)($activePkg->day ?? 0) >= 300)) ? 365 : 30;
            $daysToAdd = (int) ($request->input('daysToAdd') ?: ($request->input('days_to_add') ?: ($request->input('days') ?: $defaultDays)));

            if ($activePkg) {
                $parseCustomDate = function ($dateInput) {
                    if (empty($dateInput)) {
                        return null;
                    }
                    try {
                        $trimmed = trim((string) $dateInput);
                        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $trimmed, $m)) {
                            return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->startOfDay();
                        }

                        return Carbon::parse($trimmed);
                    } catch (\Exception $e) {
                        return null;
                    }
                };

                $parsedStart = $parseCustomDate($request->input('startDate', $request->input('start_date')));
                $parsedEnd = $parseCustomDate($request->input('endDate', $request->input('end_date')));

                if ($parsedStart) {
                    $activePkg->startDate = $parsedStart;
                } elseif (! $activePkg->startDate) {
                    $activePkg->startDate = now();
                }

                if ($parsedEnd) {
                    $newEndDate = $parsedEnd;
                } else {
                    // If old plan already expired, renew from now instead of extending a past date.
                    $renewBase = $activePkg->endDate && Carbon::parse($activePkg->endDate)->gt(now())
                        ? Carbon::parse($activePkg->endDate)
                        : now();
                    $newEndDate = (clone $renewBase)->addDays($daysToAdd);
                }

                $activePkg->endDate = $newEndDate;
                if ($activePkg->startDate) {
                    $activePkg->day = (int) max(1, Carbon::parse($activePkg->startDate)->diffInDays($newEndDate, false));
                }
                $activePkg->status = ActivePackage::statusFromDates($activePkg->startDate, $activePkg->endDate);
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'lastMonthlyReset')) {
                    $activePkg->lastMonthlyReset = now();
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedMsgCount')) {
                    $activePkg->monthlyUsedMsgCount = 0;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedTemplateCount')) {
                    $activePkg->monthlyUsedTemplateCount = 0;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedContactCount')) {
                    $activePkg->monthlyUsedContactCount = 0;
                }
                $activePkg->save();
                $this->logPackageHistory($user, $activePkg, 'renewed', $request->user()?->id, "Subscription renewed for {$daysToAdd} days");
            }

            $user->update(['status' => 'active']);

            // Sync staff members of this admin user to active upon renewal
            if ($user->isAdmin() || strtolower($user->role) === 'admin') {
                User::where('parent_id', $user->id)->update(['status' => 'active']);
            }

            return response()->json([
                'success' => true,
                'message' => "Subscription renewed successfully for {$daysToAdd} days",
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'status' => 'active',
                    'newEndDate' => $activePkg ? $activePkg->endDate->toIso8601String() : null,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error renewing subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate Renewal Payment Link for Admin User
     * POST /api/v1/admin/users/{userId}/renewal-payment-link
     */
    public function generateRenewalPaymentLink(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $packageId = $request->input('packageId', $request->input('package_id'));
            $walletService = app(WalletService::class);
            $result = $walletService->createRenewalPaymentLink($user, $packageId, [
                'callback_url' => $request->input('callbackUrl', $request->input('callback_url')),
                'back_url' => $request->input('back_url', $request->input('backUrl', $request->input('return_url', $request->input('cancel_url')))),
                'billing_state' => $request->input('billing_state', $request->input('billingState', $request->input('state', $request->input('customer_state')))),
                'custom_price' => $request->input('custom_price', $request->input('customPrice', $request->input('renewal_price', $request->input('renewalPrice', $request->input('price'))))),
                'custom_plan_name' => $request->input('custom_plan_name', $request->input('customPlanName', $request->input('plan_name', $request->input('packageName')))),
                'days' => $request->input('day', $request->input('days', $request->input('daysToAdd', $request->input('days_to_add')))),
                'msg_count' => $request->input('msg_count', $request->input('msgCount', $request->input('custom_msg_count'))),
                'contact_count' => $request->input('contact_count', $request->input('contactCount', $request->input('custom_contact_count'))),
                'template_count' => $request->input('template_count', $request->input('templateCount', $request->input('custom_template_count'))),
                'is_custom_plan' => $request->boolean('is_custom_plan', $request->boolean('custom_plan', false)),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Renewal payment link generated successfully',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error generating renewal payment link: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get User Subscription Package Details
     * GET /api/v1/admin/users/{userId}/plan
     */
    public function getUserPlan(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin user not found'
                ], 404);
            }

            $activePkg = ActivePackage::where('userId', $user->id)->with('package')->orderByDesc('id')->first();

            $allPackages = Package::all()->map(function ($p) {
                return [
                    'id' => $p->id,
                    'packageName' => $p->packageName,
                    'price' => $p->price,
                    'renewalPrice' => $p->renewal_price ?: $p->price,
                    'renewal_price' => $p->renewal_price ?: $p->price,
                    'effectiveRenewalPrice' => $p->getEffectiveRenewalPrice(),
                    'period' => $p->normalized_period ?? ($p->day >= 300 ? '/year' : '/month'),
                    'billingPeriod' => $p->billing_period ?? ($p->day >= 300 ? 'yearly' : 'monthly'),
                    'isMonthly' => $p->is_monthly ?? ($p->day <= 90),
                    'isYearly' => $p->is_yearly ?? ($p->day >= 300),
                    'msgCount' => (int) $p->msgCount,
                    'day' => (int) $p->day,
                    'contactCount' => (int) ($p->contactCount ?? $p->contact_count ?? 0),
                ];
            });

            $currentPlan = $activePkg
                ? $activePkg->toPlanPayload()
                : [
                    'activePackageId' => null,
                    'packageId' => null,
                    'packageName' => 'Standard',
                    'msgCount' => 0,
                    'contactCount' => 0,
                    'price' => 0,
                    'usedMsgCount' => 0,
                    'remainingMessages' => 0,
                    'startDate' => null,
                    'endDate' => null,
                    'renewalDate' => null,
                    'days' => 30,
                    'daysRemaining' => 0,
                    'status' => 0,
                    'baseMsgCount' => 0,
                    'addonMsgCount' => 0,
                    'baseContactCount' => 0,
                    'addonContactCount' => 0,
                ];

            return response()->json([
                'success' => true,
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'dbId' => $user->id,
                    'userName' => $user->name,
                    'currentPlan' => $currentPlan,
                    'availablePackages' => $allPackages
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign or Update User Package Plan (Customizable Start Date, End Date, Message Limit, Contacts Limit, Pricing Overrides)
     * POST/PUT /api/v1/admin/users/{userId}/plan
     */
    public function updateUserPlan(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'plan' => 'nullable',
            'packageId' => 'nullable',
            'package_id' => 'nullable',
            'planId' => 'nullable',
            'plan_id' => 'nullable',
            'custom_plan' => 'nullable|boolean',
            'is_custom_plan' => 'nullable|boolean',
            'custom_plan_name' => 'nullable|string',
            'customPlanName' => 'nullable|string',
            'plan_name' => 'nullable|string',
            'planName' => 'nullable|string',
            'name' => 'nullable|string',
            'packageName' => 'nullable|string',
            'packageDesc' => 'nullable|string',
            'description' => 'nullable|string',
            'days' => 'nullable|integer',
            'duration' => 'nullable|integer',
            'duration_days' => 'nullable|integer',
            'durationDays' => 'nullable|integer',
            'startDate' => 'nullable',
            'start_date' => 'nullable',
            'start' => 'nullable',
            'endDate' => 'nullable',
            'end_date' => 'nullable',
            'end' => 'nullable',
            'msgCount' => 'nullable',
            'custom_msg_count' => 'nullable',
            'messageLimit' => 'nullable',
            'totalMessages' => 'nullable',
            'total_messages' => 'nullable',
            'totalMessage' => 'nullable',
            'total_message' => 'nullable',
            'messages' => 'nullable',
            'allocatedMessages' => 'nullable',
            'msgPrice' => 'nullable',
            'messagesPrice' => 'nullable',
            'custom_msg_price' => 'nullable',
            'contactCount' => 'nullable',
            'contactsLimit' => 'nullable',
            'totalContacts' => 'nullable',
            'total_contacts' => 'nullable',
            'totalContact' => 'nullable',
            'total_contact' => 'nullable',
            'contacts' => 'nullable',
            'custom_contact_count' => 'nullable',
            'contactPrice' => 'nullable',
            'contactsPrice' => 'nullable',
            'custom_contact_price' => 'nullable',
            'templateCount' => 'nullable',
            'templatesLimit' => 'nullable',
            'template_limit' => 'nullable',
            'templateLimit' => 'nullable',
            'templates_limit' => 'nullable',
            'templates' => 'nullable',
            'custom_template_count' => 'nullable',
            'templatePrice' => 'nullable',
            'templatesPrice' => 'nullable',
            'custom_template_price' => 'nullable',
            'price' => 'nullable',
            'custom_price' => 'nullable',
            'customPrice' => 'nullable',
            'renewal_price' => 'nullable',
            'renewalPrice' => 'nullable',
            'features' => 'nullable',
            'usedMsgCount' => 'nullable',
            'usedMessages' => 'nullable',
            'reset_used_messages' => 'nullable|boolean',
            'resetUsage' => 'nullable|boolean',
            'notes' => 'nullable|string',
            'note' => 'nullable|string',
            'generate_invoice' => 'nullable|boolean',
            'generateInvoice' => 'nullable|boolean',
            'generate_tax_invoice' => 'nullable|boolean',
            'generateTaxInvoice' => 'nullable|boolean',
            'tax_invoice' => 'nullable|boolean',
            'taxInvoice' => 'nullable|boolean',
            'payment_method' => 'nullable|string',
            'paymentMethod' => 'nullable|string',
            'payment_status' => 'nullable|string',
            'paymentStatus' => 'nullable|string',
            'tax_inclusive' => 'nullable|boolean',
            'gst_percentage' => 'nullable|numeric',
            'invoice_notes' => 'nullable|string',
            'allow_negative_balance' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin user not found'
                ], 404);
            }

            $activePkg = $this->syncUserActivePackage($user, $request);
            $activePkg->load('package');
            $plan = $activePkg->toPlanPayload();

            $type = ($request->input('action') === 'custom_plan' || $request->boolean('is_custom_plan') || $request->boolean('custom_plan')) ? 'custom_plan' : 'subscription';
            $invoiceResult = $this->createPlanInvoiceIfRequested($user, $activePkg, $request, $type);

            $responseData = [
                'userId' => 'usr_' . $user->id,
                'dbId' => $user->id,
                'userName' => $user->name,
                'plan' => $plan['packageName'],
                'packageName' => $plan['packageName'],
                'currentPlan' => $plan,
            ];

            if ($invoiceResult && ($invoiceResult['success'] ?? false)) {
                $responseData['invoice'] = $invoiceResult['invoice'];
                $responseData['transaction'] = $invoiceResult['transaction'];
                $responseData['wallet'] = $invoiceResult['wallet'];
            }

            return response()->json([
                'success' => true,
                'message' => 'User active subscription plan updated successfully',
                'data' => array_merge($responseData, $plan)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating user plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create & Assign Custom Subscription Plan for User
     * POST /api/v1/admin/users/{userId}/custom-plan
     */
    public function assignCustomPlan(Request $request, $userId)
    {
        $merges = [
            'is_custom_plan' => true,
            'action' => $request->input('action', 'custom_plan'),
        ];

        // 1. Plan Name
        $planName = $request->input('custom_plan_name', 
            $request->input('customPlanName', 
            $request->input('plan_name', 
            $request->input('planName', 
            $request->input('name', 
            $request->input('packageName', 
            $request->input('plan')))))));
        if ($planName !== null && $planName !== '') {
            $merges['custom_plan_name'] = $planName;
            $merges['customPlanName'] = $planName;
            $merges['plan_name'] = $planName;
            $merges['packageName'] = $planName;
        }

        // 2. Duration / Days
        $days = $request->input('days', 
            $request->input('duration', 
            $request->input('duration_days', 
            $request->input('durationDays'))));
        if ($days !== null && $days !== '') {
            $merges['days'] = (int) $days;
            $merges['duration'] = (int) $days;
            $merges['duration_days'] = (int) $days;
        }

        // 3. Start Date & End Date
        $startDate = $request->input('start_date', $request->input('startDate', $request->input('start')));
        if ($startDate !== null && $startDate !== '') {
            $merges['start_date'] = $startDate;
            $merges['startDate'] = $startDate;
        }

        $endDate = $request->input('end_date', $request->input('endDate', $request->input('end')));
        if ($endDate !== null && $endDate !== '') {
            $merges['end_date'] = $endDate;
            $merges['endDate'] = $endDate;
        }

        // 4. Price
        $price = $request->input('price', $request->input('custom_price', $request->input('customPrice')));
        if ($price !== null && $price !== '') {
            $merges['price'] = $price;
            $merges['custom_price'] = $price;
        }

        // 5. Total Messages
        $messages = $request->input('total_messages', 
            $request->input('totalMessages', 
            $request->input('total_message', 
            $request->input('totalMessage', 
            $request->input('messages', 
            $request->input('messageLimit', 
            $request->input('custom_msg_count', 
            $request->input('msgCount'))))))));
        if ($messages !== null && $messages !== '') {
            $merges['totalMessages'] = (int) $messages;
            $merges['total_messages'] = (int) $messages;
            $merges['custom_msg_count'] = (int) $messages;
            $merges['msgCount'] = (int) $messages;
        }

        // 6. Total Contacts
        $contacts = $request->input('total_contacts', 
            $request->input('totalContacts', 
            $request->input('total_contact', 
            $request->input('totalContact', 
            $request->input('contacts', 
            $request->input('contactsLimit', 
            $request->input('custom_contact_count', 
            $request->input('contactCount'))))))));
        if ($contacts !== null && $contacts !== '') {
            $merges['totalContacts'] = (int) $contacts;
            $merges['total_contacts'] = (int) $contacts;
            $merges['custom_contact_count'] = (int) $contacts;
            $merges['contactCount'] = (int) $contacts;
        }

        // 7. Template Limit
        $templates = $request->input('template_limit', 
            $request->input('templateLimit', 
            $request->input('templates_limit', 
            $request->input('templatesLimit', 
            $request->input('templates', 
            $request->input('custom_template_count', 
            $request->input('templateCount')))))));
        if ($templates !== null && $templates !== '') {
            $merges['template_limit'] = (int) $templates;
            $merges['templateLimit'] = (int) $templates;
            $merges['custom_template_count'] = (int) $templates;
            $merges['templateCount'] = (int) $templates;
        }

        // 8. Payment Method
        $paymentMethod = $request->input('payment_method', $request->input('paymentMethod'));
        if ($paymentMethod !== null && $paymentMethod !== '') {
            $merges['payment_method'] = $paymentMethod;
            $merges['paymentMethod'] = $paymentMethod;
        }

        // 9. Payment Status
        $paymentStatus = $request->input('payment_status', $request->input('paymentStatus'));
        if ($paymentStatus !== null && $paymentStatus !== '') {
            $merges['payment_status'] = $paymentStatus;
            $merges['paymentStatus'] = $paymentStatus;
        }

        // 10. Notes
        $notes = $request->input('notes', $request->input('note', $request->input('description')));
        if ($notes !== null && $notes !== '') {
            $merges['notes'] = $notes;
        }

        // 11. Generate Tax Invoice
        if ($request->has('generate_tax_invoice') || $request->has('generateTaxInvoice') || $request->has('tax_invoice') || $request->has('taxInvoice') || $request->has('generate_invoice') || $request->has('generateInvoice')) {
            $genInvoice = $request->boolean('generate_tax_invoice')
                || $request->boolean('generateTaxInvoice')
                || $request->boolean('tax_invoice')
                || $request->boolean('taxInvoice')
                || $request->boolean('generate_invoice')
                || $request->boolean('generateInvoice');
            $merges['generate_invoice'] = $genInvoice;
            $merges['generate_tax_invoice'] = $genInvoice;
        } else {
            // Default to true as shown in modal toggle
            $merges['generate_invoice'] = true;
            $merges['generate_tax_invoice'] = true;
        }

        $request->merge($merges);
        return $this->updateUserPlan($request, $userId);
    }

    /**
     * Upgrade Admin User Subscription Plan
     * POST /api/v1/admin/users/{userId}/upgrade-plan
     * POST /api/v1/admin/users/{userId}/upgrade
     */
    public function upgradeUserPlan(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'packageId' => 'nullable',
            'package_id' => 'nullable',
            'planId' => 'nullable',
            'plan_id' => 'nullable',
            'plan' => 'nullable',
            'is_custom_plan' => 'nullable|boolean',
            'custom_plan' => 'nullable|boolean',
            'custom_plan_name' => 'nullable|string',
            'customPlanName' => 'nullable|string',
            'plan_name' => 'nullable|string',
            'packageName' => 'nullable|string',
            'packageDesc' => 'nullable|string',
            'description' => 'nullable|string',
            'days' => 'nullable|integer',
            'duration' => 'nullable|integer',
            'startDate' => 'nullable',
            'start_date' => 'nullable',
            'endDate' => 'nullable',
            'end_date' => 'nullable',
            'msgCount' => 'nullable',
            'custom_msg_count' => 'nullable',
            'messageLimit' => 'nullable',
            'totalMessages' => 'nullable',
            'allocatedMessages' => 'nullable',
            'contactCount' => 'nullable',
            'contactsLimit' => 'nullable',
            'totalContacts' => 'nullable',
            'custom_contact_count' => 'nullable',
            'templateCount' => 'nullable',
            'templatesLimit' => 'nullable',
            'custom_template_count' => 'nullable',
            'price' => 'nullable',
            'custom_price' => 'nullable',
            'renewal_price' => 'nullable',
            'renewalPrice' => 'nullable',
            'features' => 'nullable',
            'notes' => 'nullable|string',
            'extend_duration' => 'nullable|boolean',
            'reset_used_messages' => 'nullable|boolean',
            'resetUsage' => 'nullable|boolean',
            'generate_invoice' => 'nullable|boolean',
            'payment_method' => 'nullable|string',
            'paymentMethod' => 'nullable|string',
            'payment_status' => 'nullable|string',
            'paymentStatus' => 'nullable|string',
            'tax_inclusive' => 'nullable|boolean',
            'gst_percentage' => 'nullable|numeric',
            'invoice_notes' => 'nullable|string',
            'allow_negative_balance' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $rawId = str_replace('usr_', '', (string) $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin user not found'
                ], 404);
            }

            $isCustomPlan = $request->boolean('is_custom_plan')
                || $request->boolean('custom_plan')
                || $request->filled('custom_plan_name')
                || $request->filled('customPlanName');

            // Target package to upgrade to
            $targetPlanInput = $request->input('packageId', 
                $request->input('package_id', 
                $request->input('planId', 
                $request->input('plan_id', 
                $request->input('custom_plan_name',
                $request->input('customPlanName',
                $request->input('plan_name',
                $request->input('plan'))))))));

            if (!$isCustomPlan && ($targetPlanInput === null || $targetPlanInput === '')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Target subscription plan is required (provide packageId or plan)'
                ], 422);
            }

            $newPackage = null;
            if ($targetPlanInput !== null && $targetPlanInput !== '') {
                if (is_numeric($targetPlanInput)) {
                    $newPackage = Package::find((int) $targetPlanInput);
                }
                if (!$newPackage) {
                    $newPackage = Package::where('packageName', 'like', "%{$targetPlanInput}%")->first();
                }
            }

            // Previous active package info
            $activePkg = ActivePackage::where('userId', $user->id)
                ->orderByDesc('id')
                ->first();

            if (!$newPackage && $isCustomPlan) {
                // Do NOT create a custom plan record in the packages table.
                // Anchor to the user's existing package or a standard base package, and apply custom quotas on ActivePackage.
                $newPackage = ($activePkg && $activePkg->packageId)
                    ? Package::find($activePkg->packageId)
                    : Package::first();

                $customPlanName = $request->input('custom_plan_name', 
                    $request->input('customPlanName', 
                    $request->input('plan_name', 
                    $request->input('packageName', $targetPlanInput ?: ('Custom Plan - ' . ($user->companyName ?: $user->name))))));
                $customDays = (int) ($request->input('days', $request->input('duration', $request->input('duration_days', 30))) ?: 30);
                $customPrice = $request->input('custom_price', $request->input('price', 0));
                $customMsg = (int) ($request->input('totalMessages', $request->input('custom_msg_count', $request->input('msgCount', $request->input('msg_count', 10000)))));
                $customContact = (int) ($request->input('totalContacts', $request->input('custom_contact_count', $request->input('contactCount', $request->input('contact_count', 5000)))));
                $customTemplate = (int) ($request->input('template_limit', $request->input('templateLimit', $request->input('custom_template_count', $request->input('templateCount', $request->input('template_count', 100))))));

                $request->merge([
                    'is_custom_plan' => true,
                    'custom_plan' => true,
                    'custom_plan_name' => $customPlanName,
                    'days' => $customDays,
                    'duration' => $customDays,
                    'price' => $customPrice,
                    'custom_price' => $customPrice,
                    'totalMessages' => $customMsg,
                    'custom_msg_count' => $customMsg,
                    'totalContacts' => $customContact,
                    'custom_contact_count' => $customContact,
                    'custom_template_count' => $customTemplate,
                    'template_limit' => $customTemplate,
                ]);
            }

            if (!$newPackage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Selected subscription plan not found'
                ], 404);
            }

            $previousPlanName = 'None';
            $previousPackageId = null;
            if ($activePkg) {
                $previousPackageId = $activePkg->packageId;
                $activePkg->loadMissing('package');
                $previousPlanName = $activePkg->package ? $activePkg->package->packageName : 'Plan #' . $activePkg->packageId;
            }

            // Set action to upgraded and pass packageId
            $request->merge([
                'packageId' => $newPackage->id,
                'action' => 'upgraded',
            ]);

            // Calculate remaining days from current active package if still in the future
            $remainingDays = 0;
            if ($activePkg && $activePkg->endDate && \Carbon\Carbon::parse($activePkg->endDate)->gt(now())) {
                $remainingDays = (int) max(0, ceil(now()->floatDiffInDays(\Carbon\Carbon::parse($activePkg->endDate), false)));
            }

            $packageDays = (int) ($request->input('days') ?: ($newPackage->day ?: 30));
            $totalDays = $packageDays + $remainingDays;

            // Automatically start from now() and add on remaining days of old plan unless admin supplied explicit custom dates
            if (!$request->filled('startDate') && !$request->filled('start_date')) {
                $request->merge([
                    'startDate' => now()->toIso8601String(),
                    'start_date' => now()->toIso8601String(),
                ]);
            }
            if (!$request->filled('endDate') && !$request->filled('end_date')) {
                $newEndDate = now()->addDays($totalDays);
                $request->merge([
                    'endDate' => $newEndDate->toIso8601String(),
                    'end_date' => $newEndDate->toIso8601String(),
                    'days' => $totalDays,
                ]);
            }

            $previousRemainingMessages = 0;
            $previousRemainingContacts = 0;
            $previousRemainingTemplates = 0;
            if ($activePkg) {
                // 1. Messages: remaining unused messages
                $oldLimit = $activePkg->resolvedMessageLimit();
                $oldUsed = $activePkg->is_yearly
                    ? (int) ($activePkg->monthlyUsedMsgCount ?? 0)
                    : (int) ($activePkg->usedMsgCount ?? 0);
                $previousRemainingMessages = max(0, $oldLimit - $oldUsed);

                // 2. Contacts: remaining unused contacts + extra add-ons
                $oldContactLimit = $activePkg->resolvedContactLimit();
                $usedContacts = max(
                    (int) ($activePkg->monthlyUsedContactCount ?? 0),
                    \App\Models\Contact::where('userId', $user->id)->count()
                );
                $remainingContacts = max(0, $oldContactLimit - $usedContacts);
                $previousRemainingContacts = max($remainingContacts, $activePkg->addonContactCount());

                // 3. Templates: remaining unused custom templates + extra add-ons
                $oldTemplateLimit = $activePkg->resolvedTemplateLimit();
                $usedTemplates = max(
                    (int) ($activePkg->monthlyUsedTemplateCount ?? 0),
                    \App\Models\Template::where('userId', $user->id)->where('type', 'custom')->count()
                );
                $remainingTemplates = max(0, $oldTemplateLimit - $usedTemplates);
                $baseOldTemplateLimit = (int) ($activePkg->package?->templateCount ?? 0);
                $addonTemplates = max(0, $oldTemplateLimit - $baseOldTemplateLimit);
                $previousRemainingTemplates = max($remainingTemplates, $addonTemplates);
            }

            // Carry over remaining features / add-ons on upgrade if no explicit override limit was passed
            $newBaseMsg = (int) ($newPackage->msgCount ?? 0);
            $targetTotalMsg = $newBaseMsg + $previousRemainingMessages;
            if (!$request->filled('totalMessages') && !$request->filled('allocatedMessages') && !$request->filled('custom_msg_count')) {
                $request->merge([
                    'totalMessages' => $targetTotalMsg,
                    'custom_msg_count' => $targetTotalMsg,
                ]);
            }

            $newTotalContacts = (int) ($newPackage->contactCount ?? 1000) + $previousRemainingContacts;
            if (!$request->filled('totalContacts') && !$request->filled('custom_contact_count')) {
                $request->merge([
                    'totalContacts' => $newTotalContacts,
                    'custom_contact_count' => $newTotalContacts,
                ]);
            }

            $newTotalTemplates = (int) ($newPackage->templateCount ?? 50) + $previousRemainingTemplates;
            if (!$request->filled('custom_template_count')) {
                $request->merge([
                    'custom_template_count' => $newTotalTemplates,
                ]);
            }

            if (!$request->filled('notes')) {
                $rolloverParts = [];
                if ($previousRemainingMessages > 0) {
                    $rolloverParts[] = "+{$previousRemainingMessages} unused msgs";
                }
                if ($previousRemainingContacts > 0) {
                    $rolloverParts[] = "+{$previousRemainingContacts} contacts";
                }
                if ($previousRemainingTemplates > 0) {
                    $rolloverParts[] = "+{$previousRemainingTemplates} templates";
                }
                if ($remainingDays > 0) {
                    $rolloverParts[] = "+{$remainingDays} remaining days";
                }
                $rolloverText = !empty($rolloverParts) ? " with " . implode(', ', $rolloverParts) . " carried over" : "";
                $request->merge([
                    'notes' => "Plan upgraded from {$previousPlanName} to {$newPackage->packageName}{$rolloverText} by Super Admin"
                ]);
            }

            $syncedPkg = $this->syncUserActivePackage($user, $request);

            // By default on upgrade, reset usages to give fresh quota
            $resetUsed = $request->boolean('reset_used_messages', $request->boolean('resetUsage', true));
            if ($resetUsed) {
                $syncedPkg->usedMsgCount = 0;
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedMsgCount')) {
                    $syncedPkg->monthlyUsedMsgCount = 0;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedTemplateCount')) {
                    $syncedPkg->monthlyUsedTemplateCount = 0;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedContactCount')) {
                    $syncedPkg->monthlyUsedContactCount = 0;
                }
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'lastMonthlyReset')) {
                $syncedPkg->lastMonthlyReset = now();
            }
            $syncedPkg->status = ActivePackage::STATUS_ACTIVE;
            $syncedPkg->save();
            $syncedPkg->load('package');

            // Reactivate user and staff if status was inactive
            $user->update(['status' => 'active']);
            if ($user->isAdmin() || strtolower((string)$user->role) === 'admin') {
                User::where('parent_id', $user->id)->update(['status' => 'active']);
            }

            $planPayload = $syncedPkg->toPlanPayload();
            $invoiceResult = $this->createPlanInvoiceIfRequested($user, $syncedPkg, $request, 'upgrade');

            $upgradeData = [
                'userId' => 'usr_' . $user->id,
                'dbId' => $user->id,
                'userName' => $user->name,
                'userEmail' => $user->email,
                'companyName' => $user->companyName ?? '',
                'action' => 'upgraded',
                'previousPlan' => $previousPlanName,
                'previousPackageId' => $previousPackageId,
                'upgradedPlan' => $newPackage->packageName,
                'upgradedPackageId' => $newPackage->id,
                'upgradedAt' => now()->toIso8601String(),
                'plan' => $planPayload['packageName'],
                'packageName' => $planPayload['packageName'],
                'currentPlan' => $planPayload,
            ];

            if ($invoiceResult && ($invoiceResult['success'] ?? false)) {
                $upgradeData['invoice'] = $invoiceResult['invoice'];
                $upgradeData['transaction'] = $invoiceResult['transaction'];
                $upgradeData['wallet'] = $invoiceResult['wallet'];
            }

            return response()->json([
                'success' => true,
                'message' => "User plan upgraded successfully from {$previousPlanName} to {$newPackage->packageName}",
                'data' => array_merge($upgradeData, $planPayload)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error upgrading user plan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync and update or customize active package dates, limits, pricing, and package assignment
     */
    private function syncUserActivePackage(User $user, Request $request)
    {
        $activePkg = ActivePackage::where('userId', $user->id)
            ->orderByDesc('id')
            ->first();

        $isExplicitCustom = $request->boolean('is_custom_plan')
            || $request->boolean('custom_plan')
            || $request->filled('custom_plan_name')
            || $request->filled('customPlanName')
            || ($request->input('action') === 'custom_plan');

        $customPlanName = $request->input('custom_plan_name')
            ?? $request->input('customPlanName')
            ?? $request->input('plan_name')
            ?? $request->input('packageName');

        $planInput = $request->input('packageId')
            ?? $request->input('package_id')
            ?? $request->input('planId')
            ?? $request->input('plan_id')
            ?? $request->input('plan');

        $package = null;
        if ($customPlanName) {
            $package = Package::where('packageName', $customPlanName)->first();
            $customDays = (int) ($request->input('days')
                ?? $request->input('duration')
                ?? $request->input('duration_days')
                ?? $request->input('durationDays')
                ?? 30);
            $customPrice = $request->input('custom_price')
                ?? $request->input('customPrice')
                ?? $request->input('price', 0);
            $customMsg = (int) ($request->input('total_messages')
                ?? $request->input('totalMessages')
                ?? $request->input('messages')
                ?? $request->input('custom_msg_count')
                ?? $request->input('msgCount', 5000));
            $customContact = (int) ($request->input('total_contacts')
                ?? $request->input('totalContacts')
                ?? $request->input('contacts')
                ?? $request->input('custom_contact_count')
                ?? $request->input('contactCount', 1000));
            $customTemplate = (int) ($request->input('template_limit')
                ?? $request->input('templateLimit')
                ?? $request->input('templates_limit')
                ?? $request->input('templates')
                ?? $request->input('custom_template_count')
                ?? $request->input('templateCount', 50));

            if (!$package) {
                // Do NOT create a custom plan record in the packages table.
                // Anchor to the user's existing package or a standard base package, and apply custom quotas on ActivePackage.
                $package = ($activePkg && $activePkg->packageId)
                    ? Package::find($activePkg->packageId)
                    : Package::first();
            }
        } elseif ($planInput !== null && $planInput !== '') {
            if (is_numeric($planInput)) {
                $package = Package::find((int) $planInput);
            }
            if (!$package) {
                $package = Package::where('packageName', 'like', "%{$planInput}%")->first();
            }
        } elseif ($isExplicitCustom) {
            // Explicit custom plan requested without specifying an existing package or customPlanName.
            // Anchor to user's existing package or standard package without creating a row in packages table.
            $package = ($activePkg && $activePkg->packageId)
                ? Package::find($activePkg->packageId)
                : Package::first();
        }

        $previousPackageId = $activePkg ? (int) $activePkg->packageId : null;
        $isNewActivePackage = false;

        // Helper function for flexible date parsing (ISO, YYYY-MM-DD, DD/MM/YYYY, DD-MM-YYYY)
        $parseCustomDate = function ($dateInput, $isEndOfDay = false) {
            if (empty($dateInput)) return null;
            try {
                $trimmed = trim((string) $dateInput);
                // Handle DD/MM/YYYY or DD-MM-YYYY format
                if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $trimmed, $m)) {
                    $d = Carbon::createFromDate((int)$m[3], (int)$m[2], (int)$m[1]);
                    return $isEndOfDay ? $d->endOfDay() : $d->startOfDay();
                }
                $d = Carbon::parse($trimmed);
                return $isEndOfDay ? $d->endOfDay() : $d->startOfDay();
            } catch (\Exception $e) {
                return null;
            }
        };

        $reqStartDate = $request->input('startDate', $request->input('start_date', $request->input('start')));
        $reqEndDate = $request->input('endDate', $request->input('end_date', $request->input('end')));
        $parsedStart = $parseCustomDate($reqStartDate, false);
        $parsedEnd = $parseCustomDate($reqEndDate, true);

        if (!$activePkg) {
            if (!$package) {
                $package = Package::first();
            }
            $days = (int) ($request->input('days', $request->input('duration', $request->input('duration_days', $package ? ($package->day ?: 30) : 30))) ?: 30);
            $startDate = $parsedStart ?: now()->startOfDay();
            $endDate = $parsedEnd ?: (clone $startDate)->addDays($days)->endOfDay();

            $activeData = [
                'userId' => $user->id,
                'packageId' => $package ? $package->id : 1,
                'startDate' => $startDate,
                'endDate' => $endDate,
                'day' => $days,
                'status' => ActivePackage::statusFromDates($startDate, $endDate),
                'usedMsgCount' => 0,
            ];
            if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'lastMonthlyReset')) {
                $activeData['lastMonthlyReset'] = now();
            }
            $activePkg = ActivePackage::create($activeData);
            $isNewActivePackage = true;
            $previousPackageId = null;
        } else {
            if ($package) {
                $activePkg->packageId = $package->id;
            }
        }

        $packageChanged = $isNewActivePackage
            || ($package && $previousPackageId !== null && (int) $package->id !== $previousPackageId);

        // Update Start Date
        if ($parsedStart) {
            $activePkg->startDate = $parsedStart;
        } elseif (!$activePkg->startDate) {
            $activePkg->startDate = now()->startOfDay();
        }

        // Determine Days & End Date
        $packageDays = $package ? (int) ($package->day ?: 30) : ($activePkg->day ?: 30);
        $inputDays = $request->filled('days') 
            ? (int) $request->input('days') 
            : ($request->filled('duration') 
                ? (int) $request->input('duration') 
                : ($request->filled('duration_days') 
                    ? (int) $request->input('duration_days') 
                    : ($request->filled('durationDays') ? (int) $request->input('durationDays') : null)));
        $effectiveDays = $inputDays ?: $packageDays;

        if ($parsedEnd) {
            $activePkg->endDate = $parsedEnd;
            if ($activePkg->startDate) {
                $start = Carbon::parse($activePkg->startDate);
                $activePkg->day = $inputDays ?: (int) max(1, round($start->diffInDays($parsedEnd)));
            }
        } elseif ($package || $inputDays) {
            // Recalculate endDate only when package actually changed or days explicitly set
            if ($packageChanged || $inputDays) {
                $start = Carbon::parse($activePkg->startDate);
                $activePkg->endDate = (clone $start)->addDays($effectiveDays)->endOfDay();
                $activePkg->day = $effectiveDays;
            }
        }

        // Base Package Defaults
        if ($package) {
            $activePkg->setRelation('package', $package);
        } else {
            $activePkg->loadMissing('package');
        }
        $packageObj = $package ?: $activePkg->package;
        $baseMsgCount = $packageObj ? (int) $packageObj->msgCount : 0;
        $baseContactCount = $packageObj ? (int) ($packageObj->contactCount ?? $packageObj->contact_count ?? 0) : 0;
        $basePrice = $packageObj ? (float) $packageObj->price : 0.00;

        // Message limit handling:
        // When explicit custom plan, or total_messages / totalMessages / allocatedMessages is passed, it represents ABSOLUTE quota
        $explicitTotalMsg = $request->input('total_messages')
            ?? $request->input('totalMessages')
            ?? $request->input('total_message')
            ?? $request->input('totalMessage')
            ?? $request->input('messages')
            ?? $request->input('allocatedMessages');
        $explicitAddonMsg = $request->input('addonMsgCount')
            ?? $request->input('addonMessageLimit')
            ?? $request->input('addon_msg_count')
            ?? $request->input('addon_message_limit');
        $inputMsg = $request->input('msgCount')
            ?? $request->input('custom_msg_count')
            ?? $request->input('messageLimit');

        if ($explicitTotalMsg !== null && $explicitTotalMsg !== '') {
            $activePkg->custom_msg_count = max(0, (int) $explicitTotalMsg);
        } elseif ($isExplicitCustom) {
            // In custom plan assignment, any provided message limit is absolute
            if ($inputMsg !== null && $inputMsg !== '') {
                $activePkg->custom_msg_count = max(0, (int) $inputMsg);
            } elseif ($packageChanged) {
                $activePkg->custom_msg_count = $baseMsgCount;
            }
        } elseif ($explicitAddonMsg !== null && $explicitAddonMsg !== '') {
            $addon = max(0, (int) $explicitAddonMsg);
            $activePkg->custom_msg_count = $baseMsgCount + $addon;
        } elseif ($inputMsg !== null && $inputMsg !== '') {
            // Super Admin legacy "Messages Limit" field = add-on count (added to plan base)
            $addon = max(0, (int) $inputMsg);
            $activePkg->custom_msg_count = $baseMsgCount + $addon;
        } elseif ($packageChanged) {
            $activePkg->custom_msg_count = $baseMsgCount;
        }

        $activePkg->custom_msg_price = null;

        // Contact limit handling:
        $explicitTotalContact = $request->input('total_contacts')
            ?? $request->input('totalContacts')
            ?? $request->input('total_contact')
            ?? $request->input('totalContact')
            ?? $request->input('contacts')
            ?? $request->input('custom_contact_count');
        $explicitAddonContact = $request->input('addonContactCount')
            ?? $request->input('addonContactsLimit')
            ?? $request->input('addon_contact_count')
            ?? $request->input('addon_contacts_limit');
        $inputContact = $request->input('contactCount')
            ?? $request->input('contactsLimit');

        if ($explicitTotalContact !== null && $explicitTotalContact !== '') {
            $activePkg->custom_contact_count = max(0, (int) $explicitTotalContact);
        } elseif ($isExplicitCustom) {
            // In custom plan assignment, any provided contact limit is absolute
            if ($inputContact !== null && $inputContact !== '') {
                $activePkg->custom_contact_count = max(0, (int) $inputContact);
            } elseif ($packageChanged) {
                $activePkg->custom_contact_count = $baseContactCount;
            }
        } elseif ($explicitAddonContact !== null && $explicitAddonContact !== '') {
            $addon = max(0, (int) $explicitAddonContact);
            $activePkg->custom_contact_count = $baseContactCount + $addon;
        } elseif ($inputContact !== null && $inputContact !== '') {
            $addon = max(0, (int) $inputContact);
            $activePkg->custom_contact_count = $baseContactCount + $addon;
        } elseif ($packageChanged) {
            $activePkg->custom_contact_count = $baseContactCount;
        }

        $activePkg->custom_contact_price = null;

        // Custom Templates Limit override
        $customTemplateCount = $request->input('template_limit')
            ?? $request->input('templateLimit')
            ?? $request->input('templates_limit')
            ?? $request->input('templatesLimit')
            ?? $request->input('templates')
            ?? $request->input('custom_template_count')
            ?? $request->input('templateCount');
        if ($customTemplateCount !== null && $customTemplateCount !== '') {
            $activePkg->custom_template_count = max(0, (int) $customTemplateCount);
        } elseif ($packageChanged) {
            $activePkg->custom_template_count = (int) ($packageObj->templateCount ?? 50);
        }

        // Custom Templates Price override
        $customTemplatePrice = $request->input('templatePrice')
            ?? $request->input('templatesPrice')
            ?? $request->input('custom_template_price');
        if ($customTemplatePrice !== null && $customTemplatePrice !== '') {
            $activePkg->custom_template_price = (float) $customTemplatePrice;
        }

        // Price override:
        $customPrice = $request->input('custom_price') ?? $request->input('customPrice');
        if ($customPrice === null || $customPrice === '') {
            if ($request->exists('price') && !$request->exists('msgPrice') && !$request->exists('messagesPrice') && !$request->exists('custom_msg_price')) {
                $customPrice = $request->input('price');
            }
        }
        if ($customPrice !== null && $customPrice !== '') {
            $activePkg->custom_price = (float) $customPrice;
        } elseif ($packageChanged) {
            $activePkg->custom_price = $basePrice;
        }

        // Reset or set used messages count
        if ($request->filled('usedMsgCount') || $request->filled('usedMessages')) {
            $activePkg->usedMsgCount = (int) $request->input('usedMsgCount', $request->input('usedMessages'));
        } elseif ($isExplicitCustom) {
            // When assigning custom plan, default reset usage counters unless reset_used_messages is false
            $resetUsed = $request->boolean('reset_used_messages', $request->boolean('resetUsage', true));
            if ($resetUsed) {
                $activePkg->usedMsgCount = 0;
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedMsgCount')) {
                    $activePkg->monthlyUsedMsgCount = 0;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedTemplateCount')) {
                    $activePkg->monthlyUsedTemplateCount = 0;
                }
                if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'monthlyUsedContactCount')) {
                    $activePkg->monthlyUsedContactCount = 0;
                }
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('active_packages', 'lastMonthlyReset')) {
            $activePkg->lastMonthlyReset = now();
        }

        $activePkg->status = ActivePackage::statusFromDates($activePkg->startDate, $activePkg->endDate);
        $activePkg->save();
        $activePkg->load('package');

        // Ensure user is active if assigned a valid plan
        if ($activePkg->status === ActivePackage::STATUS_ACTIVE && $user->status !== 'active') {
            $user->update(['status' => 'active']);
            if ($user->isAdmin() || strtolower((string)$user->role) === 'admin') {
                User::where('parent_id', $user->id)->update(['status' => 'active']);
            }
        }

        $customPlanName = $request->input('custom_plan_name', 
            $request->input('customPlanName', 
            $request->input('plan_name', 
            $request->input('packageName'))));

        $action = $request->input('action') ?: ($isExplicitCustom ? 'custom_plan' : ($request->isMethod('post') && !$request->has('packageId') ? 'updated' : 'assigned'));
        $planDisplayName = $customPlanName ?: ($activePkg->package?->packageName ?: 'Custom Plan');
        $customNotes = $request->input('notes') ?: ($isExplicitCustom ? "Custom plan '{$planDisplayName}' assigned by Super Admin" : null);
        $this->logPackageHistory($user, $activePkg, $action, $request->user()?->id, $customNotes, $customPlanName);

        // Dispatch plan notification email to user and CC admin (if enabled)
        try {
            app(\App\Services\PlanMailService::class)->sendPlanNotification($user, $activePkg->package, [
                'action' => $action,
                'activePkg' => $activePkg,
                'price' => (float) ($activePkg->custom_price ?: ($activePkg->package?->price ?? 0)),
                'days' => (int) ($activePkg->day ?: 30),
                'startDate' => $activePkg->startDate,
                'endDate' => $activePkg->endDate,
                'msgCount' => (int) ($activePkg->custom_msg_count ?: ($activePkg->package?->msgCount ?? 0)),
                'contactCount' => (int) ($activePkg->custom_contact_count ?: ($activePkg->package?->contactCount ?? 0)),
                'templateCount' => (int) ($activePkg->custom_template_count ?: ($activePkg->package?->templateCount ?? 50)),
                'payment_method' => $request->input('payment_method', $request->input('paymentMethod', 'Admin Allocation')),
            ]);
        } catch (\Throwable $mailEx) {
            \Illuminate\Support\Facades\Log::warning("Failed to dispatch plan notification email: " . $mailEx->getMessage());
        }

        return $activePkg;
    }

    /**
     * Get Package Assignment History Log for User
     * GET /api/v1/admin/users/{userId}/plan-history
     */
    public function getUserPlanHistory(Request $request, $userId = null)
    {
        try {
            $targetId = $userId ?: $request->input('user_id', $request->input('userId'));
            $rawId = $targetId ? str_replace('usr_', '', $targetId) : ($request->user() ? $request->user()->getCompanyId() : null);

            if (!$rawId) {
                return response()->json([
                    'success' => false,
                    'status' => false,
                    'message' => 'User ID is required'
                ], 400);
            }

            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $activePkg = ActivePackage::where('userId', $user->id)->latest('id')->first();
            $hasActivePlan = $activePkg
                ? ((int) $activePkg->status !== ActivePackage::STATUS_EXPIRED && (!$activePkg->endDate || now()->lte(\Carbon\Carbon::parse($activePkg->endDate))))
                : true;

            $activeAssigned = false;
            $newerPlanStart = null;
            $histories = UserPackageHistory::where('userId', $user->id)
                ->with(['package', 'assignedByUser'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($h) use ($hasActivePlan, &$activeAssigned, &$newerPlanStart) {
                    $pkg = $h->package;
                    $startDate = $h->startDate ? \Carbon\Carbon::parse($h->startDate) : null;
                    $endDate = $h->endDate ? \Carbon\Carbon::parse($h->endDate) : null;
                    $now = now();

                    $isCurrentActivePlan = false;
                    if ($startDate && $now->lt($startDate)) {
                        $computedStatus = ActivePackage::STATUS_UPCOMING;
                        $statusLabel = 'upcoming';
                    } elseif ($endDate && $now->gt($endDate)) {
                        $computedStatus = ActivePackage::STATUS_EXPIRED;
                        $statusLabel = 'expired';
                    } else {
                        if ($hasActivePlan && !$activeAssigned) {
                            $computedStatus = ActivePackage::STATUS_ACTIVE;
                            $statusLabel = 'active';
                            $activeAssigned = true;
                            $isCurrentActivePlan = true;
                        } else {
                            $computedStatus = ActivePackage::STATUS_EXPIRED;
                            $statusLabel = 'expired';
                        }
                    }

                    $supersededAt = $newerPlanStart;
                    $newerPlanStart = $startDate ?: ($h->created_at ? \Carbon\Carbon::parse($h->created_at) : $now);

                    if ($isCurrentActivePlan) {
                        $effectiveEndDate = $endDate;
                        $lastActiveDate = null;
                        $daysRemaining = $endDate ? max(0, (int) ceil($now->floatDiffInDays($endDate, false))) : 0;
                    } else {
                        $lastActiveDate = $supersededAt ?: ($endDate && $now->gt($endDate) ? $endDate : $now);
                        $effectiveEndDate = $lastActiveDate;
                        $daysRemaining = 0;
                    }

                    // Billing period resolution: monthly vs yearly
                    $days = (int) ($h->day ?: ($pkg ? $pkg->day : 30));
                    $diffDays = ($startDate && $endDate) ? (int) abs($startDate->diffInDays($endDate)) : 0;
                    $nameContainsYear = stripos((string) ($h->packageName ?: ($pkg?->packageName ?? '')), 'year') !== false;
                    $pkgIsYearly = $pkg && $pkg->is_yearly;

                    $isYearly = (bool) ($pkgIsYearly || $days >= 300 || $diffDays >= 300 || $nameContainsYear);
                    $isMonthly = !$isYearly;

                    $period = $isYearly ? 'yearly' : 'monthly';
                    $periodLabel = $isYearly ? 'Yearly' : 'Monthly';
                    $billingPeriod = $isYearly ? 'yearly' : 'monthly';

                    $pkgObj = [
                        'id' => $pkg ? (int)$pkg->id : (int)$h->packageId,
                        'packageName' => $h->packageName ?: ($pkg ? $pkg->packageName : 'Custom Plan'),
                        'packageDesc' => $pkg ? ($pkg->packageDesc ?? '') : '',
                        'day' => $days,
                        'msgCount' => (int)($h->msgCount ?: ($pkg ? $pkg->msgCount : 0)),
                        'templateCount' => (int)($h->templateCount ?: ($pkg ? ($pkg->templateCount ?? 100000) : 100000)),
                        'contactCount' => (int)($h->contactCount ?: ($pkg ? ($pkg->contactCount ?? $pkg->contact_count ?? 150000) : 150000)),
                        'price' => (float)($h->price ?: ($pkg ? $pkg->price : 0)),
                        'period' => $period,
                        'periodLabel' => $periodLabel,
                        'billingPeriod' => $billingPeriod,
                        'billingCycle' => $billingPeriod,
                        'isYearly' => $isYearly,
                        'isMonthly' => $isMonthly,
                        'createdAt' => $pkg && $pkg->created_at ? $pkg->created_at->toIso8601String() : ($h->created_at ? $h->created_at->toIso8601String() : null),
                        'updatedAt' => $pkg && $pkg->updated_at ? $pkg->updated_at->toIso8601String() : ($h->updated_at ? $h->updated_at->toIso8601String() : null),
                    ];

                    return [
                        'id' => (int)$h->id,
                        'userId' => (int)$h->userId,
                        'packageId' => $pkgObj,
                        'startDate' => $h->startDate ? $h->startDate->toIso8601String() : null,
                        'endDate' => $h->endDate ? $h->endDate->toIso8601String() : null,
                        'effective_end_date' => $effectiveEndDate ? $effectiveEndDate->toIso8601String() : null,
                        'last_active_date' => $lastActiveDate ? $lastActiveDate->toIso8601String() : null,
                        'day' => $days,
                        'days_remaining' => $daysRemaining,
                        'period' => $period,
                        'periodLabel' => $periodLabel,
                        'billingCycle' => $billingPeriod,
                        'isYearly' => $isYearly,
                        'isMonthly' => $isMonthly,
                        'status' => $computedStatus,
                        'statusLabel' => $statusLabel,
                        'is_active' => ($statusLabel === 'active'),
                        'is_expired' => ($statusLabel === 'expired'),
                        'createdAt' => $h->created_at ? $h->created_at->toIso8601String() : null,
                        'updatedAt' => $h->updated_at ? $h->updated_at->toIso8601String() : null,

                        // Helper fields for backward compatibility
                        'packageName' => $h->packageName ?: ($pkg ? $pkg->packageName : 'Custom Plan'),
                        'action' => $h->action ?: 'assigned',
                        'price' => (float)$h->price,
                        'msgCount' => (int)$h->msgCount,
                        'contactCount' => (int)$h->contactCount,
                        'assigned_by' => $h->assignedByUser ? [
                            'id' => $h->assignedByUser->id,
                            'name' => $h->assignedByUser->name,
                            'email' => $h->assignedByUser->email,
                            'role' => $h->assignedByUser->role,
                        ] : 'Super Admin',
                    ];
                });

            if ($histories->isEmpty()) {
                $activePkg = ActivePackage::where('userId', $user->id)->with('package')->orderByDesc('id')->first();
                if ($activePkg) {
                    $pkg = $activePkg->package;
                    $pkgObj = [
                        'id' => $pkg ? (int)$pkg->id : (int)$activePkg->packageId,
                        'packageName' => $pkg ? $pkg->packageName : 'Custom Plan',
                        'packageDesc' => $pkg ? ($pkg->packageDesc ?? '') : '',
                        'day' => $pkg ? (int)$pkg->day : (int)($activePkg->day ?: 30),
                        'msgCount' => (int)($activePkg->custom_msg_count ?: ($pkg ? $pkg->msgCount : 0)),
                        'templateCount' => (int)($activePkg->custom_template_count ?: ($pkg ? ($pkg->templateCount ?? 100000) : 100000)),
                        'contactCount' => (int)($activePkg->custom_contact_count ?: ($pkg ? ($pkg->contactCount ?? $pkg->contact_count ?? 150000) : 150000)),
                        'price' => (float)($activePkg->custom_price ?: ($pkg ? $pkg->price : 0)),
                        'createdAt' => $pkg && $pkg->created_at ? $pkg->created_at->toIso8601String() : null,
                        'updatedAt' => $pkg && $pkg->updated_at ? $pkg->updated_at->toIso8601String() : null,
                    ];

                    $histories = collect([[
                        'id' => (int)$activePkg->id,
                        'userId' => (int)$activePkg->userId,
                        'packageId' => $pkgObj,
                        'startDate' => $activePkg->startDate ? Carbon::parse($activePkg->startDate)->toIso8601String() : null,
                        'endDate' => $activePkg->endDate ? Carbon::parse($activePkg->endDate)->toIso8601String() : null,
                        'day' => (int)($activePkg->day ?: ($pkg ? $pkg->day : 30)),
                        'status' => $activePkg->computedStatus(),
                        'statusLabel' => $activePkg->statusLabel(),
                        'createdAt' => $activePkg->created_at ? $activePkg->created_at->toIso8601String() : null,
                        'updatedAt' => $activePkg->updated_at ? $activePkg->updated_at->toIso8601String() : null,
                    ]]);
                }
            }

            return response()->json([
                'success' => true,
                'status' => true,
                'count' => count($histories),
                'total' => count($histories),
                'data' => $histories
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching package history: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper to log package assignment history
     */
    private function logPackageHistory(User $user, ActivePackage $activePkg, string $action, ?int $assignedBy = null, ?string $notes = null, ?string $customPlanName = null): UserPackageHistory
    {
        $pkg = $activePkg->package ?: Package::find($activePkg->packageId);

        $totalMessages = 0;
        if (isset($activePkg->custom_msg_count) && $activePkg->custom_msg_count !== null && $activePkg->custom_msg_count > 0) {
            $totalMessages = (int) $activePkg->custom_msg_count;
        } elseif ($pkg) {
            $totalMessages = (int) $pkg->msgCount;
        }

        $contactLimit = 0;
        if (isset($activePkg->custom_contact_count) && $activePkg->custom_contact_count !== null) {
            $contactLimit = (int) $activePkg->custom_contact_count;
        } elseif ($pkg && isset($pkg->contact_count)) {
            $contactLimit = (int) $pkg->contact_count;
        }

        $templateLimit = isset($activePkg->custom_template_count) && $activePkg->custom_template_count !== null
            ? (int) $activePkg->custom_template_count
            : 0;

        $price = isset($activePkg->custom_price) && $activePkg->custom_price !== null
            ? (float) $activePkg->custom_price
            : ($pkg ? (float) $pkg->price : 0.00);

        if (!$customPlanName && $activePkg->is_custom) {
            $latestHist = UserPackageHistory::where('userId', $user->id)->orderByDesc('id')->first();
            if ($latestHist && !empty($latestHist->packageName)) {
                $customPlanName = $latestHist->packageName;
            }
        }

        $loggedPlanName = $customPlanName ?: ($pkg ? $pkg->packageName : 'Custom Plan');

        return UserPackageHistory::recordOnce([
            'userId' => $user->id,
            'packageId' => $activePkg->packageId,
            'assignedBy' => $assignedBy ?: (auth()->check() ? auth()->id() : null),
            'action' => $action,
            'packageName' => $loggedPlanName,
            'price' => $price,
            'msgCount' => $totalMessages,
            'msgPrice' => isset($activePkg->custom_msg_price) ? (float) $activePkg->custom_msg_price : null,
            'contactCount' => $contactLimit,
            'contactPrice' => isset($activePkg->custom_contact_price) ? (float) $activePkg->custom_contact_price : null,
            'templateCount' => $templateLimit,
            'templatePrice' => isset($activePkg->custom_template_price) ? (float) $activePkg->custom_template_price : null,
            'startDate' => $activePkg->startDate,
            'endDate' => $activePkg->endDate,
            'day' => (int) $activePkg->day,
            'notes' => $notes ?: "Package {$action} for user {$user->name}",
        ]);
    }

    /**
     * Helper to create Invoice and record transaction for Plan Assignment or Upgrade
     */
    private function createPlanInvoiceIfRequested(User $user, ActivePackage $activePkg, Request $request, string $defaultType = 'custom_plan'): ?array
    {
        $hasInvoiceToggle = $request->has('generate_tax_invoice')
            || $request->has('generateTaxInvoice')
            || $request->has('tax_invoice')
            || $request->has('taxInvoice')
            || $request->has('generate_invoice')
            || $request->has('generateInvoice');

        $invoiceToggleValue = $request->boolean('generate_tax_invoice')
            || $request->boolean('generateTaxInvoice')
            || $request->boolean('tax_invoice')
            || $request->boolean('taxInvoice')
            || $request->boolean('generate_invoice')
            || $request->boolean('generateInvoice');

        if ($hasInvoiceToggle && !$invoiceToggleValue) {
            return null;
        }

        $shouldGenerate = $invoiceToggleValue
            || $request->boolean('is_custom_plan')
            || $request->boolean('custom_plan')
            || ($request->input('action') === 'custom_plan')
            || ($defaultType === 'custom_plan')
            || ($defaultType === 'upgrade')
            || $request->filled('payment_method')
            || $request->filled('paymentMethod')
            || $request->filled('custom_price')
            || $request->filled('price');

        if (!$shouldGenerate) {
            return null;
        }

        try {
            $package = $activePkg->package ?: Package::find($activePkg->packageId);
            if (!$package) {
                return null;
            }

            $price = $request->input('custom_price', $request->input('customPrice', $request->input('price', $activePkg->custom_price ?? $package->price)));
            $paymentMethod = $request->input('payment_method', $request->input('paymentMethod', 'cash'));
            $paymentStatus = $request->input('payment_status', $request->input('paymentStatus', 'paid'));
            $notes = $request->input('notes', $request->input('note', $request->input('invoice_notes', $request->input('description'))));

            $customPlanName = $request->input('custom_plan_name', 
                $request->input('customPlanName', 
                $request->input('plan_name', 
                $request->input('packageName'))));

            $options = [
                'base_amount' => $price,
                'price' => $price,
                'gst_percentage' => $request->input('gst_percentage'),
                'tax_inclusive' => $request->boolean('tax_inclusive', false),
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'type' => $defaultType,
                'is_custom_plan' => $request->boolean('is_custom_plan', $request->boolean('custom_plan', $defaultType === 'custom_plan')),
                'custom_plan_name' => $customPlanName,
                'plan_name' => $customPlanName,
                'assigned_by' => $request->user()?->name ?: 'Super Admin',
                'notes' => $notes,
                'allow_negative_balance' => $request->boolean('allow_negative_balance', false),
            ];

            return app(\App\Services\InvoiceService::class)->createPlanInvoice($user, $package, $activePkg, $options);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Could not generate plan invoice: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get User WABA / Meta Settings & Setup API
     * GET /api/v1/admin/users/{userId}/settings
     */
    public function getUserSettings(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin user not found'
                ], 404);
            }

            $userSettings = Settings::where('userId', $user->id)->first();
            $hasWabaCreds = $userSettings 
                && !empty($userSettings->phoneNumberId) 
                && !empty($userSettings->access_token);
            $credStatus = !$userSettings 
                ? 'pending' 
                : (!$hasWabaCreds ? 'pending' : ($userSettings->isActive ? 'live' : 'inactive'));

            $rawWorkingDays = $userSettings?->working_days;
            $workingDays = is_array($rawWorkingDays)
                ? $rawWorkingDays
                : (is_string($rawWorkingDays) ? (json_decode($rawWorkingDays, true) ?: []) : []);

            return response()->json([
                'success' => true,
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'is_configured' => (bool) $hasWabaCreds,
                    'isConfigured' => (bool) $hasWabaCreds,
                    'credentials_status' => $credStatus,
                    'credentialsStatus' => $credStatus,
                    'whatsapp_status' => $credStatus,
                    'whatsappStatus' => $credStatus,
                    'fbAppId' => $userSettings?->fb_app_id ?? '',
                    'fbAppSecret' => $userSettings ? ($userSettings->fb_app_secret ? '********' : '') : '',
                    'fbAccessToken' => $userSettings?->access_token ?? '',
                    'wabaAccountId' => $userSettings?->waba_id ?? '',
                    'phoneId' => $userSettings?->phoneNumberId ?? '',
                    'webhookUrl' => (!empty($userSettings?->webhook_url)) 
                        ? $userSettings->webhook_url 
                        : url('/api/whatsapp/webhook/usr_' . $user->id),
                    'webhookSecret' => $userSettings?->webhook_secret ?? '',
                    'isActive' => $userSettings ? (bool) $userSettings->isActive : false,
                    'first_time_message' => $userSettings?->first_time_message ?? '',
                    'auto_ai_enabled' => (bool) ($userSettings?->auto_ai_enabled ?? false),
                    'business_name' => $userSettings?->business_name ?? '',
                    'business_type' => $userSettings?->business_type ?? '',
                    'business_details' => $userSettings?->business_details ?? '',
                    'business_hours' => $userSettings?->business_hours ?? '',
                    'auto_ai_tone' => $userSettings?->auto_ai_tone ?? 'friendly',
                    'business_address' => $userSettings?->business_address ?? '',
                    'website_url' => $userSettings?->website_url ?? '',
                    'support_phone' => $userSettings?->support_phone ?? '',
                    'support_email' => $userSettings?->support_email ?? '',
                    'industry' => $userSettings?->industry ?? '',
                    'services' => $userSettings?->services ?? '',
                    'products' => $userSettings?->products ?? '',
                    'location' => $userSettings?->location ?? '',
                    'working_days' => $workingDays,
                    'language' => $userSettings?->language ?? '',
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching user settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update User WABA / Meta Settings & Setup API
     * POST/PUT /api/v1/admin/users/{userId}/settings
     */
    public function updateUserSettings(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'fbAppId' => 'nullable|string',
            'fbAppSecret' => 'nullable|string',
            'fbAccessToken' => 'nullable|string',
            'wabaAccountId' => 'nullable|string',
            'phoneId' => 'nullable|string',
            'webhookUrl' => 'nullable|string',
            'webhookSecret' => 'nullable|string',
            'webhookVerifyToken' => 'nullable|string',
            'first_time_message' => 'nullable|string',
            'firstTimeMessage' => 'nullable|string',
            'auto_ai_enabled' => 'nullable|boolean',
            'autoAiEnabled' => 'nullable|boolean',
            'business_name' => 'nullable|string|max:255',
            'businessName' => 'nullable|string|max:255',
            'business_type' => 'nullable|string|max:255',
            'businessType' => 'nullable|string|max:255',
            'business_details' => 'nullable|string|max:5000',
            'businessDetails' => 'nullable|string|max:5000',
            'business_hours' => 'nullable|string|max:255',
            'businessHours' => 'nullable|string|max:255',
            'auto_ai_tone' => 'nullable|string|max:50',
            'autoAiTone' => 'nullable|string|max:50',
            'business_address' => 'nullable|string|max:1000',
            'businessAddress' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:1000',
            'website_url' => 'nullable|string|max:500',
            'websiteUrl' => 'nullable|string|max:500',
            'website' => 'nullable|string|max:500',
            'support_phone' => 'nullable|string|max:100',
            'supportPhone' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:100',
            'support_email' => 'nullable|string|max:150',
            'supportEmail' => 'nullable|string|max:150',
            'email' => 'nullable|string|max:150',
            'industry' => 'nullable|string|max:255',
            'services' => 'nullable|string|max:5000',
            'products' => 'nullable|string|max:5000',
            'location' => 'nullable|string|max:500',
            'working_days' => 'nullable',
            'workingDays' => 'nullable',
            'language' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Admin user not found'
                ], 404);
            }

            $settingsData = [
                'userId' => $user->id,
                'isActive' => true,
            ];

            if ($request->has('fbAppId')) $settingsData['fb_app_id'] = $request->input('fbAppId');
            if ($request->has('fbAppSecret') && $request->input('fbAppSecret') !== '********') {
                $settingsData['fb_app_secret'] = $request->input('fbAppSecret');
            }
            if ($request->has('fbAccessToken')) $settingsData['access_token'] = $request->input('fbAccessToken');
            if ($request->has('wabaAccountId')) $settingsData['waba_id'] = $request->input('wabaAccountId');
            if ($request->has('phoneId')) $settingsData['phoneNumberId'] = $request->input('phoneId');
            if ($request->has('webhookUrl')) {
                $settingsData['webhook_url'] = $request->input('webhookUrl');
            } else {
                $settingsData['webhook_url'] = url('/api/whatsapp/webhook/' . $user->id);
            }
            if ($request->has('webhookSecret')) $settingsData['webhook_secret'] = $request->input('webhookSecret');
            if ($request->has('webhookVerifyToken')) $settingsData['webhook_secret'] = $request->input('webhookVerifyToken');

            if ($request->has('first_time_message')) {
                $settingsData['first_time_message'] = $request->input('first_time_message');
            } elseif ($request->has('firstTimeMessage')) {
                $settingsData['first_time_message'] = $request->input('firstTimeMessage');
            }

            if ($request->has('auto_ai_enabled')) {
                $settingsData['auto_ai_enabled'] = (bool) $request->input('auto_ai_enabled');
            } elseif ($request->has('autoAiEnabled')) {
                $settingsData['auto_ai_enabled'] = (bool) $request->input('autoAiEnabled');
            }

            if ($request->has('business_name')) {
                $settingsData['business_name'] = $request->input('business_name');
            } elseif ($request->has('businessName')) {
                $settingsData['business_name'] = $request->input('businessName');
            }

            if ($request->has('business_type')) {
                $settingsData['business_type'] = $request->input('business_type');
            } elseif ($request->has('businessType')) {
                $settingsData['business_type'] = $request->input('businessType');
            }

            if ($request->has('business_details')) {
                $settingsData['business_details'] = $request->input('business_details');
            } elseif ($request->has('businessDetails')) {
                $settingsData['business_details'] = $request->input('businessDetails');
            }

            if ($request->has('business_hours')) {
                $settingsData['business_hours'] = $request->input('business_hours');
            } elseif ($request->has('businessHours')) {
                $settingsData['business_hours'] = $request->input('businessHours');
            }

            if ($request->has('auto_ai_tone')) {
                $settingsData['auto_ai_tone'] = $request->input('auto_ai_tone');
            } elseif ($request->has('autoAiTone')) {
                $settingsData['auto_ai_tone'] = $request->input('autoAiTone');
            }

            if ($request->has('business_address')) {
                $settingsData['business_address'] = $request->input('business_address');
            } elseif ($request->has('businessAddress')) {
                $settingsData['business_address'] = $request->input('businessAddress');
            } elseif ($request->has('address')) {
                $settingsData['business_address'] = $request->input('address');
            }

            if ($request->has('website_url')) {
                $settingsData['website_url'] = $request->input('website_url');
            } elseif ($request->has('websiteUrl')) {
                $settingsData['website_url'] = $request->input('websiteUrl');
            } elseif ($request->has('website')) {
                $settingsData['website_url'] = $request->input('website');
            }

            if ($request->has('support_phone')) {
                $settingsData['support_phone'] = $request->input('support_phone');
            } elseif ($request->has('supportPhone')) {
                $settingsData['support_phone'] = $request->input('supportPhone');
            } elseif ($request->has('phone')) {
                $settingsData['support_phone'] = $request->input('phone');
            }

            if ($request->has('support_email')) {
                $settingsData['support_email'] = $request->input('support_email');
            } elseif ($request->has('supportEmail')) {
                $settingsData['support_email'] = $request->input('supportEmail');
            } elseif ($request->has('email')) {
                $settingsData['support_email'] = $request->input('email');
            }

            if ($request->has('industry')) {
                $settingsData['industry'] = $request->input('industry');
            }

            if ($request->has('services')) {
                $settingsData['services'] = $request->input('services');
            }

            if ($request->has('products')) {
                $settingsData['products'] = $request->input('products');
            }

            if ($request->has('location')) {
                $settingsData['location'] = $request->input('location');
            }

            if ($request->has('working_days')) {
                $days = $request->input('working_days');
                $settingsData['working_days'] = is_array($days) ? $days : (is_string($days) ? json_decode($days, true) ?: [$days] : null);
            } elseif ($request->has('workingDays')) {
                $days = $request->input('workingDays');
                $settingsData['working_days'] = is_array($days) ? $days : (is_string($days) ? json_decode($days, true) ?: [$days] : null);
            }

            if ($request->has('language')) {
                $settingsData['language'] = $request->input('language');
            }

            $userSettings = Settings::updateOrCreate(
                ['userId' => $user->id],
                $settingsData
            );

            // Auto-subscribe Meta app to this user's WABA so inbound webhooks work
            $wabaSubscribe = app(MetaWabaSubscribeService::class)->subscribe($userSettings->fresh());

            $rawWorkingDays = $userSettings?->working_days;
            $workingDays = is_array($rawWorkingDays)
                ? $rawWorkingDays
                : (is_string($rawWorkingDays) ? (json_decode($rawWorkingDays, true) ?: []) : []);

            return response()->json([
                'success' => true,
                'message' => 'User WABA settings updated successfully',
                'wabaSubscribe' => $wabaSubscribe,
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'fbAppId' => $userSettings->fb_app_id ?? '',
                    'fbAppSecret' => $userSettings->fb_app_secret ? '********' : '',
                    'fbAccessToken' => $userSettings->access_token ?? '',
                    'wabaAccountId' => $userSettings->waba_id ?? '',
                    'phoneId' => $userSettings->phoneNumberId ?? '',
                    'webhookUrl' => (!empty($userSettings->webhook_url))
                        ? $userSettings->webhook_url
                        : url('/api/whatsapp/webhook/' . $user->id),
                    'webhookSecret' => $userSettings->webhook_secret ?? '',
                    'isActive' => (bool) $userSettings->isActive,
                    'first_time_message' => $userSettings->first_time_message ?? '',
                    'auto_ai_enabled' => (bool) ($userSettings->auto_ai_enabled ?? false),
                    'business_name' => $userSettings->business_name ?? '',
                    'business_type' => $userSettings->business_type ?? '',
                    'business_details' => $userSettings->business_details ?? '',
                    'business_hours' => $userSettings->business_hours ?? '',
                    'auto_ai_tone' => $userSettings->auto_ai_tone ?? 'friendly',
                    'business_address' => $userSettings->business_address ?? '',
                    'website_url' => $userSettings->website_url ?? '',
                    'support_phone' => $userSettings->support_phone ?? '',
                    'support_email' => $userSettings->support_email ?? '',
                    'industry' => $userSettings->industry ?? '',
                    'services' => $userSettings->services ?? '',
                    'products' => $userSettings->products ?? '',
                    'location' => $userSettings->location ?? '',
                    'working_days' => $workingDays,
                    'language' => $userSettings->language ?? '',
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating user settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually subscribe Meta app to a user's WABA (admin).
     * POST /api/v1/admin/users/{userId}/subscribe-waba
     */
    public function subscribeUserWaba(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', (string) $userId);
            $user = User::find($rawId);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $settings = Settings::where('userId', $user->id)->first();
            if (! $settings) {
                return response()->json([
                    'success' => false,
                    'message' => 'No WhatsApp settings found for this user. Save WABA credentials first.',
                ], 404);
            }

            $result = app(MetaWabaSubscribeService::class)->subscribe($settings);

            return response()->json([
                'success' => (bool) ($result['success'] ?? false),
                'message' => $result['message'] ?? 'Subscribe finished',
                'data' => $result,
            ], ($result['success'] ?? false) ? 200 : 422);
        } catch (\Exception $e) {
            Log::error('subscribeUserWaba failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error subscribing WABA: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Register a User's WhatsApp Phone Number on Meta Cloud API with a 6-digit PIN.
     * POST /api/v1/admin/users/{userId}/register-phone
     * Body: { "pin": "123456", "phoneNumberId": "optional", "access_token": "optional" }
     */
    public function registerUserPhoneNumber(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', (string) $userId);
            $user = User::find($rawId);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $pin = trim((string) $request->input('pin', ''));
            if ($pin === '' || !preg_match('/^\d{6}$/', $pin)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Two-step verification PIN is required and must be exactly 6 digits (e.g. 123456).',
                ], 422);
            }

            $settings = Settings::firstOrNew(['userId' => $user->id]);

            // Allow passing or updating credentials on the fly
            $phoneNumberId = trim((string) ($request->input('phoneNumberId') ?: $request->input('phone_number_id') ?: $settings->phoneNumberId));
            $accessToken = trim((string) ($request->input('access_token') ?: $request->input('accessToken') ?: $settings->access_token));

            if ($phoneNumberId === '' || $accessToken === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Both Phone Number ID and WhatsApp Access Token are required. Please save credentials in user settings or provide them in the request body.',
                ], 422);
            }

            // Save credentials if provided in request
            $updatedCreds = false;
            if ($request->filled('phoneNumberId') || $request->filled('phone_number_id')) {
                $settings->phoneNumberId = $phoneNumberId;
                $updatedCreds = true;
            }
            if ($request->filled('access_token') || $request->filled('accessToken')) {
                $settings->access_token = $accessToken;
                $updatedCreds = true;
            }
            if ($request->filled('waba_id')) {
                $settings->waba_id = trim((string) $request->input('waba_id'));
                $updatedCreds = true;
            }

            // Call Meta Registration Service
            $regService = app(MetaPhoneNumberRegistrationService::class);
            $result = $regService->register($phoneNumberId, $accessToken, $pin, (int) $user->id);

            if ($result['success'] ?? false) {
                $settings->isActive = true;
                $settings->save();

                // Auto-subscribe WABA webhooks if waba_id is available
                $wabaSubscription = null;
                if (!empty($settings->waba_id)) {
                    $wabaSubscription = app(MetaWabaSubscribeService::class)->subscribe($settings);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'WhatsApp Phone Number registered and connected successfully on Meta Cloud API.',
                    'data' => [
                        'userId' => 'usr_' . $user->id,
                        'phone_number_id' => $phoneNumberId,
                        'registration' => $result,
                        'phone_status' => $result['phone_status'] ?? null,
                        'waba_subscription' => $wabaSubscription,
                    ],
                ], 200);
            }

            if ($updatedCreds) {
                $settings->save();
            }

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Failed to register phone number on Meta Cloud API.',
                'error_code' => $result['error_code'] ?? null,
                'meta_error' => $result['meta_error'] ?? null,
            ], 422);

        } catch (\Exception $e) {
            Log::error('registerUserPhoneNumber failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error registering phone number: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Query live status of User's WhatsApp Phone Number on Meta Cloud API.
     * GET /api/v1/admin/users/{userId}/phone-status
     */
    public function getUserPhoneStatus(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', (string) $userId);
            $user = User::find($rawId);

            if (! $user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found',
                ], 404);
            }

            $settings = Settings::where('userId', $user->id)->first();
            $phoneNumberId = trim((string) ($request->query('phoneNumberId') ?: ($settings->phoneNumberId ?? '')));
            $accessToken = trim((string) ($request->query('access_token') ?: ($settings->access_token ?? '')));

            if ($phoneNumberId === '' || $accessToken === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Phone Number ID and Access Token are not configured for this user.',
                ], 422);
            }

            $regService = app(MetaPhoneNumberRegistrationService::class);
            $result = $regService->getPhoneStatus($phoneNumberId, $accessToken);

            return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
        } catch (\Exception $e) {
            Log::error('getUserPhoneStatus failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error querying phone status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Rotate User API Key
     * POST /api/v1/users/{userId}/rotate-api-key or /api/v1/admin/users/{userId}/rotate-api-key
     */
    public function rotateApiKey(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $newApiKey = 'fa_live_sk_usr_' . $user->id . '_' . bin2hex(random_bytes(6));

            return response()->json([
                'success' => true,
                'data' => [
                    'newApiKey' => $newApiKey
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rotating API key: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user wallet status, balance, and history
     * GET /api/v1/admin/users/{userId}/wallet
     */
    public function getUserWallet(Request $request, $userId)
    {
        try {
            app(WalletService::class)->ensureSchema();
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $limit = min(200, max(1, (int) $request->query('limit', 50)));
            $page = max(1, (int) $request->query('page', 1));

            $txnQuery = WalletTransaction::where('userId', $user->id)->orderByDesc('id');
            $totalTxn = (clone $txnQuery)->count();
            $transactions = $txnQuery->skip(($page - 1) * $limit)->take($limit)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'walletEnabled' => (bool) ($user->wallet_enabled ?? false),
                    'balance' => round((float) ($user->wallet_balance ?? 0), 2),
                    'currency' => 'INR',
                    'transactions' => $transactions,
                ],
                'meta' => [
                    'total' => $totalTxn,
                    'page' => $page,
                    'limit' => $limit,
                    'totalPages' => (int) max(1, ceil($totalTxn / $limit)),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching wallet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Enable / disable wallet for a company user
     * PUT/POST /api/v1/admin/users/{userId}/wallet
     */
    public function updateUserWallet(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'walletEnabled' => 'required_without:wallet_enabled',
            'wallet_enabled' => 'required_without:walletEnabled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Send walletEnabled true or false',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $wallet = app(WalletService::class);
            $wallet->ensureSchema();
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $enabled = $this->parseBool($request->input('walletEnabled', $request->input('wallet_enabled')));
            $wallet->setEnabled($user, $enabled);

            return response()->json([
                'success' => true,
                'message' => $enabled ? 'Wallet enabled for this user' : 'Wallet disabled for this user',
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'walletEnabled' => $enabled,
                    'balance' => round((float) $user->wallet_balance, 2),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating wallet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Super admin add / deduct wallet money (no Razorpay)
     * POST /api/v1/admin/users/{userId}/wallet/credit
     */
    public function creditUserWallet(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'amount' => 'required|numeric|not_in:0',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 400);
        }

        try {
            $wallet = app(WalletService::class);
            $wallet->ensureSchema();
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $result = $wallet->adminCredit(
                $user,
                (float) $request->amount,
                $request->input('description', 'Admin wallet credit')
            );

            return response()->json([
                'success' => true,
                'message' => 'Wallet updated successfully',
                'data' => [
                    'userId' => 'usr_' . $user->id,
                    'balance' => $result['balance'],
                    'transaction' => $result['transaction'],
                ]
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error crediting wallet: ' . $e->getMessage()
            ], 500);
        }
    }

    private function parseBool($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
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

        // If files were already parsed by PHP, nothing to do
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

    private function resolvePermissionsArray($user) {
        $stored = is_string($user->permissions) ? json_decode($user->permissions, true) : ($user->permissions ?? []);
        // Backward compatibility: admin with no permissions stored = full access
        if (empty($stored) && in_array(strtolower($user->role ?? ''), ['admin', 'staff'])) {
            return \App\Models\Module::where('status', 'active')->pluck('slug')->toArray();
        }
        return is_array($stored) ? $stored : [];
    }

    private function formatPermissionsObj($userPermsArray) {
        if (!is_array($userPermsArray)) $userPermsArray = [];
        // Dynamically load ONLY active modules from the database
        $allModules = \App\Models\Module::where('status', 'active')
            ->orderBy('id', 'asc')
            ->pluck('slug')
            ->toArray();
        $formatted = [];
        foreach ($allModules as $mod) {
            // Only include modules that exist in the modules table
            $formatted[$mod] = in_array($mod, $userPermsArray) ? 1 : 0;
        }
        return $formatted;
    }

    private function parsePermissionsInput($input) {
        if (is_string($input)) {
            $decoded = json_decode($input, true);
            $input = is_array($decoded) ? $decoded : array_map('trim', explode(',', $input));
        }
        
        if (!is_array($input)) return null;

        // If it's a flat array of strings (e.g. ["contacts", "dashboard"])
        if (array_keys($input) === range(0, count($input) - 1)) {
            return array_values(array_unique($input));
        }

        // If it's an associative array (e.g. {"contacts": 1, "dashboard": 0})
        $allowed = [];
        foreach ($input as $module => $value) {
            if ($value == 1 || $value === true || $value === '1' || strtolower((string)$value) === 'true') {
                $allowed[] = $module;
            }
        }
        return $allowed;
    }

    /**
     * Manage Admin User Permissions (GET, POST, PUT)
     * GET/POST/PUT /api/v1/users/{userId}/permissions
     */
    public function manageUserPermissions(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            // Handle GET Request
            if ($request->isMethod('get')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'id' => 'usr_' . $user->id,
                        'permissions' => $this->formatPermissionsObj($this->resolvePermissionsArray($user))
                    ]
                ], 200);
            }

            // Handle POST/PUT Request
            $validator = Validator::make($request->all(), [
                'permissions' => 'required',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 400);
            }

            $parsedPermissions = $this->parsePermissionsInput($request->input('permissions'));
            
            if (!is_array($parsedPermissions)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid permissions format'
                ], 400);
            }

            $user->permissions = $parsedPermissions;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'User permissions updated successfully',
                'data' => [
                    'id' => 'usr_' . $user->id,
                    'permissions' => $this->formatPermissionsObj($user->permissions)
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing user permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send email reminder to user (Unified endpoint: 'renewal', 'wallet', 'payment')
     * POST /api/v1/admin/users/{userId}/send-reminder
     */
    public function sendReminder(Request $request, $userId)
    {
        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $type = $request->input('type', 'renewal');
            $options = $request->all();

            $reminderService = app(\App\Services\ReminderMailService::class);
            $result = $reminderService->sendReminder($user, $type, $options);

            return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error sending reminder: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send Subscription Renewal Reminder email to user
     * POST /api/v1/admin/users/{userId}/reminder/renewal
     */
    public function sendRenewalReminder(Request $request, $userId)
    {
        $request->merge(['type' => 'renewal']);
        return $this->sendReminder($request, $userId);
    }

    /**
     * Send Wallet Recharge / Low Balance Reminder email to user
     * POST /api/v1/admin/users/{userId}/reminder/wallet
     */
    public function sendWalletReminder(Request $request, $userId)
    {
        $request->merge(['type' => 'wallet']);
        return $this->sendReminder($request, $userId);
    }

    /**
     * Send Payment / Invoice Reminder email to user
     * POST /api/v1/admin/users/{userId}/reminder/payment
     */
    public function sendPaymentReminder(Request $request, $userId)
    {
        $request->merge(['type' => 'payment']);
        return $this->sendReminder($request, $userId);
    }
}

