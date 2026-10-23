@extends('admin.layouts.app')

@section('title', 'Platform Financial Transactions & Ledger')

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

    /* KPI Summary Cards */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 28px;
    }

    .kpi-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 20px 24px;
        display: flex;
        align-items: center;
        gap: 18px;
        transition: transform 0.2s ease, border-color 0.2s ease;
    }

    .kpi-card:hover {
        transform: translateY(-2px);
        border-color: var(--border-hover);
    }

    .kpi-icon-wrap {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.3rem;
    }

    .kpi-val {
        font-family: var(--font-display);
        font-size: 1.45rem;
        font-weight: 800;
        line-height: 1.1;
        margin-bottom: 4px;
    }

    .kpi-lbl {
        font-size: 0.76rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
    }

    /* Filter Bar */
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
        min-width: 260px;
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
        height: 40px;
        background: rgba(0, 0, 0, 0.25);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 0 14px 0 38px;
        font-size: 0.88rem;
        color: var(--text-primary);
        outline: none;
    }

    .search-field:focus {
        border-color: var(--primary);
    }

    .filter-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }

    .select-filter {
        height: 40px;
        background: rgba(0, 0, 0, 0.25);
        border: 1px solid var(--border);
        border-radius: var(--radius-md);
        padding: 0 14px;
        font-size: 0.84rem;
        color: var(--text-primary);
        outline: none;
    }

    /* Tab Switcher */
    .view-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 20px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 4px;
    }

    .tab-toggle-btn {
        background: transparent;
        border: none;
        padding: 10px 18px;
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
    }

    .tab-toggle-btn:hover {
        color: var(--text-primary);
    }

    .tab-toggle-btn.active {
        color: #ffffff;
        border-bottom-color: var(--primary);
        font-weight: 700;
    }

    .tab-section {
        display: none;
    }

    .tab-section.active {
        display: block;
        animation: fadeIn 0.2s ease;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(4px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Tables */
    .table-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }

    .admin-table th {
        text-align: left;
        padding: 14px 20px;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        background: rgba(255, 255, 255, 0.015);
        border-bottom: 1px solid var(--border);
    }

    .admin-table td {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }

    .admin-table tr:hover td {
        background: rgba(255, 255, 255, 0.02);
    }

    .tenant-badge-cell {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .tenant-avatar-sm {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.82rem;
        color: #ffffff;
        flex-shrink: 0;
    }

    .badge-credit {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 4px;
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }

    .badge-debit {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.72rem;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 4px;
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }

    .badge-order-paid {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.72rem;
        text-transform: uppercase;
    }

    .badge-order-pending {
        background: rgba(245, 158, 11, 0.15);
        color: #fbbf24;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.72rem;
        text-transform: uppercase;
    }

    .badge-order-failed {
        background: rgba(239, 68, 68, 0.15);
        color: #f87171;
        font-weight: 700;
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 0.72rem;
        text-transform: uppercase;
    }

    .btn-filter {
        background: rgba(59, 130, 246, 0.12);
        border: 1px solid rgba(59, 130, 246, 0.3);
        color: #60a5fa;
        font-size: 0.82rem;
        font-weight: 600;
        padding: 0 16px;
        height: 40px;
        border-radius: var(--radius-md);
        display: inline-flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    .table-responsive {
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
    }

    .admin-table {
        min-width: 800px;
    }

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
        .view-tabs {
            overflow-x: auto;
            white-space: nowrap;
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
            <h1 class="page-title">Platform Financial Ledger & Transactions</h1>
            <p style="color: var(--text-secondary); font-size: 0.88rem;">
                Super Admin audit trail of all customer wallet recharge orders, broadcast debit charges, and manual adjustments.
            </p>
        </div>
    </div>

    <!-- 4 Financial KPI Cards -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon-wrap" style="background: rgba(16, 185, 129, 0.12); color: #10b981;">
                <i class="fa-solid fa-arrow-down-to-bracket"></i>
            </div>
            <div>
                <div class="kpi-val" style="color: #10b981;">₹{{ number_format($totalRecharges, 2) }}</div>
                <div class="kpi-lbl">Total Recharges Collected</div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon-wrap" style="background: rgba(239, 68, 68, 0.12); color: #ef4444;">
                <i class="fa-solid fa-fire-flame-curved"></i>
            </div>
            <div>
                <div class="kpi-val" style="color: #f87171;">₹{{ number_format($totalDebitUsage, 2) }}</div>
                <div class="kpi-lbl">Total Broadcast Debited</div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon-wrap" style="background: rgba(245, 158, 11, 0.12); color: #f59e0b;">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div>
                <div class="kpi-val" style="color: #f59e0b;">₹{{ number_format($netWalletFloat, 2) }}</div>
                <div class="kpi-lbl">Active Client Float</div>
            </div>
        </div>

        <div class="kpi-card">
            <div class="kpi-icon-wrap" style="background: rgba(59, 130, 246, 0.12); color: #3b82f6;">
                <i class="fa-solid fa-receipt"></i>
            </div>
            <div>
                <div class="kpi-val">{{ number_format($totalTransactionsCount) }}</div>
                <div class="kpi-lbl">Total Ledger Records</div>
            </div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <form method="GET" action="{{ route('arun-admin.transactions.index') }}" class="filter-bar">
        <div class="search-input-wrap">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="search" class="search-field" placeholder="Search by Txn Ref, tenant name, email, order ID..." value="{{ request('search') }}">
        </div>

        <div class="filter-actions">
            <select name="type" class="select-filter" onchange="this.form.submit()">
                <option value="">All Types (+ / -)</option>
                <option value="credit" {{ request('type') === 'credit' ? 'selected' : '' }}>Credit (+ Recharges/Refunds)</option>
                <option value="debit" {{ request('type') === 'debit' ? 'selected' : '' }}>Debit (- Broadcasts/Usage)</option>
            </select>

            <select name="category" class="select-filter" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <option value="recharge" {{ request('category') === 'recharge' ? 'selected' : '' }}>Recharge</option>
                <option value="broadcast" {{ request('category') === 'broadcast' ? 'selected' : '' }}>Broadcast Usage</option>
                <option value="utility" {{ request('category') === 'utility' ? 'selected' : '' }}>Utility Usage</option>
                <option value="adjustment" {{ request('category') === 'adjustment' ? 'selected' : '' }}>Manual Adjustment</option>
            </select>

            <button type="submit" class="btn-filter">
                <i class="fa-solid fa-filter"></i>
                <span>Filter</span>
            </button>

            @if(request()->hasAny(['search', 'type', 'category', 'order_status']))
                <a href="{{ route('arun-admin.transactions.index') }}" class="btn-filter" style="background: rgba(255,255,255,0.05); color: var(--text-secondary); border-color: var(--border); text-decoration: none;">
                    <i class="fa-solid fa-rotate-left"></i>
                    <span>Reset</span>
                </a>
            @endif
        </div>
    </form>

    <!-- Interactive Navigation Tabs -->
    <div class="view-tabs">
        <button type="button" class="tab-toggle-btn active" onclick="switchSection('section-ledger')">
            <i class="fa-solid fa-book"></i>
            <span>Wallet Ledger ({{ $transactions->total() }})</span>
        </button>
        <button type="button" class="tab-toggle-btn" onclick="switchSection('section-orders')">
            <i class="fa-solid fa-file-invoice-dollar"></i>
            <span>Razorpay Recharge Orders ({{ $orders->total() }})</span>
        </button>
    </div>

    <!-- TAB 1: Wallet Ledger -->
    <div class="tab-section active" id="section-ledger">
        <div class="table-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Ref ID</th>
                            <th>Tenant Workspace</th>
                            <th>Type</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Balance After</th>
                            <th>Audit Description</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($transactions as $txn)
                            <tr>
                                <td style="font-family: var(--font-mono); font-size: 0.78rem; font-weight: 600; color: var(--text-muted);">
                                    {{ $txn->reference_id ?: '#' . $txn->id }}
                                </td>
                                <td>
                                    @if($txn->user)
                                        <div class="tenant-badge-cell">
                                            <div class="tenant-avatar-sm">
                                                {{ strtoupper(substr($txn->user->name ?: 'U', 0, 1)) }}
                                            </div>
                                            <div>
                                                <div style="font-weight: 700; color: var(--text-primary);">
                                                    <a href="{{ route('arun-admin.clients.show', $txn->user->id) }}" style="color: inherit; text-decoration: none;">
                                                        {{ $txn->user->name }}
                                                    </a>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                    {{ $txn->user->companyName ?: $txn->user->email }}
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span style="color: var(--text-muted); font-size: 0.78rem;">Deleted User #{{ $txn->userId }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if($txn->type === 'credit')
                                        <span class="badge-credit">
                                            <i class="fa-solid fa-arrow-up"></i> CREDIT
                                        </span>
                                    @else
                                        <span class="badge-debit">
                                            <i class="fa-solid fa-arrow-down"></i> DEBIT
                                        </span>
                                    @endif
                                </td>
                                <td style="text-transform: uppercase; font-size: 0.74rem; font-weight: 600; color: #cbd5e1;">
                                    {{ $txn->category ?: $txn->source }}
                                </td>
                                <td style="font-family: var(--font-mono); font-weight: 800; font-size: 0.92rem; color: {{ $txn->type === 'credit' ? '#10b981' : '#f87171' }};">
                                    {{ $txn->type === 'credit' ? '+' : '-' }}₹{{ number_format($txn->amount, 2) }}
                                </td>
                                <td style="font-family: var(--font-mono); font-weight: 700; color: #94a3b8;">
                                    ₹{{ number_format($txn->balance_after, 2) }}
                                </td>
                                <td style="font-size: 0.82rem; color: var(--text-secondary); max-width: 320px;">
                                    {{ $txn->description ?: 'N/A' }}
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-muted);">
                                    {{ $txn->created_at ? $txn->created_at->format('M d, Y H:i:s') : 'N/A' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="text-align: center; color: var(--text-muted); padding: 48px;">
                                    <i class="fa-solid fa-receipt" style="font-size: 2rem; margin-bottom: 12px; opacity: 0.3; display: block;"></i>
                                    No wallet transactions found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($transactions->hasPages())
                <div class="pagination-wrap">
                    {{ $transactions->links() }}
                </div>
            @endif
        </div>
    </div>

    <!-- TAB 2: Razorpay Orders & Invoices -->
    <div class="tab-section" id="section-orders">
        <div class="table-card">
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order ID / Receipt</th>
                            <th>Tenant Workspace</th>
                            <th>Amount</th>
                            <th>Payment Status</th>
                            <th>Gateway Payment ID</th>
                            <th>Plan / Purpose</th>
                            <th>Date & Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($orders as $order)
                            <tr>
                                <td style="font-family: var(--font-mono); font-size: 0.8rem; font-weight: 700; color: #60a5fa;">
                                    {{ $order->razorpay_order_id ?: $order->receipt ?: '#' . $order->id }}
                                </td>
                                <td>
                                    @if($order->user)
                                        <div class="tenant-badge-cell">
                                            <div class="tenant-avatar-sm" style="background: linear-gradient(135deg, #10b981, #059669);">
                                                {{ strtoupper(substr($order->user->name ?: 'U', 0, 1)) }}
                                            </div>
                                            <div>
                                                <div style="font-weight: 700; color: var(--text-primary);">
                                                    <a href="{{ route('arun-admin.clients.show', $order->user->id) }}" style="color: inherit; text-decoration: none;">
                                                        {{ $order->user->name }}
                                                    </a>
                                                </div>
                                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                                    {{ $order->user->email }}
                                                </div>
                                            </div>
                                        </div>
                                    @else
                                        <span style="color: var(--text-muted); font-size: 0.78rem;">Deleted User #{{ $order->userId }}</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="font-family: var(--font-mono); font-weight: 800; font-size: 0.92rem; color: #10b981;">
                                        ₹{{ number_format($order->amount, 2) }}
                                    </div>
                                    @if($order->gst_amount > 0)
                                        <div style="font-size: 0.72rem; color: var(--text-muted);">
                                            Base: ₹{{ number_format($order->base_amount, 2) }} + GST: ₹{{ number_format($order->gst_amount, 2) }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    @if(strtolower($order->status) === 'paid' || strtolower($order->status) === 'completed')
                                        <span class="badge-order-paid">
                                            <i class="fa-solid fa-circle-check"></i> Paid
                                        </span>
                                    @elseif(strtolower($order->status) === 'pending')
                                        <span class="badge-order-pending">
                                            <i class="fa-solid fa-clock"></i> Pending
                                        </span>
                                    @else
                                        <span class="badge-order-failed">
                                            <i class="fa-solid fa-xmark"></i> {{ $order->status }}
                                        </span>
                                    @endif
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-secondary);">
                                    {{ $order->razorpay_payment_id ?: 'Pending' }}
                                </td>
                                <td>
                                    @if($order->package)
                                        <span style="font-size: 0.75rem; font-weight: 700; color: #60a5fa; background: rgba(59,130,246,0.12); padding: 3px 8px; border-radius: 4px;">
                                            {{ $order->package->packageName }}
                                        </span>
                                    @else
                                        <span style="font-size: 0.75rem; font-weight: 600; text-transform: capitalize; color: var(--text-secondary);">
                                            {{ $order->payment_type ?: 'Wallet Top-Up' }}
                                        </span>
                                    @endif
                                </td>
                                <td style="font-family: var(--font-mono); font-size: 0.78rem; color: var(--text-muted);">
                                    {{ $order->created_at ? $order->created_at->format('M d, Y H:i') : 'N/A' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 48px;">
                                    <i class="fa-solid fa-file-invoice" style="font-size: 2rem; margin-bottom: 12px; opacity: 0.3; display: block;"></i>
                                    No recharge orders found matching your criteria.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($orders->hasPages())
                <div class="pagination-wrap">
                    {{ $orders->links() }}
                </div>
            @endif
        </div>
    </div>

@endsection

@section('scripts')
<script>
    function switchSection(sectionId) {
        document.querySelectorAll('.tab-toggle-btn').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-section').forEach(sec => sec.classList.remove('active'));

        const target = document.getElementById(sectionId);
        if (target) {
            target.classList.add('active');
        }

        event.currentTarget.classList.add('active');
    }
</script>
@endsection
