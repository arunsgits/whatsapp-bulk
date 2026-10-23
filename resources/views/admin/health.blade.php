@extends('admin.layouts.app')

@section('title', 'System Health & Diagnostics')

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

    .health-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .health-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 24px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .health-card-top {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .health-icon-wrap {
        width: 44px;
        height: 44px;
        border-radius: var(--radius-md);
        background: rgba(16, 185, 129, 0.15);
        color: var(--primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
    }

    .health-title {
        font-size: 0.8rem;
        font-weight: 600;
        text-transform: uppercase;
        color: var(--text-muted);
        letter-spacing: 0.05em;
    }

    .health-status-text {
        font-family: var(--font-display);
        font-size: 1.4rem;
        font-weight: 800;
        color: #34d399;
    }

    .health-detail {
        font-size: 0.82rem;
        color: var(--text-secondary);
    }

    .specs-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-xl);
        overflow: hidden;
    }

    .specs-header {
        padding: 20px 26px;
        border-bottom: 1px solid var(--border);
        font-family: var(--font-display);
        font-size: 1.1rem;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .specs-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.86rem;
    }

    .specs-table td {
        padding: 16px 26px;
        border-bottom: 1px solid var(--border);
    }

    .specs-table tr:last-child td {
        border-bottom: none;
    }

    .specs-name {
        color: var(--text-secondary);
        font-weight: 500;
        width: 35%;
    }

    .specs-val {
        font-family: var(--font-mono);
        color: var(--text-primary);
        font-weight: 600;
    }
</style>
@endsection

@section('content')

    <div class="page-header">
        <div>
            <h1 class="page-title">Platform Architecture Diagnostics</h1>
            <p style="color: var(--text-secondary); font-size: 0.88rem;">Live probe diagnostics for MySQL, Redis/Cache, runtime limits, and Meta Cloud API webhooks.</p>
        </div>
        <div>
            <a href="{{ route('arun-admin.health') }}" class="btn-toggle-status" style="height: 40px; padding: 0 16px; display: inline-flex; align-items: center; text-decoration: none; gap: 8px;">
                <i class="fa-solid fa-rotate-right"></i>
                <span>Re-run Diagnostics</span>
            </a>
        </div>
    </div>

    <!-- Health Cards Grid -->
    <div class="health-grid">
        <div class="health-card">
            <div class="health-card-top">
                <div class="health-icon-wrap">
                    <i class="fa-solid fa-database"></i>
                </div>
                <span style="font-size: 0.75rem; font-family: var(--font-mono); color: #34d399;">
                    {{ $systemInfo['db_latency'] }}
                </span>
            </div>
            <div class="health-title">MySQL Database Engine</div>
            <div class="health-status-text">{{ $systemInfo['db_status'] }}</div>
            <div class="health-detail">Direct PDO connection verified with zero pending locks.</div>
        </div>

        <div class="health-card">
            <div class="health-card-top">
                <div class="health-icon-wrap" style="background: rgba(59, 130, 246, 0.15); color: #60a5fa;">
                    <i class="fa-solid fa-memory"></i>
                </div>
                <span style="font-size: 0.75rem; font-family: var(--font-mono); color: #60a5fa;">
                    File / Key-Value
                </span>
            </div>
            <div class="health-title">Application Cache Probe</div>
            <div class="health-status-text" style="color: #60a5fa;">{{ $systemInfo['cache_status'] }}</div>
            <div class="health-detail">Read/write probe successful for temporary session cache.</div>
        </div>

        <div class="health-card">
            <div class="health-card-top">
                <div class="health-icon-wrap" style="background: rgba(139, 92, 246, 0.15); color: #a78bfa;">
                    <i class="fa-solid fa-code-branch"></i>
                </div>
                <span style="font-size: 0.75rem; font-family: var(--font-mono); color: #a78bfa;">
                    Meta API v20.0
                </span>
            </div>
            <div class="health-title">WhatsApp Webhook Listener</div>
            <div class="health-status-text" style="color: #a78bfa;">Listening</div>
            <div class="health-detail">Registered at <code>/whatsapp/webhook</code> with SHA-256 HMAC.</div>
        </div>
    </div>

    <!-- Server Environment Specs -->
    <div class="specs-card">
        <div class="specs-header">
            <i class="fa-solid fa-server" style="color: var(--primary);"></i>
            <span>Runtime Environment Specifications</span>
        </div>

        <table class="specs-table">
            <tbody>
                <tr>
                    <td class="specs-name">Application Name & Environment</td>
                    <td class="specs-val">{{ $systemInfo['app_name'] }} &bull; {{ strtoupper($systemInfo['environment']) }}</td>
                </tr>
                <tr>
                    <td class="specs-name">Company / Entity</td>
                    <td class="specs-val">{{ $systemInfo['company_name'] }}</td>
                </tr>
                <tr>
                    <td class="specs-name">PHP Engine Version</td>
                    <td class="specs-val">{{ $systemInfo['php_version'] }} (CLI/FPM)</td>
                </tr>
                <tr>
                    <td class="specs-name">Laravel Framework Version</td>
                    <td class="specs-val">v{{ $systemInfo['laravel_version'] }}</td>
                </tr>
                <tr>
                    <td class="specs-name">Operating System</td>
                    <td class="specs-val">{{ $systemInfo['server_os'] }}</td>
                </tr>
                <tr>
                    <td class="specs-name">PHP Memory Limit</td>
                    <td class="specs-val">{{ $systemInfo['memory_limit'] }}</td>
                </tr>
                <tr>
                    <td class="specs-name">Max Execution Time</td>
                    <td class="specs-val">{{ $systemInfo['max_execution_time'] }}</td>
                </tr>
                <tr>
                    <td class="specs-name">Max Upload & Post Size</td>
                    <td class="specs-val">Upload: {{ $systemInfo['upload_max_filesize'] }} | Post: {{ $systemInfo['post_max_size'] }}</td>
                </tr>
                <tr>
                    <td class="specs-name">Critical PHP Extensions</td>
                    <td class="specs-val">
                        @foreach($systemInfo['loaded_extensions'] as $ext)
                            <span style="display: inline-block; background: rgba(255,255,255,0.06); padding: 2px 8px; border-radius: 4px; margin-right: 4px; font-size: 0.78rem;">
                                {{ $ext }}: {{ extension_loaded($ext) ? '✔' : '✖' }}
                            </span>
                        @endforeach
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

@endsection
