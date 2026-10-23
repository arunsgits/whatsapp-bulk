<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Package;
use App\Models\ActivePackage;
use App\Models\BulkMessage;
use App\Models\Settings;
use App\Models\WalletOrder;
use App\Models\WalletTransaction;
use App\Models\MessageDeliveryStatus;
use App\Models\WhatsappConnectedAccount;
use App\Models\UserPackageHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AdminWebController extends Controller
{
    /**
     * Root /arun-admin handler
     */
    public function index()
    {
        if (Auth::guard('web')->check() && Auth::guard('web')->user()->isSuperAdmin()) {
            return redirect()->route('arun-admin.dashboard');
        }

        return redirect()->route('arun-admin.login');
    }

    /**
     * Show Super Admin Login Form
     */
    public function loginForm()
    {
        if (Auth::guard('web')->check() && Auth::guard('web')->user()->isSuperAdmin()) {
            return redirect()->route('arun-admin.dashboard');
        }

        return view('admin.login');
    }

    /**
     * Handle Super Admin Login Submission
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withInput()->with('error', 'No account found with this email address.');
        }

        if (!Hash::check($request->password, $user->password)) {
            return back()->withInput()->with('error', 'Invalid security credentials.');
        }

        // Verify Super Admin role
        if (!$user->isSuperAdmin()) {
            return back()->withInput()->with('error', 'Access Denied: Only Super Administrators can access this control plane.');
        }

        if ($user->status !== 'active') {
            return back()->withInput()->with('error', 'Your administrative account has been deactivated. Please contact support.');
        }

        Auth::guard('web')->login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->route('arun-admin.dashboard')->with('success', 'Welcome back, ' . ($user->name ?: 'Super Admin') . '! Control plane authenticated.');
    }

    /**
     * Handle Super Admin Logout
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('arun-admin.login')->with('success', 'You have been safely logged out of the Super Admin Panel.');
    }

    /**
     * Helper to enforce Super Admin authentication on protected web routes
     */
    protected function authorizeSuperAdmin()
    {
        if (!Auth::guard('web')->check() || !Auth::guard('web')->user()->isSuperAdmin()) {
            abort(redirect()->route('arun-admin.login')->with('error', 'Session expired or unauthorized. Please log in with Super Admin privileges.'));
        }
    }

    /**
     * Super Admin Dashboard View
     */
    public function dashboard(Request $request)
    {
        $this->authorizeSuperAdmin();

        $currentUser = Auth::guard('web')->user();

        // Platform metrics
        $totalUsers = User::where('role', '!=', 'super_admin')->count();
        $activeUsers = User::where('role', '!=', 'super_admin')->where('status', 'active')->count();
        $inactiveUsers = User::where('role', '!=', 'super_admin')->where('status', '!=', 'active')->count();

        $totalPackages = Package::count();
        $activeSubscriptions = ActivePackage::where('status', 1)->count();
        $totalBroadcasts = BulkMessage::count();

        // Message counts
        $totalMessagesSent = MessageDeliveryStatus::count() ?: BulkMessage::count();
        $totalDelivered = MessageDeliveryStatus::where('status', 'delivered')->count();
        $totalRead = MessageDeliveryStatus::where('status', 'read')->count();
        $totalFailed = MessageDeliveryStatus::where('status', 'failed')->count();

        // Total wallet recharge amount
        $totalWalletRevenue = WalletOrder::where('status', 'paid')->sum('amount') ?: 0;
        $totalWalletTransactions = WalletTransaction::count();

        // Recent users
        $recentUsers = User::where('role', '!=', 'super_admin')
            ->latest('id')
            ->take(8)
            ->get();

        // All packages with active subscriber counts
        $packages = Package::withCount(['activePackages as subscribers_count' => function ($q) {
            $q->where('status', 1);
        }])->get();

        // Global settings
        $globalSettings = Settings::whereNull('userId')
            ->orWhere('userId', $currentUser->id)
            ->first();

        // System Diagnostic Info
        $dbStatus = 'Connected';
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $dbStatus = 'Disconnected';
        }

        $systemInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => $dbStatus,
            'server_os' => PHP_OS,
            'environment' => app()->environment(),
            'server_time' => now()->format('Y-m-d H:i:s T'),
            'connected_accounts' => WhatsappConnectedAccount::count(),
        ];

        return view('admin.dashboard', compact(
            'currentUser',
            'totalUsers',
            'activeUsers',
            'inactiveUsers',
            'totalPackages',
            'activeSubscriptions',
            'totalBroadcasts',
            'totalMessagesSent',
            'totalDelivered',
            'totalRead',
            'totalFailed',
            'totalWalletRevenue',
            'totalWalletTransactions',
            'recentUsers',
            'packages',
            'globalSettings',
            'systemInfo'
        ));
    }

    /**
     * Tenant & User Management Listing View
     */
    public function clients(Request $request)
    {
        $this->authorizeSuperAdmin();

        $query = User::where('role', '!=', 'super_admin')
            ->with(['settings', 'activePackage.package']);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('companyName', 'like', "%{$s}%")
                  ->orWhere('number', 'like', "%{$s}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('package_id')) {
            $pkgId = $request->package_id;
            $query->whereHas('activePackage', function ($q) use ($pkgId) {
                $q->where('packageId', $pkgId)->where('status', 1);
            });
        }

        if ($request->filled('waba_status')) {
            if ($request->waba_status === 'configured') {
                $query->whereHas('settings', function ($q) {
                    $q->whereNotNull('phoneNumberId')->where('phoneNumberId', '!=', '');
                });
            } elseif ($request->waba_status === 'unconfigured') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('settings')
                      ->orWhereHas('settings', function ($sq) {
                          $sq->whereNull('phoneNumberId')->orWhere('phoneNumberId', '');
                      });
                });
            }
        }

        $clients = $query->latest('id')->paginate(15)->withQueryString();
        $packages = Package::all();
        $currentUser = Auth::guard('web')->user();

        // Platform summary stats for header
        $totalClients = User::where('role', '!=', 'super_admin')->count();
        $activeClients = User::where('role', '!=', 'super_admin')->where('status', 'active')->count();
        $totalFloat = User::where('role', '!=', 'super_admin')->sum('wallet_balance') ?: 0;
        $wabaConnectedCount = Settings::whereNotNull('userId')
            ->whereNotNull('phoneNumberId')
            ->where('phoneNumberId', '!=', '')
            ->distinct('userId')
            ->count('userId');

        return view('admin.clients.index', compact(
            'clients',
            'packages',
            'totalClients',
            'activeClients',
            'totalFloat',
            'wabaConnectedCount',
            'currentUser'
        ));
    }

    /**
     * Alias for backward compatibility
     */
    public function users(Request $request)
    {
        return $this->clients($request);
    }

    /**
     * Create / Register a new Tenant Client Workspace
     */
    public function storeClient(Request $request)
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191|unique:users,email',
            'password' => 'required|string|min:6',
            'number' => 'nullable|string|max:30',
            'companyName' => 'nullable|string|max:191',
            'package_id' => 'nullable|exists:packages,id',
            'initial_balance' => 'nullable|numeric|min:0',
        ]);

        $initialBalance = (float) ($request->initial_balance ?? 0);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'temp_password' => $request->password,
            'number' => $request->number,
            'companyName' => $request->companyName ?: $request->name,
            'role' => 'admin',
            'status' => 'active',
            'wallet_enabled' => true,
            'wallet_balance' => $initialBalance,
        ]);

        // Initialize tenant settings row
        Settings::create([
            'userId' => $user->id,
            'isActive' => false,
            'business_name' => $user->companyName,
            'auto_ai_enabled' => false,
            'auto_ai_tone' => 'friendly',
        ]);

        // Credit initial balance transaction if provided
        if ($initialBalance > 0) {
            WalletTransaction::create([
                'userId' => $user->id,
                'type' => 'credit',
                'source' => 'admin',
                'category' => 'recharge',
                'amount' => $initialBalance,
                'balance_after' => $initialBalance,
                'reference_id' => 'INIT-' . strtoupper(uniqid()),
                'description' => 'Initial welcome wallet credit granted by Super Administrator',
            ]);
        }

        // Assign package if selected
        if ($request->filled('package_id')) {
            $package = Package::find($request->package_id);
            if ($package) {
                $days = (int) ($package->day ?: 30);
                ActivePackage::create([
                    'userId' => $user->id,
                    'packageId' => $package->id,
                    'startDate' => now(),
                    'endDate' => now()->addDays($days),
                    'day' => $days,
                    'status' => 1,
                    'usedMsgCount' => 0,
                ]);

                UserPackageHistory::recordOnce([
                    'userId' => $user->id,
                    'packageId' => $package->id,
                    'assignedBy' => Auth::guard('web')->id(),
                    'action' => 'initial_assignment',
                    'packageName' => $package->packageName,
                    'price' => $package->price,
                    'msgCount' => $package->msgCount,
                    'startDate' => now(),
                    'endDate' => now()->addDays($days),
                    'day' => $days,
                    'notes' => 'Initial package assigned upon client registration',
                ]);
            }
        }

        return redirect()->route('arun-admin.clients.show', $user->id)
            ->with('success', "Tenant workspace '{$user->name}' created successfully!");
    }

    /**
     * Tenant Client Detail & Management View
     */
    public function showClient($id)
    {
        $this->authorizeSuperAdmin();

        $client = User::where('role', '!=', 'super_admin')->findOrFail($id);
        $settings = Settings::where('userId', $client->id)->first() 
            ?? new Settings(['userId' => $client->id, 'business_name' => $client->companyName]);

        $activePackage = ActivePackage::where('userId', $client->id)
            ->with('package')
            ->latest('id')
            ->first();

        $packages = Package::all();
        $packageHistories = UserPackageHistory::where('userId', $client->id)
            ->with('package')
            ->latest('id')
            ->take(10)
            ->get();

        $walletTransactions = WalletTransaction::where('userId', $client->id)
            ->latest('id')
            ->take(20)
            ->get();

        $walletOrders = WalletOrder::where('userId', $client->id)
            ->latest('id')
            ->take(20)
            ->get();

        $currentUser = Auth::guard('web')->user();

        // Specific metrics for this client
        $totalRecharged = WalletOrder::where('userId', $client->id)->where('status', 'paid')->sum('amount') ?: 0;
        $totalUsageDebit = WalletTransaction::where('userId', $client->id)->where('type', 'debit')->sum('amount') ?: 0;
        $broadcastsCount = BulkMessage::where('userId', $client->id)->count();

        return view('admin.clients.show', compact(
            'client',
            'settings',
            'activePackage',
            'packages',
            'packageHistories',
            'walletTransactions',
            'walletOrders',
            'totalRecharged',
            'totalUsageDebit',
            'broadcastsCount',
            'currentUser'
        ));
    }

    /**
     * Update Tenant Profile & Account Information
     */
    public function updateClient($id, Request $request)
    {
        $this->authorizeSuperAdmin();

        $client = User::where('role', '!=', 'super_admin')->findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191|unique:users,email,' . $client->id,
            'number' => 'nullable|string|max:30',
            'companyName' => 'nullable|string|max:191',
            'companyEmail' => 'nullable|email|max:191',
            'companyMobile' => 'nullable|string|max:30',
            'companyAddress' => 'nullable|string|max:500',
            'status' => 'required|in:active,inactive',
            'wallet_enabled' => 'nullable|boolean',
            'password' => 'nullable|string|min:6',
        ]);

        $client->name = $request->name;
        $client->email = $request->email;
        $client->number = $request->number;
        $client->companyName = $request->companyName ?: $request->name;
        $client->companyEmail = $request->companyEmail;
        $client->companyMobile = $request->companyMobile;
        $client->companyAddress = $request->companyAddress;
        $client->status = $request->status;
        $client->wallet_enabled = $request->boolean('wallet_enabled');

        if ($request->filled('password')) {
            $client->password = Hash::make($request->password);
            $client->temp_password = $request->password;
        }

        $client->save();

        return back()->with('success', "Client '{$client->name}' profile details updated successfully!");
    }

    /**
     * Update Tenant-Specific Meta WhatsApp Cloud API Credentials
     */
    public function updateClientWaba($id, Request $request)
    {
        $this->authorizeSuperAdmin();

        $client = User::where('role', '!=', 'super_admin')->findOrFail($id);

        $settings = Settings::where('userId', $client->id)->first();
        if (!$settings) {
            $settings = new Settings(['userId' => $client->id]);
        }

        $settings->phoneNumberId = trim((string) $request->phoneNumberId);
        $settings->waba_id = trim((string) $request->waba_id);
        if ($request->filled('access_token')) {
            $settings->access_token = trim((string) $request->access_token);
        }
        $settings->webhook_secret = trim((string) $request->webhook_secret);
        $settings->fb_app_id = trim((string) $request->fb_app_id);
        if ($request->filled('fb_app_secret')) {
            $settings->fb_app_secret = trim((string) $request->fb_app_secret);
        }
        $settings->isActive = $request->boolean('isActive');

        $settings->save();

        return back()->with('success', "Meta WhatsApp Cloud API setup updated for client '{$client->name}'!");
    }

    /**
     * Update Tenant-Specific Auto-AI Engine Configuration
     */
    public function updateClientAi($id, Request $request)
    {
        $this->authorizeSuperAdmin();

        $client = User::where('role', '!=', 'super_admin')->findOrFail($id);

        $settings = Settings::where('userId', $client->id)->first();
        if (!$settings) {
            $settings = new Settings(['userId' => $client->id]);
        }

        $settings->auto_ai_enabled = $request->boolean('auto_ai_enabled');
        if ($request->has('auto_ai_tone')) $settings->auto_ai_tone = trim((string) $request->auto_ai_tone);
        if ($request->has('business_name')) $settings->business_name = trim((string) $request->business_name);
        if ($request->has('business_type')) $settings->business_type = trim((string) $request->business_type);
        if ($request->has('business_details')) $settings->business_details = trim((string) $request->business_details);
        if ($request->has('business_hours')) $settings->business_hours = trim((string) $request->business_hours);
        if ($request->has('first_time_message')) $settings->first_time_message = trim((string) $request->first_time_message);

        $settings->save();

        return back()->with('success', "Auto-AI Engine settings updated for client '{$client->name}'!");
    }

    /**
     * Adjust Tenant Wallet Balance (Manual Credit or Debit)
     */
    public function adjustClientWallet($id, Request $request)
    {
        $this->authorizeSuperAdmin();

        $client = User::where('role', '!=', 'super_admin')->findOrFail($id);

        $request->validate([
            'action' => 'required|in:credit,debit',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
        ]);

        $amount = (float) $request->amount;
        $currentBalance = (float) ($client->wallet_balance ?? 0);

        if ($request->action === 'credit') {
            $newBalance = round($currentBalance + $amount, 2);
            $client->wallet_balance = $newBalance;
            $client->save();

            WalletTransaction::create([
                'userId' => $client->id,
                'type' => 'credit',
                'source' => 'admin',
                'category' => 'adjustment',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_id' => 'ADMIN-CR-' . strtoupper(uniqid()),
                'description' => $request->description,
            ]);

            return back()->with('success', "Successfully credited ₹" . number_format($amount, 2) . " to {$client->name}'s wallet. New balance: ₹" . number_format($newBalance, 2));
        } else {
            $newBalance = round(max(0, $currentBalance - $amount), 2);
            $client->wallet_balance = $newBalance;
            $client->save();

            WalletTransaction::create([
                'userId' => $client->id,
                'type' => 'debit',
                'source' => 'admin',
                'category' => 'adjustment',
                'amount' => $amount,
                'balance_after' => $newBalance,
                'reference_id' => 'ADMIN-DB-' . strtoupper(uniqid()),
                'description' => $request->description,
            ]);

            return back()->with('success', "Successfully debited ₹" . number_format($amount, 2) . " from {$client->name}'s wallet. New balance: ₹" . number_format($newBalance, 2));
        }
    }

    /**
     * Toggle Tenant Wallet Enabled / Disabled Status
     */
    public function toggleClientWallet($id)
    {
        $this->authorizeSuperAdmin();

        $client = User::where('role', '!=', 'super_admin')->findOrFail($id);
        $client->wallet_enabled = !$client->wallet_enabled;
        $client->save();

        $statusStr = $client->wallet_enabled ? 'enabled' : 'disabled';

        return back()->with('success', "Wallet billing for client '{$client->name}' has been {$statusStr}.");
    }

    /**
     * Assign or Upgrade Tenant Subscription Plan
     */
    public function changeClientPlan($id, Request $request)
    {
        $this->authorizeSuperAdmin();

        $client = User::where('role', '!=', 'super_admin')->findOrFail($id);

        $request->validate([
            'package_id' => 'required|exists:packages,id',
            'validity_days' => 'nullable|integer|min:1|max:3650',
        ]);

        $package = Package::findOrFail($request->package_id);
        $days = $request->filled('validity_days') ? (int) $request->validity_days : (int) ($package->day ?: 30);

        // Deactivate all existing active packages for this user
        ActivePackage::where('userId', $client->id)
            ->where('status', 1)
            ->update(['status' => 0]);

        $activePkg = ActivePackage::create([
            'userId' => $client->id,
            'packageId' => $package->id,
            'startDate' => now(),
            'endDate' => now()->addDays($days),
            'day' => $days,
            'status' => 1,
            'usedMsgCount' => 0,
        ]);

        UserPackageHistory::recordOnce([
            'userId' => $client->id,
            'packageId' => $package->id,
            'assignedBy' => Auth::guard('web')->id(),
            'action' => 'admin_plan_assignment',
            'packageName' => $package->packageName,
            'price' => $package->price,
            'msgCount' => $package->msgCount,
            'startDate' => now(),
            'endDate' => now()->addDays($days),
            'day' => $days,
            'notes' => "Assigned {$package->packageName} with {$days} days validity by Super Administrator.",
        ]);

        return back()->with('success', "Plan '{$package->packageName}' ({$days} days) assigned successfully to {$client->name}!");
    }

    /**
     * Toggle Tenant Account Status
     */
    public function toggleClientStatus($id)
    {
        $this->authorizeSuperAdmin();

        $user = User::where('role', '!=', 'super_admin')->findOrFail($id);
        $user->status = ($user->status === 'active') ? 'inactive' : 'active';
        $user->save();

        return back()->with('success', "Tenant '{$user->name}' status updated to {$user->status}.");
    }

    /**
     * Alias for backward compatibility
     */
    public function toggleUserStatus(Request $request, $id)
    {
        return $this->toggleClientStatus($id);
    }

    /**
     * Subscription Packages Management View
     */
    public function packages()
    {
        $this->authorizeSuperAdmin();

        $packages = Package::withCount(['activePackages as subscribers_count' => function ($q) {
            $q->where('status', 1);
        }])->latest('id')->get();

        $currentUser = Auth::guard('web')->user();

        return view('admin.packages', compact('packages', 'currentUser'));
    }

    /**
     * Create New Subscription Package
     */
    public function storePackage(Request $request)
    {
        $this->authorizeSuperAdmin();

        $request->validate([
            'packageName' => 'required|string|max:191',
            'packageDesc' => 'nullable|string|max:500',
            'price' => 'required|string',
            'renewal_price' => 'nullable|string',
            'period' => 'nullable|string',
            'day' => 'required|integer|min:1',
            'msgCount' => 'required|integer|min:0',
            'templateCount' => 'required|integer|min:0',
            'contactCount' => 'nullable|integer|min:0',
        ]);

        $features = [];
        if ($request->filled('features')) {
            $features = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $request->features))));
        }

        Package::create([
            'packageName' => $request->packageName,
            'packageDesc' => $request->packageDesc,
            'price' => $request->price,
            'renewal_price' => $request->renewal_price ?: $request->price,
            'period' => $request->period ?: '/month',
            'day' => (int) $request->day,
            'msgCount' => (int) $request->msgCount,
            'templateCount' => (int) $request->templateCount,
            'contactCount' => (int) ($request->contactCount ?: 500),
            'popular' => $request->boolean('popular'),
            'features' => array_values($features),
            'color' => $request->color ?: 'emerald',
            'iconName' => $request->iconName ?: 'zap',
        ]);

        return back()->with('success', "Subscription Package '{$request->packageName}' created successfully!");
    }

    /**
     * Update Subscription Package
     */
    public function updatePackage(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $package = Package::findOrFail($id);

        $request->validate([
            'packageName' => 'required|string|max:191',
            'packageDesc' => 'nullable|string|max:500',
            'price' => 'required|string',
            'renewal_price' => 'nullable|string',
            'period' => 'nullable|string',
            'day' => 'required|integer|min:1',
            'msgCount' => 'required|integer|min:0',
            'templateCount' => 'required|integer|min:0',
            'contactCount' => 'nullable|integer|min:0',
        ]);

        $features = [];
        if ($request->filled('features')) {
            $features = array_filter(array_map('trim', explode("\n", str_replace("\r", "", $request->features))));
        }

        $package->update([
            'packageName' => $request->packageName,
            'packageDesc' => $request->packageDesc,
            'price' => $request->price,
            'renewal_price' => $request->renewal_price ?: $request->price,
            'period' => $request->period ?: '/month',
            'day' => (int) $request->day,
            'msgCount' => (int) $request->msgCount,
            'templateCount' => (int) $request->templateCount,
            'contactCount' => (int) ($request->contactCount ?: 500),
            'popular' => $request->boolean('popular'),
            'features' => array_values($features),
            'color' => $request->color ?: $package->color,
            'iconName' => $request->iconName ?: $package->iconName,
        ]);

        return back()->with('success', "Subscription Package '{$package->packageName}' updated successfully!");
    }

    /**
     * Delete Subscription Package
     */
    public function deletePackage(Request $request, $id)
    {
        $this->authorizeSuperAdmin();

        $package = Package::findOrFail($id);
        $name = $package->packageName;
        $package->delete();

        return back()->with('success', "Subscription Package '{$name}' deleted successfully!");
    }

    /**
     * Global Platform Settings View
     */
    public function settings()
    {
        $this->authorizeSuperAdmin();

        $currentUser = Auth::guard('web')->user();
        $settings = Settings::where('userId', $currentUser->id)
            ->orWhereNull('userId')
            ->first() ?? new Settings(['userId' => $currentUser->id]);

        return view('admin.settings', compact('settings', 'currentUser'));
    }

    /**
     * Update Global Platform Settings
     */
    public function updateSettings(Request $request)
    {
        $this->authorizeSuperAdmin();

        $currentUser = Auth::guard('web')->user();
        $settings = Settings::where('userId', $currentUser->id)
            ->orWhereNull('userId')
            ->first();

        if (!$settings) {
            $settings = new Settings(['userId' => $currentUser->id]);
        }

        // 1. Platform Identity & Status
        if ($request->has('business_name')) $settings->business_name = trim((string) $request->business_name);
        if ($request->has('support_email')) $settings->support_email = trim((string) $request->support_email);
        if ($request->has('support_phone')) $settings->support_phone = trim((string) $request->support_phone);
        if ($request->has('website_url')) $settings->website_url = trim((string) $request->website_url);
        $settings->is_app_live = $request->boolean('is_app_live');

        // 2. Razorpay Gateway Keys
        if ($request->has('razorpay_key_id')) $settings->razorpay_key_id = trim((string) $request->razorpay_key_id);
        if ($request->filled('razorpay_key_secret')) $settings->razorpay_key_secret = trim((string) $request->razorpay_key_secret);
        if ($request->has('razorpay_webhook_secret')) $settings->razorpay_webhook_secret = trim((string) $request->razorpay_webhook_secret);

        // 3. SMTP Mail Server
        if ($request->has('smtp_host')) $settings->smtp_host = trim((string) $request->smtp_host);
        if ($request->has('smtp_port')) $settings->smtp_port = (int) $request->smtp_port;
        if ($request->has('smtp_user')) $settings->smtp_user = trim((string) $request->smtp_user);
        if ($request->filled('smtp_password')) $settings->smtp_password = trim((string) $request->smtp_password);
        if ($request->has('smtp_cc_email')) $settings->smtp_cc_email = trim((string) $request->smtp_cc_email);
        $settings->smtp_cc_enabled = $request->boolean('smtp_cc_enabled');

        // 4. Batch broadcast processing
        $settings->enable_batch_processing = $request->boolean('enable_batch_processing');
        if ($request->has('batch_size')) $settings->batch_size = (int) $request->batch_size;
        if ($request->has('batch_delay_seconds')) $settings->batch_delay_seconds = (int) $request->batch_delay_seconds;

        // 5. Wallet & conversation pricing
        if ($request->has('wallet_price_marketing')) $settings->wallet_price_marketing = (float) $request->wallet_price_marketing;
        if ($request->has('wallet_price_utility')) $settings->wallet_price_utility = (float) $request->wallet_price_utility;
        if ($request->has('wallet_price_authentication')) $settings->wallet_price_authentication = (float) $request->wallet_price_authentication;
        if ($request->has('wallet_min_balance')) $settings->wallet_min_balance = (float) $request->wallet_min_balance;

        // 6. GST Invoicing
        $settings->gst_enabled = $request->boolean('gst_enabled');
        if ($request->has('gst_percentage')) $settings->gst_percentage = (float) $request->gst_percentage;
        if ($request->has('gstin')) $settings->gstin = trim((string) $request->gstin);
        if ($request->has('gst_state')) $settings->gst_state = trim((string) $request->gst_state);

        $settings->save();

        return back()->with('success', 'Global platform configurations saved successfully!');
    }

    /**
     * Super Admin Global Financial Ledger & Transactions History View
     */
    public function transactions(Request $request)
    {
        $this->authorizeSuperAdmin();

        $currentUser = Auth::guard('web')->user();

        // Global Financial Metrics
        $totalRecharges = WalletOrder::where('status', 'paid')->sum('amount') ?: 0;
        $totalDebitUsage = WalletTransaction::where('type', 'debit')->sum('amount') ?: 0;
        $netWalletFloat = User::where('role', '!=', 'super_admin')->sum('wallet_balance') ?: 0;
        $totalTransactionsCount = WalletTransaction::count();
        $totalOrdersCount = WalletOrder::count();

        // 1. Transactions Query
        $txnQuery = WalletTransaction::with('user');

        if ($request->filled('search')) {
            $s = trim($request->search);
            $txnQuery->where(function ($q) use ($s) {
                $q->where('reference_id', 'like', "%{$s}%")
                  ->orWhere('description', 'like', "%{$s}%")
                  ->orWhereHas('user', function ($uq) use ($s) {
                      $uq->where('name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('companyName', 'like', "%{$s}%");
                  });
            });
        }

        if ($request->filled('type') && in_array($request->type, ['credit', 'debit'])) {
            $txnQuery->where('type', $request->type);
        }

        if ($request->filled('category')) {
            $txnQuery->where('category', $request->category);
        }

        $transactions = $txnQuery->latest('id')->paginate(20, ['*'], 'txns_page')->withQueryString();

        // 2. Orders Query (Razorpay recharges & invoices)
        $orderQuery = WalletOrder::with(['user', 'package']);

        if ($request->filled('search')) {
            $s = trim($request->search);
            $orderQuery->where(function ($q) use ($s) {
                $q->where('razorpay_order_id', 'like', "%{$s}%")
                  ->orWhere('receipt', 'like', "%{$s}%")
                  ->orWhere('razorpay_payment_id', 'like', "%{$s}%")
                  ->orWhereHas('user', function ($uq) use ($s) {
                      $uq->where('name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%");
                  });
            });
        }

        if ($request->filled('order_status')) {
            $orderQuery->where('status', $request->order_status);
        }

        $orders = $orderQuery->latest('id')->paginate(20, ['*'], 'orders_page')->withQueryString();

        return view('admin.transactions.index', compact(
            'transactions',
            'orders',
            'totalRecharges',
            'totalDebitUsage',
            'netWalletFloat',
            'totalTransactionsCount',
            'totalOrdersCount',
            'currentUser'
        ));
    }

    /**
     * Super Admin Profile View
     */
    public function profile()
    {
        $this->authorizeSuperAdmin();

        $currentUser = Auth::guard('web')->user();

        return view('admin.profile', compact('currentUser'));
    }

    /**
     * Update Super Admin Profile
     */
    public function updateProfile(Request $request)
    {
        $this->authorizeSuperAdmin();

        $user = Auth::guard('web')->user();

        $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191|unique:users,email,' . $user->id,
            'number' => 'nullable|string|max:50',
            'companyName' => 'nullable|string|max:191',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,webp,svg|max:2048',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->number = $request->number;
        $user->companyName = $request->companyName ?: 'ZapReach Technologies';

        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $filename = 'admin_' . time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/profiles'), $filename);
            $user->profileImage = '/uploads/profiles/' . $filename;
        }

        $user->save();

        return back()->with('success', 'Profile information updated successfully!');
    }

    /**
     * Change Super Admin Password
     */
    public function changePassword(Request $request)
    {
        $this->authorizeSuperAdmin();

        $user = Auth::guard('web')->user();

        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->with('error', 'Your current password does not match our records.');
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return back()->with('success', 'Your password has been changed securely.');
    }

    /**
     * System Health & Diagnostic View
     */
    public function health()
    {
        $this->authorizeSuperAdmin();

        $currentUser = Auth::guard('web')->user();

        $dbStatus = 'Connected';
        $dbLatency = 0;
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $dbLatency = round((microtime(true) - $start) * 1000, 2);
        } catch (\Throwable $e) {
            $dbStatus = 'Connection Failed: ' . $e->getMessage();
        }

        $cacheStatus = 'Operational';
        try {
            Cache::put('probe_test', 1, 5);
            $cacheStatus = Cache::get('probe_test') ? 'Operational' : 'Degraded';
        } catch (\Throwable $e) {
            $cacheStatus = 'Failed: ' . $e->getMessage();
        }

        $systemInfo = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'db_status' => $dbStatus,
            'db_latency' => $dbLatency . ' ms',
            'cache_status' => $cacheStatus,
            'server_os' => PHP_OS,
            'environment' => app()->environment(),
            'app_url' => config('app.url'),
            'app_name' => config('app.name'),
            'company_name' => config('app.company_name'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time') . 's',
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'loaded_extensions' => ['pdo', 'openssl', 'mbstring', 'curl', 'json', 'tokenizer', 'xml'],
        ];

        return view('admin.health', compact('systemInfo', 'currentUser'));
    }
}
