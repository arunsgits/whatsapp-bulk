@extends('admin.layouts.app')

@section('title', 'Global Platform Settings')

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
        font-size: 1.5rem;
        font-weight: 800;
        letter-spacing: -0.02em;
    }

    .settings-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
        gap: 24px;
        margin-bottom: 32px;
    }

    .settings-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-xl);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .settings-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 12px;
        font-family: var(--font-display);
        font-size: 1.05rem;
        font-weight: 700;
        background: rgba(255, 255, 255, 0.015);
    }

    .settings-header i {
        font-size: 1.15rem;
    }

    .settings-body {
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 18px;
        flex: 1;
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
        font-family: var(--font-mono);
        font-size: 0.88rem;
        color: var(--text-primary);
        outline: none;
        transition: all 0.18s ease;
    }

    .form-input:focus {
        border-color: var(--primary);
        background: rgba(0, 0, 0, 0.4);
    }

    .form-select {
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

    .info-pill {
        padding: 12px 16px;
        border-radius: var(--radius-md);
        background: rgba(59, 130, 246, 0.08);
        border: 1px solid rgba(59, 130, 246, 0.2);
        color: #93c5fd;
        font-size: 0.82rem;
        line-height: 1.5;
        display: flex;
        align-items: flex-start;
        gap: 10px;
    }

    .btn-save-sticky {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        color: #ffffff;
        border: none;
        padding: 14px 28px;
        border-radius: var(--radius-md);
        font-size: 0.95rem;
        font-weight: 700;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 20px rgba(16, 185, 129, 0.35);
        transition: all 0.2s ease;
    }

    .btn-save-sticky:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.5);
    }
</style>
@endsection

@section('content')

    <form action="{{ route('arun-admin.settings.update') }}" method="POST">
        @csrf

        <div class="page-header">
            <div>
                <h1 class="page-title">Global Platform Configuration</h1>
                <p style="color: var(--text-secondary); font-size: 0.88rem;">
                    Configure platform-level services: Payment Gateway, SMTP Mail Server, Broadcast Pacing, and Global Billing Rates.
                </p>
            </div>
            <div>
                <button type="submit" class="btn-save-sticky">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>Save Platform Settings</span>
                </button>
            </div>
        </div>

        <div class="info-pill" style="margin-bottom: 24px;">
            <i class="fa-solid fa-circle-info" style="font-size: 1.1rem; margin-top: 2px;"></i>
            <div>
                <strong>Tenant-Specific Configurations Notice:</strong>
                Meta WhatsApp Cloud API credentials and Auto-AI Assistant settings are configured individually per tenant workspace under the 
                <a href="{{ route('arun-admin.clients.index') }}" style="color: #60a5fa; text-decoration: underline; font-weight: 600;">Tenants & Clients Module</a>.
            </div>
        </div>

        <div class="settings-grid">
            <!-- 1. Platform Identity & Live Status -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fa-solid fa-building" style="color: #3b82f6;"></i>
                    <span>Platform Identity & Live Status</span>
                </div>
                <div class="settings-body">
                    <div class="form-group">
                        <label for="business_name" class="form-label">Platform Name</label>
                        <input type="text" id="business_name" name="business_name" class="form-input" value="{{ old('business_name', $settings->business_name ?: 'ZapReach Technologies') }}">
                    </div>

                    <div class="form-group">
                        <label for="support_email" class="form-label">Support Email Address</label>
                        <input type="email" id="support_email" name="support_email" class="form-input" value="{{ old('support_email', $settings->support_email ?: 'support@zapreach.com') }}">
                    </div>

                    <div class="form-group">
                        <label for="support_phone" class="form-label">Support Contact Number</label>
                        <input type="text" id="support_phone" name="support_phone" class="form-input" value="{{ old('support_phone', $settings->support_phone) }}" placeholder="+91 9876543210">
                    </div>

                    <div class="form-group">
                        <label for="website_url" class="form-label">Official Website URL</label>
                        <input type="url" id="website_url" name="website_url" class="form-input" value="{{ old('website_url', $settings->website_url ?: config('app.url')) }}">
                    </div>

                    <label class="switch-wrap">
                        <input type="checkbox" name="is_app_live" value="1" {{ ($settings->is_app_live ?? true) ? 'checked' : '' }}>
                        <span class="switch-text">Platform Live Status (Uncheck to enable Maintenance Mode)</span>
                    </label>
                </div>
            </div>

            <!-- 2. Razorpay Payment Gateway -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fa-solid fa-credit-card" style="color: #10b981;"></i>
                    <span>Razorpay Payment Gateway</span>
                </div>
                <div class="settings-body">
                    <div class="form-group">
                        <label for="razorpay_key_id" class="form-label">Razorpay Key ID</label>
                        <input type="text" id="razorpay_key_id" name="razorpay_key_id" class="form-input" value="{{ old('razorpay_key_id', $settings->razorpay_key_id) }}" placeholder="rzp_live_...">
                    </div>

                    <div class="form-group">
                        <label for="razorpay_key_secret" class="form-label">Razorpay Key Secret</label>
                        <input type="password" id="razorpay_key_secret" name="razorpay_key_secret" class="form-input" placeholder="{{ $settings->razorpay_key_secret ? '•••••••••••••••••••••••• (Configured)' : 'Enter Secret Key' }}">
                    </div>

                    <div class="form-group">
                        <label for="razorpay_webhook_secret" class="form-label">Razorpay Webhook Secret</label>
                        <input type="text" id="razorpay_webhook_secret" name="razorpay_webhook_secret" class="form-input" value="{{ old('razorpay_webhook_secret', $settings->razorpay_webhook_secret) }}" placeholder="zapreach_rzp_webhook">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Payment Webhook Endpoint</label>
                        <input type="text" class="form-input" value="{{ url('/api/wallet/webhook') }}" readonly style="opacity: 0.65; cursor: not-allowed;">
                    </div>
                </div>
            </div>

            <!-- 3. SMTP Mail Server -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fa-solid fa-envelope" style="color: #ec4899;"></i>
                    <span>SMTP Mail Server Configuration</span>
                </div>
                <div class="settings-body">
                    <div class="form-group">
                        <label for="smtp_host" class="form-label">SMTP Host</label>
                        <input type="text" id="smtp_host" name="smtp_host" class="form-input" value="{{ old('smtp_host', $settings->smtp_host) }}" placeholder="smtp.gmail.com or mail.zapreach.com">
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label for="smtp_port" class="form-label">SMTP Port</label>
                            <input type="number" id="smtp_port" name="smtp_port" class="form-input" value="{{ old('smtp_port', $settings->smtp_port ?: 587) }}" placeholder="587">
                        </div>
                        <div class="form-group">
                            <label for="smtp_user" class="form-label">SMTP User</label>
                            <input type="text" id="smtp_user" name="smtp_user" class="form-input" value="{{ old('smtp_user', $settings->smtp_user) }}" placeholder="info@zapreach.com">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="smtp_password" class="form-label">SMTP Password</label>
                        <input type="password" id="smtp_password" name="smtp_password" class="form-input" placeholder="{{ $settings->smtp_password ? '•••••••••••••••• (Configured)' : 'Enter SMTP Password' }}">
                    </div>

                    <div class="form-group">
                        <label for="smtp_cc_email" class="form-label">Notification CC Email(s)</label>
                        <input type="text" id="smtp_cc_email" name="smtp_cc_email" class="form-input" value="{{ old('smtp_cc_email', $settings->smtp_cc_email) }}" placeholder="alerts@zapreach.com, finance@zapreach.com">
                    </div>

                    <label class="switch-wrap">
                        <input type="checkbox" name="smtp_cc_enabled" value="1" {{ ($settings->smtp_cc_enabled ?? false) ? 'checked' : '' }}>
                        <span class="switch-text">Enable CC Notifications for Recharges & Alert Emails</span>
                    </label>
                </div>
            </div>

            <!-- 4. High-Volume Bulk Broadcast Worker -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fa-solid fa-paper-plane" style="color: #6366f1;"></i>
                    <span>High-Volume Broadcast Worker Engine</span>
                </div>
                <div class="settings-body">
                    <label class="switch-wrap">
                        <input type="checkbox" name="enable_batch_processing" value="1" {{ $settings->enable_batch_processing ? 'checked' : '' }}>
                        <span class="switch-text">Enable High-Volume Batch Worker Queue</span>
                    </label>

                    <div class="form-group">
                        <label for="batch_size" class="form-label">Batch Size (Messages / Dispatch Cycle)</label>
                        <input type="number" id="batch_size" name="batch_size" class="form-input" value="{{ old('batch_size', $settings->batch_size ?: 50) }}" min="5" max="500">
                    </div>

                    <div class="form-group">
                        <label for="batch_delay_seconds" class="form-label">Delay Pacing Interval (Seconds)</label>
                        <input type="number" id="batch_delay_seconds" name="batch_delay_seconds" class="form-input" value="{{ old('batch_delay_seconds', $settings->batch_delay_seconds ?: 1) }}" min="0" max="60">
                    </div>

                    <p style="font-size: 0.8rem; color: var(--text-muted); line-height: 1.4;">
                        Worker batching queues prevent rate-limiting bans from Meta Cloud API when sending campaigns with tens of thousands of recipients.
                    </p>
                </div>
            </div>

            <!-- 5. Default Wallet Conversation Costs -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fa-solid fa-coins" style="color: #f59e0b;"></i>
                    <span>Default Conversation Pricing & Rates</span>
                </div>
                <div class="settings-body">
                    <div class="form-group">
                        <label for="wallet_price_marketing" class="form-label">Marketing Conversation Rate (₹)</label>
                        <input type="number" step="0.01" id="wallet_price_marketing" name="wallet_price_marketing" class="form-input" value="{{ old('wallet_price_marketing', $settings->wallet_price_marketing ?? 0.85) }}">
                    </div>

                    <div class="form-group">
                        <label for="wallet_price_utility" class="form-label">Utility Conversation Rate (₹)</label>
                        <input type="number" step="0.01" id="wallet_price_utility" name="wallet_price_utility" class="form-input" value="{{ old('wallet_price_utility', $settings->wallet_price_utility ?? 0.35) }}">
                    </div>

                    <div class="form-group">
                        <label for="wallet_price_authentication" class="form-label">Authentication OTP Rate (₹)</label>
                        <input type="number" step="0.01" id="wallet_price_authentication" name="wallet_price_authentication" class="form-input" value="{{ old('wallet_price_authentication', $settings->wallet_price_authentication ?? 0.35) }}">
                    </div>

                    <div class="form-group">
                        <label for="wallet_min_balance" class="form-label">Minimum Wallet Balance Threshold (₹)</label>
                        <input type="number" step="1" id="wallet_min_balance" name="wallet_min_balance" class="form-input" value="{{ old('wallet_min_balance', $settings->wallet_min_balance ?? 200) }}">
                    </div>
                </div>
            </div>

            <!-- 6. GST & Tax Invoicing Compliance -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fa-solid fa-file-invoice-dollar" style="color: #14b8a6;"></i>
                    <span>GST & Tax Invoicing Compliance</span>
                </div>
                <div class="settings-body">
                    <label class="switch-wrap">
                        <input type="checkbox" name="gst_enabled" value="1" {{ ($settings->gst_enabled ?? true) ? 'checked' : '' }}>
                        <span class="switch-text">Enable GST Calculation on Wallet Recharges & Plans</span>
                    </label>

                    <div class="form-group">
                        <label for="gst_percentage" class="form-label">GST Tax Percentage (%)</label>
                        <input type="number" step="0.01" id="gst_percentage" name="gst_percentage" class="form-input" value="{{ old('gst_percentage', $settings->gst_percentage ?? 18.00) }}" placeholder="18.00">
                    </div>

                    <div class="form-group">
                        <label for="gstin" class="form-label">Company GSTIN Number</label>
                        <input type="text" id="gstin" name="gstin" class="form-input" value="{{ old('gstin', $settings->gstin) }}" placeholder="24AAAAA0000A1Z5">
                    </div>

                    <div class="form-group">
                        <label for="gst_state" class="form-label">Registered Business State</label>
                        <input type="text" id="gst_state" name="gst_state" class="form-input" value="{{ old('gst_state', $settings->gst_state ?: 'Gujarat') }}" placeholder="e.g. Gujarat">
                    </div>
                </div>
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-bottom: 40px;">
            <button type="submit" class="btn-save-sticky" style="padding: 16px 36px; font-size: 1rem;">
                <i class="fa-solid fa-floppy-disk"></i>
                <span>Save All Platform Settings</span>
            </button>
        </div>
    </form>

@endsection
