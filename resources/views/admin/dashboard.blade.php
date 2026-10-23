@extends('admin.layouts.app')

@section('title', 'Super Admin Dashboard')

@section('styles')
<style>
    /* Dashboard Specific Styles */
    .dashboard-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .dashboard-headline {
        font-family: var(--font-display);
        font-size: 1.65rem;
        font-weight: 800;
        letter-spacing: -0.02em;
        margin-bottom: 4px;
    }

    .dashboard-sub {
        font-size: 0.88rem;
        color: var(--text-secondary);
    }

    .dashboard-actions {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .btn-action {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 9px 16px;
        border-radius: var(--radius-md);
        font-size: 0.84rem;
        font-weight: 600;
        text-decoration: none;
        cursor: pointer;
        transition: all 0.18s ease;
        border: 1px solid transparent;
    }

    .btn-action-primary {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
    }

    .btn-action-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
    }

    .btn-action-secondary {
        background: var(--bg-surface-elevated);
        border-color: var(--border);
        color: var(--text-primary);
    }

    .btn-action-secondary:hover {
        background: rgba(255, 255, 255, 0.06);
        border-color: var(--border-hover);
    }

    /* KPI Cards Grid */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .kpi-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 22px;
        position: relative;
        overflow: hidden;
        transition: all 0.2s ease;
    }

    .kpi-card:hover {
        border-color: var(--border-hover);
        background: var(--bg-card-hover);
        transform: translateY(-2px);
    }

    .kpi-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 14px;
    }

    .kpi-icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
    }

    .icon-emerald {
        background: rgba(16, 185, 129, 0.15);
        color: var(--primary);
    }

    .icon-blue {
        background: rgba(59, 130, 246, 0.15);
        color: var(--accent);
    }

    .icon-purple {
        background: rgba(139, 92, 246, 0.15);
        color: var(--accent-purple);
    }

    .icon-amber {
        background: rgba(245, 158, 11, 0.15);
        color: var(--accent-amber);
    }

    .kpi-trend-pill {
        font-size: 0.72rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 20px;
        background: rgba(16, 185, 129, 0.12);
        color: #34d399;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .kpi-label {
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin-bottom: 6px;
    }

    .kpi-value {
        font-family: var(--font-display);
        font-size: 1.85rem;
        font-weight: 800;
        color: var(--text-primary);
        letter-spacing: -0.02em;
        margin-bottom: 6px;
    }

    .kpi-subtext {
        font-size: 0.78rem;
        color: var(--text-secondary);
    }

    /* Meta WABA Status Banner */
    .waba-status-banner {
        background: linear-gradient(90deg, rgba(16, 185, 129, 0.08) 0%, rgba(59, 130, 246, 0.05) 100%);
        border: 1px solid rgba(16, 185, 129, 0.25);
        border-radius: var(--radius-lg);
        padding: 20px 24px;
        margin-bottom: 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 16px;
    }

    .waba-banner-left {
        display: flex;
        align-items: center;
        gap: 16px;
    }

    .waba-banner-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        background: rgba(16, 185, 129, 0.18);
        color: #25d366;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        flex-shrink: 0;
    }

    .waba-banner-title {
        font-family: var(--font-display);
        font-size: 1.05rem;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .waba-banner-desc {
        font-size: 0.84rem;
        color: var(--text-secondary);
    }

    .waba-banner-code {
        font-family: var(--font-mono);
        font-size: 0.78rem;
        background: rgba(0, 0, 0, 0.3);
        padding: 3px 8px;
        border-radius: 4px;
        border: 1px solid var(--border);
        color: #cbd5e1;
    }

    /* 2-Column Section */
    .dashboard-split-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 24px;
        margin-bottom: 30px;
    }

    @media (max-width: 1100px) {
        .dashboard-split-grid {
            grid-template-columns: 1fr;
        }
    }

    .panel-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }

    .panel-header {
        padding: 18px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .panel-title {
        font-family: var(--font-display);
        font-size: 1.05rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .panel-title i {
        color: var(--primary);
    }

    .panel-link {
        font-size: 0.82rem;
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
    }

    .panel-link:hover {
        text-decoration: underline;
    }

    /* Tables */
    .table-responsive {
        overflow-x: auto;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.86rem;
    }

    .admin-table th {
        text-align: left;
        padding: 12px 20px;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        background: rgba(255, 255, 255, 0.015);
        border-bottom: 1px solid var(--border);
    }

    .admin-table td {
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }

    .admin-table tr:hover td {
        background: rgba(255, 255, 255, 0.02);
    }

    .tenant-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .tenant-avatar {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        background: linear-gradient(135deg, #334155, #1e293b);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.82rem;
        color: #e2e8f0;
    }

    .tenant-name {
        font-weight: 600;
        color: var(--text-primary);
    }

    .tenant-company {
        font-size: 0.74rem;
        color: var(--text-muted);
    }

    .badge-status {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 12px;
        text-transform: capitalize;
    }

    .badge-active {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
    }

    .badge-inactive {
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
    }

    .btn-toggle-status {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--border);
        color: var(--text-secondary);
        font-size: 0.75rem;
        font-weight: 600;
        padding: 4px 10px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-toggle-status:hover {
        background: rgba(255, 255, 255, 0.08);
        color: var(--text-primary);
        border-color: var(--border-hover);
    }

    /* Diagnostics List */
    .diagnostic-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
        font-size: 0.85rem;
    }

    .diagnostic-item:last-child {
        border-bottom: none;
    }

    .diagnostic-label {
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .diagnostic-val {
        font-family: var(--font-mono);
        font-weight: 600;
        color: var(--text-primary);
        font-size: 0.82rem;
    }

    /* Packages Row */
    .packages-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .pkg-summary-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 22px;
        position: relative;
    }

    .pkg-badge-popular {
        position: absolute;
        top: 16px;
        right: 16px;
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        color: #ffffff;
        font-size: 0.68rem;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 12px;
        text-transform: uppercase;
    }

    .pkg-name {
        font-family: var(--font-display);
        font-size: 1.15rem;
        font-weight: 800;
        margin-bottom: 4px;
    }

    .pkg-price {
        font-size: 1.4rem;
        font-weight: 800;
        color: var(--primary);
        margin-bottom: 12px;
    }

    .pkg-price span {
        font-size: 0.8rem;
        color: var(--text-muted);
        font-weight: 500;
    }

    .pkg-metrics {
        display: flex;
        flex-direction: column;
        gap: 6px;
        font-size: 0.82rem;
        color: var(--text-secondary);
        padding-top: 10px;
        border-top: 1px solid var(--border);
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .admin-table {
        min-width: 600px;
    }

    @media (max-width: 992px) {
        .dashboard-grid-2 {
            grid-template-columns: 1fr !important;
        }
    }

    @media (max-width: 768px) {
        .dashboard-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .dashboard-actions {
            width: 100%;
            display: flex;
            flex-wrap: wrap;
        }
        .btn-action {
            flex: 1;
            justify-content: center;
        }
        .kpi-grid {
            grid-template-columns: 1fr 1fr;
            gap: 14px;
        }
    }

    @media (max-width: 480px) {
        .kpi-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')

    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div>
            <h1 class="dashboard-headline">Welcome back, {{ $currentUser->name ?: 'Arun' }} 👋</h1>
            <p class="dashboard-sub">Super Admin Command Center &bull; Multi-Tenant WhatsApp Business Cloud API Architecture</p>
        </div>
        <div class="dashboard-actions">
            <a href="{{ route('arun-admin.clients.index') }}" class="btn-action btn-action-primary">
                <i class="fa-solid fa-users"></i>
                <span>Manage Clients</span>
            </a>
            <a href="{{ route('arun-admin.transactions.index') }}" class="btn-action btn-action-secondary">
                <i class="fa-solid fa-receipt"></i>
                <span>Transactions</span>
            </a>
            <a href="{{ route('arun-admin.health') }}" class="btn-action btn-action-secondary">
                <i class="fa-solid fa-server"></i>
                <span>Health Diagnostic</span>
            </a>
        </div>
    </div>

    <!-- 4 High Impact KPI Metric Cards -->
    <div class="kpi-grid">
        <!-- 1. Total Tenants -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-icon-wrap icon-emerald">
                    <i class="fa-solid fa-building-user"></i>
                </div>
                <div class="kpi-trend-pill">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>{{ $activeUsers }} Active</span>
                </div>
            </div>
            <div class="kpi-label">Registered Tenants</div>
            <div class="kpi-value">{{ $totalUsers }}</div>
            <div class="kpi-subtext">{{ $inactiveUsers }} currently pending / inactive</div>
        </div>

        <!-- 2. Broadcast Volume -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-icon-wrap icon-blue">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
                <div class="kpi-trend-pill" style="background: rgba(59, 130, 246, 0.12); color: #60a5fa;">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Cloud API</span>
                </div>
            </div>
            <div class="kpi-label">Broadcast Messages</div>
            <div class="kpi-value">{{ number_format($totalMessagesSent) }}</div>
            <div class="kpi-subtext">Across {{ $totalBroadcasts }} bulk campaign batches</div>
        </div>

        <!-- 3. Active Subscriptions -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-icon-wrap icon-purple">
                    <i class="fa-solid fa-layer-group"></i>
                </div>
                <div class="kpi-trend-pill" style="background: rgba(139, 92, 246, 0.12); color: #a78bfa;">
                    <i class="fa-solid fa-crown"></i>
                    <span>{{ $totalPackages }} Tiers</span>
                </div>
            </div>
            <div class="kpi-label">Active Subscriptions</div>
            <div class="kpi-value">{{ $activeSubscriptions }}</div>
            <div class="kpi-subtext">Recurring customer subscriptions</div>
        </div>

        <!-- 4. Total Wallet Revenue -->
        <div class="kpi-card">
            <div class="kpi-top">
                <div class="kpi-icon-wrap icon-amber">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <div class="kpi-trend-pill" style="background: rgba(245, 158, 11, 0.12); color: #fbbf24;">
                    <i class="fa-solid fa-indian-rupee-sign"></i>
                    <span>Paid</span>
                </div>
            </div>
            <div class="kpi-label">Wallet Recharges</div>
            <div class="kpi-value">₹{{ number_format($totalWalletRevenue, 2) }}</div>
            <div class="kpi-subtext">{{ $totalWalletTransactions }} total billing ledger entries</div>
        </div>
    </div>

    <!-- Meta WhatsApp Cloud API Live Engine Banner -->
    <div class="waba-status-banner">
        <div class="waba-banner-left">
            <div class="waba-banner-icon">
                <i class="fa-brands fa-whatsapp"></i>
            </div>
            <div>
                <div class="waba-banner-title">Official WhatsApp Business Cloud API & Webhook Dispatcher</div>
                <div class="waba-banner-desc">
                    Incoming Webhook Endpoint: <span class="waba-banner-code">{{ url('/whatsapp/webhook') }}</span> &bull; Graph API Version: <span class="waba-banner-code">v20.0</span>
                </div>
            </div>
        </div>
        <div>
            <a href="{{ route('arun-admin.clients.index') }}" class="btn-action btn-action-secondary" style="background: rgba(0, 0, 0, 0.2);">
                <i class="fa-solid fa-users-gear"></i>
                <span>Configure Tenant WABAs</span>
            </a>
        </div>
    </div>

    <!-- 2-Column Split: Recent Tenants + System Diagnostics -->
    <div class="dashboard-split-grid">
        <!-- Left: Recent Tenants Table -->
        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fa-solid fa-users"></i>
                    <span>Recent Platform Tenants</span>
                </div>
                <a href="{{ route('arun-admin.clients.index') }}" class="panel-link">View All Clients &rarr;</a>
            </div>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Tenant Name & Company</th>
                            <th>Contact / Mobile</th>
                            <th>Role</th>
                            <th>Wallet</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentUsers as $user)
                            <tr>
                                <td>
                                    <div class="tenant-cell">
                                        <div class="tenant-avatar">
                                            {{ strtoupper(substr($user->name ?: 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            <div class="tenant-name">
                                                <a href="{{ route('arun-admin.clients.show', $user->id) }}" style="color: inherit; text-decoration: none;">
                                                    {{ $user->name }}
                                                </a>
                                            </div>
                                            <div class="tenant-company">{{ $user->companyName ?: 'Personal Account' }} &bull; {{ $user->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span style="font-family: var(--font-mono); font-size: 0.8rem; color: #cbd5e1;">
                                        {{ $user->number ?: 'N/A' }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: #94a3b8;">
                                        {{ $user->role }}
                                    </span>
                                </td>
                                <td>
                                    <span style="font-family: var(--font-mono); font-weight: 600; color: #10b981;">
                                        ₹{{ number_format($user->wallet_balance ?? 0, 2) }}
                                    </span>
                                </td>
                                <td>
                                    @if($user->status === 'active')
                                        <span class="badge-status badge-active">
                                            <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> Active
                                        </span>
                                    @else
                                        <span class="badge-status badge-inactive">
                                            <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> {{ $user->status }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <a href="{{ route('arun-admin.clients.show', $user->id) }}" style="background: rgba(59, 130, 246, 0.12); border: 1px solid rgba(59, 130, 246, 0.3); color: #60a5fa; font-size: 0.75rem; padding: 4px 8px; border-radius: var(--radius-sm); text-decoration: none;" title="Manage Client">
                                            Manage
                                        </a>
                                        <form action="{{ route('arun-admin.clients.toggle-status', $user->id) }}" method="POST" style="margin: 0;">
                                            @csrf
                                            <button type="submit" class="btn-toggle-status" title="Toggle status">
                                                {{ $user->status === 'active' ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                    No tenant accounts registered yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right: Real-time System Diagnostics -->
        <div class="panel-card">
            <div class="panel-header">
                <div class="panel-title">
                    <i class="fa-solid fa-server"></i>
                    <span>System Architecture</span>
                </div>
                <a href="{{ route('arun-admin.health') }}" class="panel-link">Full Health &rarr;</a>
            </div>

            <div>
                <div class="diagnostic-item">
                    <span class="diagnostic-label"><i class="fa-solid fa-database"></i> Database Connection</span>
                    <span class="diagnostic-val" style="color: #34d399;">{{ $systemInfo['database'] }}</span>
                </div>
                <div class="diagnostic-item">
                    <span class="diagnostic-label"><i class="fa-brands fa-php"></i> PHP Engine</span>
                    <span class="diagnostic-val">{{ $systemInfo['php_version'] }}</span>
                </div>
                <div class="diagnostic-item">
                    <span class="diagnostic-label"><i class="fa-brands fa-laravel"></i> Laravel Framework</span>
                    <span class="diagnostic-val">v{{ $systemInfo['laravel_version'] }}</span>
                </div>
                <div class="diagnostic-item">
                    <span class="diagnostic-label"><i class="fa-solid fa-microchip"></i> Operating System</span>
                    <span class="diagnostic-val">{{ $systemInfo['server_os'] }}</span>
                </div>
                <div class="diagnostic-item">
                    <span class="diagnostic-label"><i class="fa-solid fa-network-wired"></i> Environment</span>
                    <span class="diagnostic-val" style="text-transform: uppercase;">{{ $systemInfo['environment'] }}</span>
                </div>
                <div class="diagnostic-item">
                    <span class="diagnostic-label"><i class="fa-brands fa-whatsapp"></i> Connected WABA</span>
                    <span class="diagnostic-val">{{ $systemInfo['connected_accounts'] }} Account(s)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Subscription Plans Grid Overview -->
    <div style="margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between;">
        <h2 style="font-family: var(--font-display); font-size: 1.15rem; font-weight: 700;">
            <i class="fa-solid fa-cubes" style="color: var(--primary); margin-right: 8px;"></i>
            Active Platform Subscription Packages
        </h2>
        <a href="{{ route('arun-admin.packages') }}" class="panel-link">Manage Plans &rarr;</a>
    </div>

    <div class="packages-row">
        @forelse($packages as $pkg)
            <div class="pkg-summary-card">
                @if($pkg->popular)
                    <div class="pkg-badge-popular">Popular Choice</div>
                @endif
                <div class="pkg-name">{{ $pkg->packageName }}</div>
                <div class="pkg-price">₹{{ $pkg->price }} <span>{{ $pkg->period ?: '/month' }}</span></div>

                <div class="pkg-metrics">
                    <div><i class="fa-solid fa-envelope" style="width: 18px; color: var(--primary);"></i> {{ number_format($pkg->msgCount) }} Messages / mo</div>
                    <div><i class="fa-solid fa-file-lines" style="width: 18px; color: #3b82f6;"></i> {{ $pkg->templateCount }} Approved Templates</div>
                    <div><i class="fa-solid fa-address-book" style="width: 18px; color: #8b5cf6;"></i> {{ number_format($pkg->contactCount ?? 500) }} Max Contacts</div>
                    <div><i class="fa-solid fa-users" style="width: 18px; color: #f59e0b;"></i> {{ $pkg->subscribers_count ?? 0 }} Active Subscribers</div>
                </div>
            </div>
        @empty
            <div style="grid-column: 1 / -1; padding: 24px; text-align: center; color: var(--text-muted); background: var(--bg-card); border-radius: var(--radius-lg);">
                No subscription packages configured. Run database seeders to populate standard tiers.
            </div>
        @endforelse
    </div>

@endsection
