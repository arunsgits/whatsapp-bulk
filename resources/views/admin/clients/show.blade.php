@extends('admin.layouts.app')

@section('title', 'Manage Client: ' . ($client->name ?: 'Tenant'))

@section('styles')
<style>
    .client-header {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-xl);
        padding: 24px 30px;
        margin-bottom: 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 20px;
    }

    .client-profile-meta {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .client-big-avatar {
        width: 64px;
        height: 64px;
        border-radius: 16px;
        background: var(--primary-gradient);
        display: flex;
        align-items: center;
        justify-content: center;
        font-family: var(--font-display);
        font-size: 1.8rem;
        font-weight: 800;
        color: #ffffff;
        box-shadow: 0 8px 24px var(--primary-glow);
        flex-shrink: 0;
    }

    .client-name-title {
        font-family: var(--font-display);
        font-size: 1.45rem;
        font-weight: 800;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }

    .client-sub-info {
        font-size: 0.86rem;
        color: var(--text-secondary);
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .client-sub-info span {
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    /* Mini Stat Badges in Header */
    .header-quick-stats {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }

    .quick-stat-box {
        background: rgba(0, 0, 0, 0.25);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 10px 16px;
        text-align: right;
    }

    .quick-stat-label {
        font-size: 0.7rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }

    .quick-stat-val {
        font-family: var(--font-mono);
        font-size: 1.1rem;
        font-weight: 800;
        color: #10b981;
    }

    /* Tab Navigation */
    .tab-nav-bar {
        display: flex;
        gap: 8px;
        border-bottom: 1px solid var(--border);
        margin-bottom: 28px;
        overflow-x: auto;
        padding-bottom: 2px;
    }

    .tab-btn {
        background: transparent;
        border: none;
        padding: 12px 18px;
        font-family: var(--font-body);
        font-size: 0.88rem;
        font-weight: 600;
        color: var(--text-secondary);
        cursor: pointer;
        border-bottom: 2px solid transparent;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.15s ease;
        white-space: nowrap;
    }

    .tab-btn:hover {
        color: var(--text-primary);
    }

    .tab-btn.active {
        color: #ffffff;
        border-bottom-color: var(--primary);
        font-weight: 700;
    }

    .tab-btn i {
        font-size: 0.95rem;
    }

    /* Tab Content Panes */
    .tab-pane {
        display: none;
    }

    .tab-pane.active {
        display: block;
        animation: fadeIn 0.2s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .pane-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-xl);
        overflow: hidden;
        margin-bottom: 28px;
    }

    .pane-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: rgba(255, 255, 255, 0.015);
    }

    .pane-title {
        font-family: var(--font-display);
        font-size: 1.05rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .pane-body {
        padding: 24px;
    }

    .form-grid-2 {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .form-label {
        font-size: 0.76rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }

    .form-input {
        width: 100%;
        height: 42px;
        background: rgba(0, 0, 0, 0.25);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 0 14px;
        font-size: 0.88rem;
        color: var(--text-primary);
        outline: none;
        transition: all 0.18s ease;
    }

    .form-input:focus {
        border-color: var(--primary);
        background: rgba(0, 0, 0, 0.4);
    }

    .form-textarea {
        width: 100%;
        background: rgba(0, 0, 0, 0.25);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 10px 14px;
        font-size: 0.88rem;
        color: var(--text-primary);
        outline: none;
        resize: vertical;
        min-height: 85px;
    }

    .form-textarea:focus {
        border-color: var(--primary);
    }

    .switch-wrap {
        display: flex;
        align-items: center;
        gap: 12px;
        cursor: pointer;
        padding: 8px 0;
    }

    .switch-wrap input[type="checkbox"] {
        width: 18px;
        height: 18px;
        accent-color: var(--primary);
        cursor: pointer;
    }

    .switch-text {
        font-size: 0.88rem;
        font-weight: 600;
        color: #cbd5e1;
    }

    .btn-save-pane {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        border: none;
        padding: 12px 24px;
        border-radius: var(--radius-md);
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        box-shadow: 0 4px 14px rgba(16, 185, 129, 0.3);
        transition: all 0.2s ease;
    }

    .btn-save-pane:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
    }

    .btn-preset-chip {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--border);
        color: var(--text-secondary);
        font-family: var(--font-mono);
        font-size: 0.76rem;
        font-weight: 700;
        padding: 5px 12px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.15s ease;
    }

    .btn-preset-chip:hover {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        border-color: rgba(16, 185, 129, 0.3);
    }

    .info-callout {
        padding: 14px 18px;
        border-radius: var(--radius-md);
        background: rgba(16, 185, 129, 0.08);
        border: 1px solid rgba(16, 185, 129, 0.25);
        color: #a7f3d0;
        font-size: 0.85rem;
        margin-bottom: 20px;
        display: flex;
        align-items: flex-start;
        gap: 12px;
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

    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .data-table th {
        text-align: left;
        padding: 12px 18px;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        background: rgba(255, 255, 255, 0.015);
        border-bottom: 1px solid var(--border);
    }

    .data-table td {
        padding: 14px 18px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }

    .data-table tr:hover td {
        background: rgba(255, 255, 255, 0.018);
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    @media (max-width: 992px) {
        .form-grid-2 {
            grid-template-columns: 1fr;
        }
    }

    @media (max-width: 768px) {
        .client-header {
            padding: 20px;
            flex-direction: column;
            align-items: flex-start;
            gap: 16px;
        }
        .header-quick-stats {
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .quick-stat-box {
            text-align: left;
        }
        .tab-btn {
            padding: 10px 14px;
            font-size: 0.82rem;
        }
        .wallet-adjust-grid {
            grid-template-columns: 1fr !important;
        }
    }
</style>
@endsection

@section('content')

    <!-- Top Back Link -->
    <div style="margin-bottom: 16px;">
        <a href="{{ route('arun-admin.clients.index') }}" style="color: var(--text-secondary); text-decoration: none; font-size: 0.84rem; display: inline-flex; align-items: center; gap: 6px;">
            <i class="fa-solid fa-arrow-left"></i>
            <span>Back to All Clients</span>
        </a>
    </div>

    <!-- Client Header Profile Card -->
    <div class="client-header">
        <div class="client-profile-meta">
            <div class="client-big-avatar">
                {{ strtoupper(substr($client->name ?: 'U', 0, 1)) }}
            </div>
            <div>
                <div class="client-name-title">
                    <span>{{ $client->name }}</span>
                    @if($client->status === 'active')
                        <span class="badge-status badge-active">
                            <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> Active
                        </span>
                    @else
                        <span class="badge-status badge-inactive">
                            <i class="fa-solid fa-circle" style="font-size: 0.45rem;"></i> {{ $client->status }}
                        </span>
                    @endif
                </div>
                <div class="client-sub-info">
                    <span><i class="fa-solid fa-building" style="color: #60a5fa;"></i> {{ $client->companyName ?: 'No Company' }}</span>
                    <span><i class="fa-solid fa-envelope" style="color: #f59e0b;"></i> {{ $client->email }}</span>
                    <span><i class="fa-solid fa-phone" style="color: #10b981;"></i> {{ $client->number ?: 'No Mobile' }}</span>
                    <span><i class="fa-solid fa-calendar" style="color: #c4b5fd;"></i> Joined {{ $client->created_at ? $client->created_at->format('M d, Y') : 'N/A' }}</span>
                </div>
            </div>
        </div>

        <div class="header-quick-stats">
            <div class="quick-stat-box">
                <div class="quick-stat-label">Wallet Balance</div>
                <div class="quick-stat-val">₹{{ number_format($client->wallet_balance ?? 0, 2) }}</div>
            </div>
            <div class="quick-stat-box">
                <div class="quick-stat-label">Active Plan</div>
                <div class="quick-stat-val" style="color: #60a5fa; font-size: 0.95rem;">
                    {{ $activePackage?->package?->packageName ?? 'No Plan' }}
                </div>
            </div>
            <div class="quick-stat-box">
                <div class="quick-stat-label">Total Recharges</div>
                <div class="quick-stat-val" style="color: #f59e0b; font-size: 0.95rem;">
                    ₹{{ number_format($totalRecharged, 2) }}
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="tab-nav-bar">
        <button type="button" class="tab-btn active" onclick="switchTab('tab-profile')">
            <i class="fa-solid fa-user"></i>
            <span>Overview & Profile</span>
        </button>
        <button type="button" class="tab-btn" onclick="switchTab('tab-waba')">
            <i class="fa-brands fa-whatsapp" style="color: #25d366;"></i>
            <span>Meta WhatsApp Cloud API</span>
        </button>
        <button type="button" class="tab-btn" onclick="switchTab('tab-ai')">
            <i class="fa-solid fa-robot" style="color: #8b5cf6;"></i>
            <span>Auto-AI Engine Settings</span>
        </button>
        <button type="button" class="tab-btn" onclick="switchTab('tab-wallet')">
            <i class="fa-solid fa-wallet" style="color: #f59e0b;"></i>
            <span>Wallet & Balance Adjustments</span>
        </button>
        <button type="button" class="tab-btn" onclick="switchTab('tab-plan')">
            <i class="fa-solid fa-layer-group" style="color: #3b82f6;"></i>
            <span>Subscription Plan</span>
        </button>
    </div>

    <!-- TAB 1: Profile & Company Details -->
    <div class="tab-pane active" id="tab-profile">
        <div class="pane-card">
            <div class="pane-header">
                <div class="pane-title">
                    <i class="fa-solid fa-id-card" style="color: #3b82f6;"></i>
                    <span>Client Profile & Business Information</span>
                </div>
            </div>
            <form action="{{ route('arun-admin.clients.update', $client->id) }}" method="POST">
                @csrf
                <div class="pane-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-input" value="{{ old('name', $client->name) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address (Login Username) *</label>
                            <input type="email" name="email" class="form-input" value="{{ old('email', $client->email) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mobile Contact Number</label>
                            <input type="text" name="number" class="form-input" value="{{ old('number', $client->number) }}" placeholder="+91 9876543210">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company / Workspace Name</label>
                            <input type="text" name="companyName" class="form-input" value="{{ old('companyName', $client->companyName) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company Official Email</label>
                            <input type="email" name="companyEmail" class="form-input" value="{{ old('companyEmail', $client->companyEmail) }}" placeholder="billing@company.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Company Contact Phone</label>
                            <input type="text" name="companyMobile" class="form-input" value="{{ old('companyMobile', $client->companyMobile) }}" placeholder="+91 80 12345678">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 16px;">
                        <label class="form-label">Company Address / Billing Location</label>
                        <textarea name="companyAddress" class="form-textarea" placeholder="Office address, City, State, PIN">{{ old('companyAddress', $client->companyAddress) }}</textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 20px; margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border);">
                        <div class="form-group">
                            <label class="form-label">Account Status</label>
                            <select name="status" class="form-input">
                                <option value="active" {{ $client->status === 'active' ? 'selected' : '' }}>Active (Allow Login & Messaging)</option>
                                <option value="inactive" {{ $client->status === 'inactive' ? 'selected' : '' }}>Inactive / Suspended</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Reset Password (Leave blank to keep unchanged)</label>
                            <input type="password" name="password" class="form-input" placeholder="Enter new password to reset">
                        </div>
                    </div>

                    <label class="switch-wrap" style="margin-top: 16px;">
                        <input type="checkbox" name="wallet_enabled" value="1" {{ ($client->wallet_enabled ?? true) ? 'checked' : '' }}>
                        <span class="switch-text">Enable Wallet Consumption & Auto-Billing for this Client</span>
                    </label>
                </div>
                <div style="padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; background: rgba(0,0,0,0.15);">
                    <button type="submit" class="btn-save-pane">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save Profile Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 2: Meta WhatsApp Cloud API Setup (FOR THIS TENANT) -->
    <div class="tab-pane" id="tab-waba">
        <div class="pane-card">
            <div class="pane-header">
                <div class="pane-title">
                    <i class="fa-brands fa-whatsapp" style="color: #25d366;"></i>
                    <span>Meta WhatsApp Cloud API Configuration (Tenant Credentials)</span>
                </div>
                @if(!empty($settings->phoneNumberId))
                    <span style="font-size: 0.76rem; font-weight: 700; color: #25d366; background: rgba(37,211,102,0.15); padding: 4px 10px; border-radius: 6px;">
                        <i class="fa-solid fa-circle-check"></i> Gateway Active
                    </span>
                @else
                    <span style="font-size: 0.76rem; font-weight: 700; color: #f59e0b; background: rgba(245,158,11,0.15); padding: 4px 10px; border-radius: 6px;">
                        <i class="fa-solid fa-triangle-exclamation"></i> Not Configured
                    </span>
                @endif
            </div>
            <form action="{{ route('arun-admin.clients.update-waba', $client->id) }}" method="POST">
                @csrf
                <div class="pane-body">
                    <div class="info-callout">
                        <i class="fa-solid fa-shield-halved" style="font-size: 1.15rem; margin-top: 2px;"></i>
                        <div>
                            <strong>Tenant-Isolated Credentials:</strong>
                            Messages sent by {{ $client->name }} will be routed strictly through this dedicated Phone Number ID and WABA account.
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 18px;">
                        <label class="form-label">Incoming Webhook Callback URL</label>
                        <input type="text" class="form-input" value="{{ url('/whatsapp/webhook') }}" readonly style="opacity: 0.7; cursor: not-allowed; font-family: var(--font-mono);">
                    </div>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="phoneNumberId" class="form-label">Phone Number ID *</label>
                            <input type="text" id="phoneNumberId" name="phoneNumberId" class="form-input" value="{{ old('phoneNumberId', $settings->phoneNumberId) }}" placeholder="e.g. 104839281928371" style="font-family: var(--font-mono);">
                        </div>

                        <div class="form-group">
                            <label for="waba_id" class="form-label">WhatsApp Business Account (WABA) ID *</label>
                            <input type="text" id="waba_id" name="waba_id" class="form-input" value="{{ old('waba_id', $settings->waba_id) }}" placeholder="e.g. 293847291827391" style="font-family: var(--font-mono);">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 18px;">
                        <label for="access_token" class="form-label">System User Permanent Access Token *</label>
                        <input type="password" id="access_token" name="access_token" class="form-input" placeholder="{{ $settings->access_token ? '•••••••••••••••••••••••••••••••• (Configured & Saved)' : 'EAABw...' }}" style="font-family: var(--font-mono);">
                    </div>

                    <div class="form-grid-2" style="margin-top: 18px;">
                        <div class="form-group">
                            <label for="webhook_secret" class="form-label">Webhook Verify Token Secret</label>
                            <input type="text" id="webhook_secret" name="webhook_secret" class="form-input" value="{{ old('webhook_secret', $settings->webhook_secret) }}" placeholder="zapreach_verify_token" style="font-family: var(--font-mono);">
                        </div>
                        <div class="form-group">
                            <label for="fb_app_id" class="form-label">Meta App ID (Optional)</label>
                            <input type="text" id="fb_app_id" name="fb_app_id" class="form-input" value="{{ old('fb_app_id', $settings->fb_app_id) }}" placeholder="e.g. 192837465019283" style="font-family: var(--font-mono);">
                        </div>
                    </div>

                    <label class="switch-wrap" style="margin-top: 20px;">
                        <input type="checkbox" name="isActive" value="1" {{ $settings->isActive ? 'checked' : '' }}>
                        <span class="switch-text">Enable Meta Cloud API as Active Messaging Gateway for this Client</span>
                    </label>
                </div>
                <div style="padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; background: rgba(0,0,0,0.15);">
                    <button type="submit" class="btn-save-pane">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save Meta WABA Setup</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 3: Auto-AI Engine Settings (FOR THIS TENANT) -->
    <div class="tab-pane" id="tab-ai">
        <div class="pane-card">
            <div class="pane-header">
                <div class="pane-title">
                    <i class="fa-solid fa-robot" style="color: #8b5cf6;"></i>
                    <span>Autonomous AI Assistant Configuration (Tenant Bot)</span>
                </div>
                @if($settings->auto_ai_enabled)
                    <span style="font-size: 0.76rem; font-weight: 700; color: #c4b5fd; background: rgba(139,92,246,0.18); padding: 4px 10px; border-radius: 6px;">
                        <i class="fa-solid fa-bolt"></i> Auto-AI Active
                    </span>
                @else
                    <span style="font-size: 0.76rem; font-weight: 700; color: var(--text-muted); background: rgba(255,255,255,0.05); padding: 4px 10px; border-radius: 6px;">
                        Disabled
                    </span>
                @endif
            </div>
            <form action="{{ route('arun-admin.clients.update-ai', $client->id) }}" method="POST">
                @csrf
                <div class="pane-body">
                    <label class="switch-wrap" style="margin-bottom: 20px;">
                        <input type="checkbox" name="auto_ai_enabled" value="1" {{ $settings->auto_ai_enabled ? 'checked' : '' }}>
                        <span class="switch-text" style="font-size: 0.95rem; font-weight: 700; color: #c4b5fd;">
                            Enable Autonomous AI Customer Assistant for Inbound Chats
                        </span>
                    </label>

                    <div class="form-grid-2">
                        <div class="form-group">
                            <label for="auto_ai_tone" class="form-label">AI Conversation Tone & Persona</label>
                            <select id="auto_ai_tone" name="auto_ai_tone" class="form-input">
                                <option value="friendly" {{ ($settings->auto_ai_tone ?? 'friendly') === 'friendly' ? 'selected' : '' }}>Friendly & Welcoming</option>
                                <option value="professional" {{ ($settings->auto_ai_tone ?? '') === 'professional' ? 'selected' : '' }}>Professional & Direct</option>
                                <option value="formal" {{ ($settings->auto_ai_tone ?? '') === 'formal' ? 'selected' : '' }}>Formal & Corporate</option>
                                <option value="persuasive" {{ ($settings->auto_ai_tone ?? '') === 'persuasive' ? 'selected' : '' }}>Sales & Persuasive</option>
                                <option value="casual" {{ ($settings->auto_ai_tone ?? '') === 'casual' ? 'selected' : '' }}>Casual & Modern</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="business_name" class="form-label">Client Business Name for AI Persona</label>
                            <input type="text" id="business_name" name="business_name" class="form-input" value="{{ old('business_name', $settings->business_name ?: $client->companyName) }}" placeholder="e.g. Acme Retailers">
                        </div>

                        <div class="form-group">
                            <label for="business_type" class="form-label">Industry / Business Category</label>
                            <input type="text" id="business_type" name="business_type" class="form-input" value="{{ old('business_type', $settings->business_type) }}" placeholder="e.g. Real Estate, Healthcare, E-Commerce">
                        </div>

                        <div class="form-group">
                            <label for="business_hours" class="form-label">Business Operating Hours</label>
                            <input type="text" id="business_hours" name="business_hours" class="form-input" value="{{ old('business_hours', $settings->business_hours) }}" placeholder="e.g. Mon-Sat 9:00 AM - 7:00 PM">
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 18px;">
                        <label for="business_details" class="form-label">Business Details & Knowledge Context (System AI Prompt)</label>
                        <textarea id="business_details" name="business_details" class="form-textarea" style="min-height: 100px;" placeholder="Tell the AI what products or services this client sells, pricing rules, refund policies, FAQs, and contact links...">{{ old('business_details', $settings->business_details) }}</textarea>
                    </div>

                    <div class="form-group" style="margin-top: 18px;">
                        <label for="first_time_message" class="form-label">First-Time Greeting Message</label>
                        <textarea id="first_time_message" name="first_time_message" class="form-textarea" placeholder="Greeting message automatically dispatched to first-time customer contacts">{{ old('first_time_message', $settings->first_time_message) }}</textarea>
                    </div>
                </div>
                <div style="padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; background: rgba(0,0,0,0.15);">
                    <button type="submit" class="btn-save-pane">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save Auto-AI Engine Settings</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TAB 4: Wallet & Balance Adjustments -->
    <div class="tab-pane" id="tab-wallet">
        <!-- 1. Dedicated Wallet Status & Enable/Disable Switch Card -->
        <div class="pane-card" style="margin-bottom: 24px;">
            <div class="pane-body" style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
                <div style="display: flex; align-items: center; gap: 16px;">
                    <div style="width: 52px; height: 52px; border-radius: 14px; background: {{ $client->wallet_enabled ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.15)' }}; color: {{ $client->wallet_enabled ? '#10b981' : '#f87171' }}; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                        <i class="fa-solid fa-wallet"></i>
                    </div>
                    <div>
                        <div style="font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted);">
                            Client Wallet Billing Engine
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px; margin-top: 4px;">
                            @if($client->wallet_enabled)
                                <span style="font-size: 0.88rem; font-weight: 800; color: #34d399; display: inline-flex; align-items: center; gap: 6px;">
                                    <i class="fa-solid fa-circle-check"></i> ACTIVE &amp; ENABLED
                                </span>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">&bull; User can consume wallet balance for campaigns &amp; messages</span>
                            @else
                                <span style="font-size: 0.88rem; font-weight: 800; color: #f87171; display: inline-flex; align-items: center; gap: 6px;">
                                    <i class="fa-solid fa-circle-xmark"></i> INACTIVE &amp; DISABLED
                                </span>
                                <span style="font-size: 0.8rem; color: var(--text-muted);">&bull; Wallet billing is locked for this tenant</span>
                            @endif
                        </div>
                    </div>
                </div>

                <div>
                    <form action="{{ route('arun-admin.clients.toggle-wallet', $client->id) }}" method="POST" style="margin: 0;">
                        @csrf
                        <button type="submit" style="background: {{ $client->wallet_enabled ? 'rgba(239,68,68,0.12)' : 'rgba(16,185,129,0.12)' }}; border: 1px solid {{ $client->wallet_enabled ? 'rgba(239,68,68,0.3)' : 'rgba(16,185,129,0.3)' }}; color: {{ $client->wallet_enabled ? '#f87171' : '#34d399' }}; font-size: 0.86rem; font-weight: 700; padding: 11px 20px; border-radius: var(--radius-md); cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: all 0.15s ease;">
                            <i class="fa-solid {{ $client->wallet_enabled ? 'fa-toggle-off' : 'fa-toggle-on' }}"></i>
                            <span>{{ $client->wallet_enabled ? 'Disable Wallet Billing' : 'Enable Wallet Billing' }}</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- 2. Manual Balance Adjustment (Credit / Debit) Card -->
        <div class="pane-card">
            <div class="pane-header">
                <div class="pane-title">
                    <i class="fa-solid fa-money-bill-transfer" style="color: #f59e0b;"></i>
                    <span>Add or Deduct Wallet Float (Stored in DB Ledger)</span>
                </div>
                <div style="font-family: var(--font-mono); font-size: 1.15rem; font-weight: 800; color: #10b981;">
                    Current Float: ₹{{ number_format($client->wallet_balance ?? 0, 2) }}
                </div>
            </div>
            <form action="{{ route('arun-admin.clients.adjust-wallet', $client->id) }}" method="POST">
                @csrf
                <div class="pane-body">
                    <div class="wallet-adjust-grid" style="display: grid; grid-template-columns: 220px 240px 1fr; gap: 16px; align-items: flex-start;">
                        <div class="form-group">
                            <label class="form-label">Action *</label>
                            <select name="action" class="form-input" required id="adjustmentActionSelect" onchange="updateActionColor(this.value)">
                                <option value="credit">+ Add Funds (Credit)</option>
                                <option value="debit">- Deduct Funds (Debit)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Amount (₹) *</label>
                            <input type="number" step="0.01" min="0.01" name="amount" id="clientWalletAmtInput" class="form-input" required placeholder="0.00" style="font-family: var(--font-mono); font-weight: 800; font-size: 1rem;">
                            <div style="display: flex; gap: 6px; margin-top: 6px; flex-wrap: wrap;">
                                <button type="button" class="btn-preset-chip" onclick="document.getElementById('clientWalletAmtInput').value = 100">+₹100</button>
                                <button type="button" class="btn-preset-chip" onclick="document.getElementById('clientWalletAmtInput').value = 500">+₹500</button>
                                <button type="button" class="btn-preset-chip" onclick="document.getElementById('clientWalletAmtInput').value = 1000">+₹1,000</button>
                                <button type="button" class="btn-preset-chip" onclick="document.getElementById('clientWalletAmtInput').value = 5000">+₹5,000</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Audit Description / Reason (Stored in DB) *</label>
                            <input type="text" name="description" class="form-input" required placeholder="e.g. Promotional campaign credit, Manual recharge, Billing refund adjustment">
                        </div>
                    </div>
                </div>
                <div style="padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; background: rgba(0,0,0,0.15);">
                    <button type="submit" class="btn-save-pane" id="btnExecuteAdjustment">
                        <i class="fa-solid fa-check"></i>
                        <span>Execute Wallet Update</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Wallet Transactions Table -->
        <div class="pane-card">
            <div class="pane-header">
                <div class="pane-title">
                    <i class="fa-solid fa-clock-rotate-left" style="color: #60a5fa;"></i>
                    <span>Client's Recent Wallet Ledger Entries</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Ref ID</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Balance After</th>
                            <th>Description</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($walletTransactions as $txn)
                            <tr>
                                <td style="font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-muted);">
                                    {{ $txn->reference_id ?: '#' . $txn->id }}
                                </td>
                                <td>
                                    @if($txn->type === 'credit')
                                        <span style="font-size: 0.72rem; font-weight: 700; color: #10b981; background: rgba(16,185,129,0.15); padding: 3px 8px; border-radius: 4px;">
                                            <i class="fa-solid fa-arrow-up"></i> CREDIT
                                        </span>
                                    @else
                                        <span style="font-size: 0.72rem; font-weight: 700; color: #ef4444; background: rgba(239,68,68,0.15); padding: 3px 8px; border-radius: 4px;">
                                            <i class="fa-solid fa-arrow-down"></i> DEBIT
                                        </span>
                                    @endif
                                </td>
                                <td style="text-transform: uppercase; font-size: 0.74rem; font-weight: 600; color: var(--text-secondary);">
                                    {{ $txn->category ?: $txn->source }}
                                </td>
                                <td style="font-family: var(--font-mono); font-weight: 700; color: {{ $txn->type === 'credit' ? '#10b981' : '#f87171' }};">
                                    {{ $txn->type === 'credit' ? '+' : '-' }}₹{{ number_format($txn->amount, 2) }}
                                </td>
                                <td style="font-family: var(--font-mono); font-weight: 700; color: #cbd5e1;">
                                    ₹{{ number_format($txn->balance_after, 2) }}
                                </td>
                                <td style="font-size: 0.82rem; color: var(--text-secondary);">
                                    {{ $txn->description ?: 'N/A' }}
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 0.76rem; color: var(--text-muted);">
                                    {{ $txn->created_at ? $txn->created_at->format('M d, Y H:i') : 'N/A' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                    No wallet transactions recorded for this client yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 5: Subscription Plan -->
    <div class="tab-pane" id="tab-plan">
        <!-- Assign / Upgrade Plan Card -->
        <div class="pane-card">
            <div class="pane-header">
                <div class="pane-title">
                    <i class="fa-solid fa-cube" style="color: #3b82f6;"></i>
                    <span>Assign or Upgrade Subscription Tier</span>
                </div>
            </div>
            <form action="{{ route('arun-admin.clients.change-plan', $client->id) }}" method="POST">
                @csrf
                <div class="pane-body">
                    <div style="display: grid; grid-template-columns: 1fr 220px; gap: 20px;">
                        <div class="form-group">
                            <label class="form-label">Select Package / Plan *</label>
                            <select name="package_id" class="form-input" required>
                                @foreach($packages as $pkg)
                                    <option value="{{ $pkg->id }}" {{ $activePackage && $activePackage->packageId == $pkg->id ? 'selected' : '' }}>
                                        {{ $pkg->packageName }} &bull; ₹{{ number_format($pkg->price, 0) }} &bull; {{ number_format($pkg->msgCount) }} msgs &bull; {{ $pkg->day ?: 30 }} days
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Custom Validity in Days (Optional)</label>
                            <input type="number" name="validity_days" class="form-input" placeholder="Leave blank for plan default">
                        </div>
                    </div>

                    @if($activePackage)
                        <div style="margin-top: 20px; padding: 18px; background: rgba(0,0,0,0.25); border-radius: var(--radius-md); border: 1px solid var(--border);">
                            <div style="font-weight: 700; color: #cbd5e1; margin-bottom: 8px;">Active Subscription Overview</div>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px; font-size: 0.84rem;">
                                <div>
                                    <div style="color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase;">Tier Name</div>
                                    <div style="font-weight: 700; color: #60a5fa;">{{ $activePackage->package?->packageName ?: 'Custom Plan' }}</div>
                                </div>
                                <div>
                                    <div style="color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase;">Activated On</div>
                                    <div style="font-family: var(--font-mono);">{{ $activePackage->startDate ? $activePackage->startDate->format('M d, Y') : 'N/A' }}</div>
                                </div>
                                <div>
                                    <div style="color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase;">Expires On</div>
                                    <div style="font-family: var(--font-mono); color: {{ $activePackage->endDate && now()->gt($activePackage->endDate) ? '#ef4444' : '#10b981' }};">
                                        {{ $activePackage->endDate ? $activePackage->endDate->format('M d, Y') : 'Never' }}
                                    </div>
                                </div>
                                <div>
                                    <div style="color: var(--text-muted); font-size: 0.72rem; text-transform: uppercase;">Messages Dispatched</div>
                                    <div style="font-family: var(--font-mono);">{{ number_format($activePackage->usedMsgCount ?? 0) }} / {{ number_format($activePackage->resolvedMessageLimit()) }}</div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
                <div style="padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; background: rgba(0,0,0,0.15);">
                    <button type="submit" class="btn-save-pane">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Assign Subscription Plan</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Plan Assignment History -->
        <div class="pane-card">
            <div class="pane-header">
                <div class="pane-title">
                    <i class="fa-solid fa-history" style="color: #c4b5fd;"></i>
                    <span>Plan Assignment & Renewal Audit History</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Plan Name</th>
                            <th>Action</th>
                            <th>Price</th>
                            <th>Quota (Msgs)</th>
                            <th>Validity Period</th>
                            <th>Assigned By</th>
                            <th>Audit Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($packageHistories as $history)
                            <tr>
                                <td style="font-weight: 700; color: #60a5fa;">
                                    {{ $history->packageName }}
                                </td>
                                <td style="text-transform: capitalize; font-size: 0.76rem; font-weight: 600;">
                                    {{ str_replace('_', ' ', $history->action) }}
                                </td>
                                <td style="font-family: var(--font-mono);">
                                    ₹{{ number_format($history->price ?? 0, 2) }}
                                </td>
                                <td style="font-family: var(--font-mono);">
                                    {{ number_format($history->msgCount ?? 0) }}
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 0.76rem; color: var(--text-muted);">
                                    {{ $history->startDate ? $history->startDate->format('M d, Y') : '' }} &rarr; {{ $history->endDate ? $history->endDate->format('M d, Y') : '' }}
                                </td>
                                <td style="font-size: 0.78rem; color: var(--text-secondary);">
                                    {{ $history->assignedByUser?->name ?: 'System' }}
                                </td>
                                <td style="font-size: 0.78rem; color: var(--text-muted);">
                                    {{ $history->notes ?: '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 32px;">
                                    No plan assignment history recorded for this client.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

@endsection

@section('scripts')
<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

        const targetPane = document.getElementById(tabId);
        if (targetPane) {
            targetPane.classList.add('active');
        }

        // Highlight tab button
        event.currentTarget.classList.add('active');
    }
</script>
@endsection
