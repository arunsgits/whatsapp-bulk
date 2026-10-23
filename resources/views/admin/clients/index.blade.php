@extends('admin.layouts.app')

@section('title', 'Tenant & Client Workspaces')

@section('styles')
<style>
    .page-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 16px;
    }

    .page-title {
        font-family: var(--font-display);
        font-size: 1.55rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    /* KPI Summary Cards */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        gap: 18px;
        margin-bottom: 24px;
    }

    .kpi-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px 22px;
        display: flex;
        align-items: center;
        gap: 16px;
        transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
        position: relative;
        overflow: hidden;
    }

    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 2px;
        background: transparent;
        transition: background 0.2s ease;
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        border-color: var(--border-hover);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.35);
    }

    .kpi-card.kpi-indigo:hover::before { background: #6366f1; }
    .kpi-card.kpi-emerald:hover::before { background: #10b981; }
    .kpi-card.kpi-amber:hover::before { background: #f59e0b; }
    .kpi-card.kpi-cyan:hover::before { background: #06b6d4; }

    .kpi-icon-wrap {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
        flex-shrink: 0;
    }

    .icon-wrap-indigo {
        background: rgba(99, 102, 241, 0.15);
        color: #818cf8;
        border: 1px solid rgba(99, 102, 241, 0.25);
    }

    .icon-wrap-emerald {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        border: 1px solid rgba(16, 185, 129, 0.25);
    }

    .icon-wrap-amber {
        background: rgba(245, 158, 11, 0.15);
        color: #fbbf24;
        border: 1px solid rgba(245, 158, 11, 0.25);
    }

    .icon-wrap-cyan {
        background: rgba(6, 182, 212, 0.15);
        color: #38bdf8;
        border: 1px solid rgba(6, 182, 212, 0.25);
    }

    .kpi-val {
        font-family: var(--font-display);
        font-size: 1.5rem;
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 4px;
    }

    .kpi-lbl {
        font-size: 0.74rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }

    /* Live Filter & Search Bar */
    .filter-bar {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 16px 20px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 14px;
    }

    .search-input-wrap {
        position: relative;
        flex: 1;
        min-width: 240px;
        max-width: 440px;
    }

    .search-input-wrap i {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-muted);
    }

    .search-field {
        width: 100%;
        height: 42px;
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 0 36px 0 38px;
        font-size: 0.88rem;
        color: var(--text-primary);
        outline: none;
        transition: border-color 0.18s ease;
    }

    .search-field:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 3px var(--primary-glow);
    }

    .btn-search-clear {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        display: none;
    }

    /* Quick Filter Chips */
    .filter-chips-row {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 16px;
    }

    .filter-chip {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid var(--border);
        color: var(--text-secondary);
        font-size: 0.78rem;
        font-weight: 600;
        padding: 6px 14px;
        border-radius: 20px;
        cursor: pointer;
        transition: all 0.15s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .filter-chip:hover {
        background: rgba(255, 255, 255, 0.08);
        color: var(--text-primary);
    }

    .filter-chip.active {
        background: var(--primary-glow);
        border-color: var(--primary);
        color: #ffffff;
    }

    .filter-chip-count {
        font-size: 0.7rem;
        opacity: 0.75;
        font-family: var(--font-mono);
    }

    .select-filter {
        height: 42px;
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 0 14px;
        font-size: 0.84rem;
        color: var(--text-primary);
        outline: none;
        cursor: pointer;
    }

    .select-filter:focus {
        border-color: var(--primary);
    }

    /* Clients Table */
    .table-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.86rem;
        min-width: 900px;
    }

    .admin-table th {
        text-align: left;
        padding: 14px 18px;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        background: rgba(255, 255, 255, 0.02);
        border-bottom: 1px solid var(--border);
        font-weight: 700;
        white-space: nowrap;
    }

    .admin-table td {
        padding: 16px 18px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
        transition: background 0.15s ease;
    }

    .admin-table tr:hover td {
        background: rgba(255, 255, 255, 0.025);
    }

    .tenant-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .tenant-avatar {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 0.95rem;
        color: #ffffff;
        flex-shrink: 0;
        box-shadow: 0 2px 8px var(--primary-glow);
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
        border: 1px solid rgba(16, 185, 129, 0.25);
    }

    .badge-inactive {
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.25);
    }

    .badge-pill-waba {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 9px;
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 700;
        font-family: var(--font-mono);
    }

    .waba-configured {
        background: rgba(37, 211, 102, 0.14);
        color: #25d366;
        border: 1px solid rgba(37, 211, 102, 0.3);
    }

    .waba-unconfigured {
        background: rgba(255, 255, 255, 0.04);
        color: var(--text-muted);
        border: 1px solid var(--border);
    }

    .badge-pill-ai {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 9px;
        border-radius: 6px;
        font-size: 0.72rem;
        font-weight: 700;
    }

    .ai-enabled {
        background: rgba(139, 92, 246, 0.18);
        color: #c4b5fd;
        border: 1px solid rgba(139, 92, 246, 0.3);
    }

    .ai-disabled {
        background: rgba(255, 255, 255, 0.04);
        color: var(--text-muted);
    }

    /* Action Buttons in Rows */
    .btn-action-view {
        background: rgba(99, 102, 241, 0.12);
        border: 1px solid rgba(99, 102, 241, 0.3);
        color: #a5b4fc;
        font-size: 0.78rem;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: var(--radius-sm);
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
    }

    .btn-action-view:hover {
        background: rgba(99, 102, 241, 0.25);
        color: #ffffff;
    }

    .btn-action-wallet {
        background: rgba(245, 158, 11, 0.14);
        border: 1px solid rgba(245, 158, 11, 0.35);
        color: #fbbf24;
        font-size: 0.78rem;
        font-weight: 700;
        padding: 6px 12px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.15s ease;
    }

    .btn-action-wallet:hover {
        background: rgba(245, 158, 11, 0.28);
        color: #fef08a;
        transform: translateY(-1px);
    }

    .btn-primary-create {
        background: var(--primary-gradient);
        color: #ffffff;
        border: none;
        padding: 10px 20px;
        border-radius: var(--radius-md);
        font-size: 0.88rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 14px var(--primary-glow);
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-primary-create:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px var(--primary-glow);
    }

    .copy-chip {
        cursor: pointer;
        transition: color 0.15s ease;
        position: relative;
    }

    .copy-chip:hover {
        color: var(--primary) !important;
    }

    .pagination-wrap {
        padding: 18px 24px;
        display: flex;
        justify-content: flex-end;
    }

    /* Modal Styling */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(4, 7, 13, 0.8);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 1000;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }

    .modal-overlay.open {
        display: flex;
    }

    .modal-card {
        background: #0f172a;
        border: 1px solid var(--border-hover);
        border-radius: var(--radius-xl);
        width: 100%;
        max-width: 580px;
        max-height: 92vh;
        overflow-y: auto;
        box-shadow: 0 25px 60px -15px rgba(0, 0, 0, 0.7);
        animation: modalScale 0.22s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes modalScale {
        from { transform: scale(0.94); opacity: 0; }
        to { transform: scale(1); opacity: 1; }
    }

    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .modal-title {
        font-family: var(--font-display);
        font-size: 1.15rem;
        font-weight: 800;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .modal-close-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border);
        color: var(--text-muted);
        font-size: 1.1rem;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.15s ease;
    }

    .modal-close-btn:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.12);
    }

    .modal-body {
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 16px;
    }

    .modal-footer {
        padding: 16px 24px;
        border-top: 1px solid var(--border);
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        background: rgba(0, 0, 0, 0.25);
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-label {
        font-size: 0.74rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }

    .form-input {
        width: 100%;
        height: 42px;
        background: rgba(0, 0, 0, 0.35);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 0 14px;
        font-size: 0.88rem;
        color: var(--text-primary);
        outline: none;
    }

    .form-input:focus {
        border-color: var(--primary);
    }

    /* Segmented Action Buttons for Credit / Debit */
    .action-segmented-wrap {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
    }

    .btn-segmented {
        padding: 12px;
        border-radius: var(--radius-md);
        border: 2px solid var(--border);
        background: rgba(255, 255, 255, 0.03);
        color: var(--text-secondary);
        font-weight: 700;
        font-size: 0.86rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: all 0.18s ease;
    }

    .btn-segmented.active-credit {
        background: rgba(16, 185, 129, 0.15);
        border-color: #10b981;
        color: #34d399;
    }

    .btn-segmented.active-debit {
        background: rgba(239, 68, 68, 0.15);
        border-color: #ef4444;
        color: #f87171;
    }

    /* Balance Projection Box */
    .balance-projection-card {
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 14px 18px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 14px;
    }

    .preset-chips-wrap {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-top: 8px;
    }

    .btn-preset-chip {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--border);
        color: var(--text-secondary);
        font-family: var(--font-mono);
        font-size: 0.76rem;
        font-weight: 700;
        padding: 5px 11px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-preset-chip:hover {
        background: var(--primary-glow);
        color: #ffffff;
        border-color: var(--primary);
    }

    /* Mobile media queries */
    @media (max-width: 768px) {
        .kpi-grid {
            grid-template-columns: 1fr 1fr;
        }
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        .filter-bar {
            flex-direction: column;
            align-items: stretch;
        }
        .search-input-wrap {
            max-width: 100%;
        }
        .modal-card {
            max-width: 95vw;
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

    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Tenant &amp; Client Workspaces</h1>
            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-top: 2px;">
                Manage registered business workspaces, individual Meta Cloud API credentials, Auto-AI assistants, and wallet floats.
            </p>
        </div>
        <div>
            <button type="button" class="btn-primary-create" onclick="openCreateModal()">
                <i class="fa-solid fa-user-plus"></i>
                <span>Register New Client</span>
            </button>
        </div>
    </div>

    <!-- KPI Summary Grid with Multi-Color Accents -->
    <div class="kpi-grid">
        <!-- KPI 1: Total Registered Clients -->
        <div class="kpi-card kpi-indigo">
            <div class="kpi-icon-wrap icon-wrap-indigo">
                <i class="fa-solid fa-building-user"></i>
            </div>
            <div>
                <div class="kpi-val">{{ number_format($totalClients) }}</div>
                <div class="kpi-lbl">Total Clients</div>
            </div>
        </div>

        <!-- KPI 2: Active Clients -->
        <div class="kpi-card kpi-emerald">
            <div class="kpi-icon-wrap icon-wrap-emerald">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <div>
                <div class="kpi-val">{{ number_format($activeClients) }}</div>
                <div class="kpi-lbl">Active Workspaces</div>
            </div>
        </div>

        <!-- KPI 3: Net Wallet Float Pool -->
        <div class="kpi-card kpi-amber">
            <div class="kpi-icon-wrap icon-wrap-amber">
                <i class="fa-solid fa-coins"></i>
            </div>
            <div>
                <div class="kpi-val" style="color: #fbbf24;">₹{{ number_format($totalFloat, 2) }}</div>
                <div class="kpi-lbl">Total Wallet Float</div>
            </div>
        </div>

        <!-- KPI 4: Active WABA Setups -->
        <div class="kpi-card kpi-cyan">
            <div class="kpi-icon-wrap icon-wrap-cyan">
                <i class="fa-brands fa-whatsapp"></i>
            </div>
            <div>
                <div class="kpi-val" style="color: #38bdf8;">{{ number_format($wabaConnectedCount) }}</div>
                <div class="kpi-lbl">Meta WABA Active Setups</div>
            </div>
        </div>
    </div>

    <!-- Quick Filter Chips for Instant Toggling -->
    <div class="filter-chips-row">
        <button type="button" class="filter-chip active" data-filter="all" onclick="applyClientFilter('all', this)">
            <i class="fa-solid fa-list-check"></i> All Clients <span class="filter-chip-count">({{ $clients->count() }})</span>
        </button>
        <button type="button" class="filter-chip" data-filter="active" onclick="applyClientFilter('active', this)">
            <i class="fa-solid fa-circle" style="color: #10b981; font-size: 0.5rem;"></i> Active Workspaces
        </button>
        <button type="button" class="filter-chip" data-filter="wallet-enabled" onclick="applyClientFilter('wallet-enabled', this)">
            <i class="fa-solid fa-wallet" style="color: #34d399;"></i> Wallet Enabled
        </button>
        <button type="button" class="filter-chip" data-filter="wallet-frozen" onclick="applyClientFilter('wallet-frozen', this)">
            <i class="fa-solid fa-lock" style="color: #f87171;"></i> Wallet Frozen
        </button>
        <button type="button" class="filter-chip" data-filter="waba-ready" onclick="applyClientFilter('waba-ready', this)">
            <i class="fa-brands fa-whatsapp" style="color: #25d366;"></i> WABA Ready
        </button>
        <button type="button" class="filter-chip" data-filter="ai-active" onclick="applyClientFilter('ai-active', this)">
            <i class="fa-solid fa-robot" style="color: #c4b5fd;"></i> AI Active
        </button>
    </div>

    <!-- Search & Backend Filter Bar -->
    <form method="GET" action="{{ route('arun-admin.clients.index') }}" class="filter-bar" id="clientsSearchForm">
        <div class="search-input-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" id="clientSearchInput" class="search-field" placeholder="Search by client name, email, phone, company..." value="{{ request('search') }}" oninput="liveFilterClients()">
            <button type="button" class="btn-search-clear" id="btnSearchClear" onclick="clearClientSearch()">&times;</button>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <select name="status" class="select-filter" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Active Only</option>
                <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive Only</option>
            </select>

            <select name="package_id" class="select-filter" onchange="this.form.submit()">
                <option value="">All Subscription Plans</option>
                @foreach($packages as $pkg)
                    <option value="{{ $pkg->id }}" {{ request('package_id') == $pkg->id ? 'selected' : '' }}>
                        {{ $pkg->packageName }}
                    </option>
                @endforeach
            </select>

            <button type="submit" class="btn-action-view" style="height: 42px; padding: 0 16px;">
                <i class="fa-solid fa-filter"></i>
                <span>Filter</span>
            </button>

            @if(request()->hasAny(['search', 'status', 'waba_status', 'package_id']))
                <a href="{{ route('arun-admin.clients.index') }}" class="btn-action-view" style="height: 42px; padding: 0 14px; background: rgba(255,255,255,0.05); color: var(--text-secondary); border-color: var(--border);">
                    <i class="fa-solid fa-rotate-left"></i>
                    <span>Reset</span>
                </a>
            @endif
        </div>
    </form>

    <!-- Clients Table Card -->
    <div class="table-card">
        <div class="table-responsive">
            <table class="admin-table" id="clientsTable">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>Tenant Workspace</th>
                        <th>Contact Number</th>
                        <th>Assigned Plan</th>
                        <th>Meta WABA Setup</th>
                        <th>Auto-AI Bot</th>
                        <th>Wallet Float &amp; Billing</th>
                        <th>Status</th>
                        <th style="text-align: right; width: 190px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="clientsTableBody">
                    @forelse($clients as $client)
                        @php
                            $setting = $client->settings;
                            $hasWaba = $setting && !empty($setting->phoneNumberId);
                            $aiActive = $setting && $setting->auto_ai_enabled;
                            $activePkg = $client->activePackage;
                            $walletEnabled = (bool) ($client->wallet_enabled ?? true);
                        @endphp
                        <tr class="client-row"
                            data-name="{{ strtolower($client->name) }}"
                            data-email="{{ strtolower($client->email) }}"
                            data-company="{{ strtolower($client->companyName ?? '') }}"
                            data-phone="{{ strtolower($client->number ?? '') }}"
                            data-status="{{ $client->status }}"
                            data-wallet-status="{{ $walletEnabled ? 'enabled' : 'frozen' }}"
                            data-waba="{{ $hasWaba ? 'configured' : 'unconfigured' }}"
                            data-ai="{{ $aiActive ? 'enabled' : 'disabled' }}">
                            <td style="font-family: var(--font-mono); font-weight: 700; color: var(--text-muted);">
                                #{{ $client->id }}
                            </td>
                            <td>
                                <div class="tenant-cell">
                                    <div class="tenant-avatar">
                                        {{ strtoupper(substr($client->name ?: 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        <div style="font-weight: 700; color: var(--text-primary);">
                                            <a href="{{ route('arun-admin.clients.show', $client->id) }}" style="color: inherit; text-decoration: none;">
                                                {{ $client->name }}
                                            </a>
                                        </div>
                                        <div style="font-size: 0.78rem; color: var(--text-muted); margin-top: 2px;">
                                            <span>{{ $client->companyName ?: 'Personal Workspace' }}</span> &bull; 
                                            <span class="copy-chip" onclick="copyText('{{ $client->email }}')" title="Click to copy email">{{ $client->email }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="copy-chip" style="font-family: var(--font-mono); font-size: 0.82rem; color: #cbd5e1;" onclick="copyText('{{ $client->number }}')" title="Click to copy phone">
                                    {{ $client->number ?: 'N/A' }}
                                </span>
                            </td>
                            <td>
                                @if($activePkg && $activePkg->package)
                                    <span style="font-size: 0.75rem; font-weight: 700; color: #818cf8; background: rgba(99, 102, 241, 0.12); padding: 4px 10px; border-radius: 6px; border: 1px solid rgba(99, 102, 241, 0.25);">
                                        {{ $activePkg->package->packageName }}
                                    </span>
                                @else
                                    <span style="font-size: 0.72rem; color: var(--text-muted);">
                                        No Active Plan
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($hasWaba)
                                    <span class="badge-pill-waba waba-configured" title="Phone ID: {{ $setting->phoneNumberId }}">
                                        <i class="fa-brands fa-whatsapp"></i>
                                        <span>Ready ({{ substr($setting->phoneNumberId, -4) }})</span>
                                    </span>
                                @else
                                    <span class="badge-pill-waba waba-unconfigured">
                                        <i class="fa-solid fa-circle-xmark"></i>
                                        <span>Not Configured</span>
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($aiActive)
                                    <span class="badge-pill-ai ai-enabled">
                                        <i class="fa-solid fa-robot"></i>
                                        <span>AI Active</span>
                                    </span>
                                @else
                                    <span class="badge-pill-ai ai-disabled">
                                        <i class="fa-solid fa-power-off"></i>
                                        <span>Disabled</span>
                                    </span>
                                @endif
                            </td>
                            <td>
                                <div style="font-family: var(--font-mono); font-weight: 800; color: #fbbf24; font-size: 0.96rem;">
                                    ₹{{ number_format($client->wallet_balance ?? 0, 2) }}
                                </div>
                                <form action="{{ route('arun-admin.clients.toggle-wallet', $client->id) }}" method="POST" style="margin: 4px 0 0 0;">
                                    @csrf
                                    <button type="submit" style="background: none; border: none; cursor: pointer; padding: 0;" title="Click to {{ $walletEnabled ? 'Freeze / Disable' : 'Enable' }} Wallet Billing">
                                        @if($walletEnabled)
                                            <span style="font-size: 0.7rem; font-weight: 700; color: #34d399; background: rgba(16,185,129,0.12); padding: 3px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; border: 1px solid rgba(16,185,129,0.25);">
                                                <i class="fa-solid fa-circle-check"></i> Enabled
                                            </span>
                                        @else
                                            <span style="font-size: 0.7rem; font-weight: 700; color: #f87171; background: rgba(239,68,68,0.12); padding: 3px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; border: 1px solid rgba(239,68,68,0.25);">
                                                <i class="fa-solid fa-lock"></i> Frozen
                                            </span>
                                        @endif
                                    </button>
                                </form>
                            </td>
                            <td>
                                @if($client->status === 'active')
                                    <span class="badge-status badge-active">
                                        <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> Active
                                    </span>
                                @else
                                    <span class="badge-status badge-inactive">
                                        <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> {{ $client->status }}
                                    </span>
                                @endif
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; align-items: center; gap: 6px;">
                                    <button type="button" class="btn-action-wallet" onclick="openWalletModal({{ $client->id }}, '{{ addslashes($client->name) }}', '{{ number_format($client->wallet_balance ?? 0, 2, '.', '') }}', {{ $walletEnabled ? 'true' : 'false' }})" title="Add/Deduct Wallet Balance or Toggle Status">
                                        <i class="fa-solid fa-wallet"></i>
                                        <span>± Funds</span>
                                    </button>
                                    <a href="{{ route('arun-admin.clients.show', $client->id) }}" class="btn-action-view" title="Manage Client Credentials, WABA, AI & Wallet">
                                        <i class="fa-solid fa-gear"></i>
                                        <span>Manage</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 48px;">
                                <i class="fa-solid fa-users" style="font-size: 2rem; margin-bottom: 12px; opacity: 0.3; display: block;"></i>
                                No tenant workspaces found matching your filter criteria.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($clients->hasPages())
            <div class="pagination-wrap">
                {{ $clients->links() }}
            </div>
        @endif
    </div>

    <!-- Register New Client Modal -->
    <div class="modal-overlay" id="createClientModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fa-solid fa-user-plus" style="color: var(--primary);"></i>
                    <span>Register New Client Workspace</span>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeCreateModal()">&times;</button>
            </div>
            <form action="{{ route('arun-admin.clients.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-input" required placeholder="John Doe">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address (Login) *</label>
                            <input type="email" name="email" class="form-input" required placeholder="john@company.com">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label">Password *</label>
                            <input type="password" name="password" class="form-input" required placeholder="Minimum 6 characters">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Mobile Number</label>
                            <input type="text" name="number" class="form-input" placeholder="+91 9876543210">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Company / Workspace Name</label>
                        <input type="text" name="companyName" class="form-input" placeholder="Acme Enterprises">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px;">
                        <div class="form-group">
                            <label class="form-label">Assign Subscription Plan</label>
                            <select name="package_id" class="form-input">
                                <option value="">-- No Plan (Assign Later) --</option>
                                @foreach($packages as $pkg)
                                    <option value="{{ $pkg->id }}">
                                        {{ $pkg->packageName }} (₹{{ number_format($pkg->price, 0) }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Initial Wallet Balance (₹)</label>
                            <input type="number" step="0.01" name="initial_balance" class="form-input" placeholder="0.00" value="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" style="background: rgba(255,255,255,0.06); border: 1px solid var(--border); color: #cbd5e1; padding: 10px 18px; border-radius: var(--radius-md); cursor: pointer;" onclick="closeCreateModal()">
                        Cancel
                    </button>
                    <button type="submit" class="btn-primary-create">
                        <i class="fa-solid fa-check"></i>
                        <span>Create Client Workspace</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Enhanced Quick Wallet Modal with Live Balance Calculator -->
    <div class="modal-overlay" id="quickWalletModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fa-solid fa-wallet" style="color: #f59e0b;"></i>
                    <span>Manage Wallet: <span id="walletModalClientName" style="color: #60a5fa;"></span></span>
                </div>
                <button type="button" class="modal-close-btn" onclick="closeWalletModal()">&times;</button>
            </div>
            
            <!-- Quick Status Bar -->
            <div style="padding: 18px 24px 0 24px;">
                <div style="background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px 18px; display: flex; align-items: center; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">Current Active Float</div>
                        <div style="font-family: var(--font-mono); font-size: 1.3rem; font-weight: 800; color: #fbbf24;" id="walletModalBalanceDisplay">₹0.00</div>
                    </div>
                    <form id="walletModalToggleForm" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" id="walletModalToggleBtn" style="font-size: 0.8rem; font-weight: 700; padding: 7px 14px; border-radius: 6px; cursor: pointer; border: 1px solid transparent; transition: all 0.15s ease;">
                        </button>
                    </form>
                </div>
            </div>

            <!-- Add / Deduct Form -->
            <form id="quickWalletForm" method="POST">
                @csrf
                <div class="modal-body" style="padding-top: 16px;">
                    <!-- Action Switcher -->
                    <div class="form-group">
                        <label class="form-label">Transaction Type *</label>
                        <div class="action-segmented-wrap">
                            <button type="button" class="btn-segmented active-credit" id="btnActionCredit" onclick="setWalletAction('credit')">
                                <i class="fa-solid fa-plus-circle"></i> + Add Funds (Credit)
                            </button>
                            <button type="button" class="btn-segmented" id="btnActionDebit" onclick="setWalletAction('debit')">
                                <i class="fa-solid fa-minus-circle"></i> - Deduct Funds (Debit)
                            </button>
                        </div>
                        <input type="hidden" name="action" id="modalWalletAction" value="credit">
                    </div>

                    <!-- Amount Input & Quick Chips -->
                    <div class="form-group">
                        <label class="form-label">Amount (₹) *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" id="modalWalletAmount" class="form-input" required placeholder="0.00" style="font-family: var(--font-mono); font-weight: 800; font-size: 1.15rem;" oninput="updateLiveBalanceProjection()">
                        
                        <div class="preset-chips-wrap">
                            <button type="button" class="btn-preset-chip" onclick="setPresetAmount(100)">+₹100</button>
                            <button type="button" class="btn-preset-chip" onclick="setPresetAmount(500)">+₹500</button>
                            <button type="button" class="btn-preset-chip" onclick="setPresetAmount(1000)">+₹1,000</button>
                            <button type="button" class="btn-preset-chip" onclick="setPresetAmount(5000)">+₹5,000</button>
                            <button type="button" class="btn-preset-chip" onclick="setPresetAmount(10000)">+₹10,000</button>
                        </div>
                    </div>

                    <!-- Live Balance Projection Calculator -->
                    <div class="balance-projection-card" id="balanceProjectionBox">
                        <div>
                            <div style="font-size: 0.72rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Projected Balance</div>
                            <div style="font-family: var(--font-mono); font-size: 1.15rem; font-weight: 800; color: #34d399;" id="projectedBalanceDisplay">₹0.00</div>
                        </div>
                        <div id="projectionBadge" style="font-size: 0.75rem; font-weight: 700; padding: 4px 10px; border-radius: 6px; background: rgba(16,185,129,0.15); color: #34d399;">
                            + Credit Adjustment
                        </div>
                    </div>

                    <!-- Audit Description with Quick Remarks -->
                    <div class="form-group">
                        <label class="form-label">Audit Reason / Ledger Remark *</label>
                        <input type="text" name="description" id="modalWalletDesc" class="form-input" required placeholder="e.g. Monthly topup, Promotional credit, Manual billing correction">
                        <div class="preset-chips-wrap">
                            <button type="button" class="btn-preset-chip" onclick="setPresetRemark('Monthly wallet recharge')">Recharge</button>
                            <button type="button" class="btn-preset-chip" onclick="setPresetRemark('Promotional campaign credit')">Bonus</button>
                            <button type="button" class="btn-preset-chip" onclick="setPresetRemark('Administrative balance correction')">Correction</button>
                            <button type="button" class="btn-preset-chip" onclick="setPresetRemark('Billing adjustment refund')">Refund</button>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" style="background: rgba(255,255,255,0.06); border: 1px solid var(--border); color: #cbd5e1; padding: 10px 18px; border-radius: var(--radius-md); cursor: pointer;" onclick="closeWalletModal()">
                        Cancel
                    </button>
                    <button type="submit" id="btnSubmitWalletAdjustment" class="btn-primary-create" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Apply Balance Adjustment</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

@endsection

@section('scripts')
<script>
    let currentModalRawBalance = 0;

    function openCreateModal() {
        document.getElementById('createClientModal').classList.add('open');
    }

    function closeCreateModal() {
        document.getElementById('createClientModal').classList.remove('open');
    }

    function openWalletModal(id, name, balance, isEnabled) {
        currentModalRawBalance = parseFloat(balance) || 0;
        document.getElementById('walletModalClientName').innerText = name;
        document.getElementById('walletModalBalanceDisplay').innerText = '₹' + currentModalRawBalance.toFixed(2);
        
        const form = document.getElementById('quickWalletForm');
        form.action = '/arun-admin/clients/' + id + '/adjust-wallet';
        
        const toggleForm = document.getElementById('walletModalToggleForm');
        toggleForm.action = '/arun-admin/clients/' + id + '/toggle-wallet';
        
        const toggleBtn = document.getElementById('walletModalToggleBtn');
        if (isEnabled) {
            toggleBtn.style.background = 'rgba(239,68,68,0.15)';
            toggleBtn.style.color = '#f87171';
            toggleBtn.style.borderColor = 'rgba(239,68,68,0.3)';
            toggleBtn.innerHTML = '<i class="fa-solid fa-lock"></i> Freeze Wallet';
        } else {
            toggleBtn.style.background = 'rgba(16,185,129,0.15)';
            toggleBtn.style.color = '#34d399';
            toggleBtn.style.borderColor = 'rgba(16,185,129,0.3)';
            toggleBtn.innerHTML = '<i class="fa-solid fa-unlock"></i> Unfreeze Wallet';
        }

        setWalletAction('credit');
        document.getElementById('modalWalletAmount').value = '';
        document.getElementById('modalWalletDesc').value = '';
        updateLiveBalanceProjection();
        document.getElementById('quickWalletModal').classList.add('open');
    }

    function closeWalletModal() {
        document.getElementById('quickWalletModal').classList.remove('open');
    }

    function setWalletAction(action) {
        document.getElementById('modalWalletAction').value = action;
        const btnCredit = document.getElementById('btnActionCredit');
        const btnDebit = document.getElementById('btnActionDebit');
        const submitBtn = document.getElementById('btnSubmitWalletAdjustment');

        if (action === 'credit') {
            btnCredit.className = 'btn-segmented active-credit';
            btnDebit.className = 'btn-segmented';
            submitBtn.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            submitBtn.innerHTML = '<i class="fa-solid fa-plus-circle"></i> <span>Add Funds (Credit)</span>';
        } else {
            btnCredit.className = 'btn-segmented';
            btnDebit.className = 'btn-segmented active-debit';
            submitBtn.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
            submitBtn.innerHTML = '<i class="fa-solid fa-minus-circle"></i> <span>Deduct Funds (Debit)</span>';
        }
        updateLiveBalanceProjection();
    }

    function setPresetAmount(amount) {
        document.getElementById('modalWalletAmount').value = amount;
        updateLiveBalanceProjection();
    }

    function setPresetRemark(remark) {
        document.getElementById('modalWalletDesc').value = remark;
    }

    function updateLiveBalanceProjection() {
        const action = document.getElementById('modalWalletAction').value;
        const amount = parseFloat(document.getElementById('modalWalletAmount').value) || 0;
        const display = document.getElementById('projectedBalanceDisplay');
        const badge = document.getElementById('projectionBadge');

        let projected = action === 'credit' 
            ? (currentModalRawBalance + amount) 
            : (currentModalRawBalance - amount);

        display.innerText = '₹' + projected.toFixed(2);

        if (action === 'credit') {
            display.style.color = '#34d399';
            badge.style.background = 'rgba(16,185,129,0.15)';
            badge.style.color = '#34d399';
            badge.innerText = '+ Credit: +₹' + amount.toFixed(2);
        } else {
            if (projected < 0) {
                display.style.color = '#f87171';
                badge.style.background = 'rgba(239,68,68,0.2)';
                badge.style.color = '#f87171';
                badge.innerText = 'Warning: Exceeds Current Float!';
            } else {
                display.style.color = '#fbbf24';
                badge.style.background = 'rgba(245,158,11,0.15)';
                badge.style.color = '#fbbf24';
                badge.innerText = '- Debit: -₹' + amount.toFixed(2);
            }
        }
    }

    // Client-side instant live filtering
    function liveFilterClients() {
        const query = document.getElementById('clientSearchInput').value.toLowerCase().trim();
        const clearBtn = document.getElementById('btnSearchClear');
        if (clearBtn) clearBtn.style.display = query ? 'block' : 'none';

        const rows = document.querySelectorAll('.client-row');
        rows.forEach(row => {
            const name = row.getAttribute('data-name') || '';
            const email = row.getAttribute('data-email') || '';
            const company = row.getAttribute('data-company') || '';
            const phone = row.getAttribute('data-phone') || '';
            const match = !query || name.includes(query) || email.includes(query) || company.includes(query) || phone.includes(query);
            row.style.display = match ? '' : 'none';
        });
    }

    function clearClientSearch() {
        const input = document.getElementById('clientSearchInput');
        input.value = '';
        liveFilterClients();
    }

    function applyClientFilter(type, btn) {
        document.querySelectorAll('.filter-chip').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');

        const rows = document.querySelectorAll('.client-row');
        rows.forEach(row => {
            let show = false;
            if (type === 'all') show = true;
            else if (type === 'active') show = row.getAttribute('data-status') === 'active';
            else if (type === 'wallet-enabled') show = row.getAttribute('data-wallet-status') === 'enabled';
            else if (type === 'wallet-frozen') show = row.getAttribute('data-wallet-status') === 'frozen';
            else if (type === 'waba-ready') show = row.getAttribute('data-waba') === 'configured';
            else if (type === 'ai-active') show = row.getAttribute('data-ai') === 'enabled';
            row.style.display = show ? '' : 'none';
        });
    }

    // 1-Click Copy helper
    function copyText(str) {
        if (!str) return;
        navigator.clipboard.writeText(str).then(() => {
            const toast = document.createElement('div');
            toast.innerText = 'Copied: ' + str;
            toast.style.position = 'fixed';
            toast.style.bottom = '24px';
            toast.style.right = '24px';
            toast.style.background = '#10b981';
            toast.style.color = '#ffffff';
            toast.style.padding = '8px 16px';
            toast.style.borderRadius = '8px';
            toast.style.fontWeight = '700';
            toast.style.fontSize = '0.82rem';
            toast.style.zIndex = '9999';
            toast.style.boxShadow = '0 4px 14px rgba(16,185,129,0.4)';
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 1800);
        });
    }

    window.addEventListener('click', function(e) {
        const createModal = document.getElementById('createClientModal');
        const walletModal = document.getElementById('quickWalletModal');
        if (e.target === createModal) closeCreateModal();
        if (e.target === walletModal) closeWalletModal();
    });
</script>
@endsection
