<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - {{ $invoice['invoice_number'] ?? 'Invoice' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #0f172a;
            --primary-light: #1e293b;
            --accent: #2563eb;
            --emerald: #059669;
            --emerald-bg: #ecfdf5;
            --border: #e2e8f0;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --bg-light: #f8fafc;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, DejaVu Sans, sans-serif;
        }
        .rupee {
            font-family: 'DejaVu Sans', sans-serif !important;
        }
        @if(!empty($isPdf))
        * {
            font-family: 'DejaVu Sans', sans-serif !important;
        }
        .rupee {
            font-family: 'DejaVu Sans', sans-serif !important;
        }
        .calc-row.final, .words-content {
            font-weight: 700 !important;
        }
        body {
            background-color: #ffffff !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .invoice-card {
            max-width: 100% !important;
            margin: 0 !important;
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
        }
        .action-bar {
            display: none !important;
        }
        @else
        body {
            background-color: #f1f5f9;
            color: var(--text-dark);
            line-height: 1.5;
            padding: 40px 20px;
        }
        @endif
        .invoice-card {
            max-width: 860px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .header-bar {
            background-color: #0f172a !important;
            background: #0f172a !important;
            color: #ffffff !important;
            padding: 30px 36px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            border: none;
            padding: 0;
            vertical-align: top;
            background-color: transparent !important;
        }
        .brand-logo {
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }
        .brand-logo .dot {
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
        }
        .header-contact-line {
            font-size: 12px;
            color: #cbd5e1;
            line-height: 1.5;
            margin-bottom: 3px;
        }
        .header-contact-line strong {
            color: #ffffff;
        }
        .header-gstin-tag {
            display: inline-block;
            margin-top: 5px;
            background: rgba(56, 189, 248, 0.15);
            color: #38bdf8;
            border: 1px solid rgba(56, 189, 248, 0.3);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h1 {
            font-size: 26px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #38bdf8;
            margin-bottom: 6px;
        }
        .invoice-no-box {
            display: inline-block;
            text-align: right;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.18);
            border-radius: 8px;
            padding: 6px 14px;
            margin-top: 2px;
        }
        .invoice-no-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #94a3b8;
            font-weight: 700;
            display: block;
            margin-bottom: 2px;
        }
        .invoice-no {
            font-size: 15px;
            color: #ffffff;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .invoice-date-sub {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 6px;
        }
        .billed-to-card {
            background: var(--bg-light);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 32px;
        }
        .billed-to-card h3 {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--border);
        }
        .content-body {
            padding: 40px;
        }
        .meta-strip {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--bg-light);
            padding: 16px 24px;
            border-radius: 12px;
            margin-bottom: 32px;
            border: 1px solid var(--border);
        }
        .meta-item {
            font-size: 13px;
        }
        .meta-label {
            color: var(--text-muted);
            text-transform: uppercase;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .meta-value {
            font-size: 14px;
            font-weight: 600;
            color: var(--text-dark);
        }
        .badge-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-paid {
            background-color: var(--emerald-bg);
            color: var(--emerald);
            border: 1px solid #a7f3d0;
        }
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 32px;
        }
        .entity-card h3 {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid var(--border);
        }
        .entity-name {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .entity-line {
            font-size: 13px;
            color: #475569;
            margin-bottom: 3px;
        }
        .gst-tag {
            display: inline-block;
            margin-top: 6px;
            background: #e0e7ff;
            color: #3730a3;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        .table-container {
            margin-bottom: 28px;
            overflow-x: auto;
        }
        table.invoice-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
        }
        table.invoice-table th {
            background: var(--bg-light);
            color: var(--text-muted);
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.5px;
            padding: 12px 16px;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        table.invoice-table td {
            padding: 16px;
            font-size: 13px;
            border-bottom: 1px solid var(--border);
            color: var(--text-dark);
            vertical-align: top;
        }
        .item-title {
            font-weight: 700;
            font-size: 14px;
            color: var(--text-dark);
            margin-bottom: 4px;
        }
        .item-desc {
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.4;
        }
        .quotas-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-top: 8px;
        }
        .quota-chip {
            background: #f1f5f9;
            color: #334155;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .totals-section {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 32px;
            gap: 40px;
        }
        .words-block {
            flex: 1;
            background: #f8fafc;
            border: 1px dashed var(--border);
            border-radius: 12px;
            padding: 16px 20px;
        }
        .words-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 4px;
        }
        .words-content {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-dark);
        }
        .totals-block {
            width: 340px;
        }
        .calc-row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            padding: 6px 0;
            color: #475569;
        }
        .calc-row.final {
            border-top: 2px solid var(--primary);
            margin-top: 8px;
            padding-top: 12px;
            font-size: 17px;
            font-weight: 800;
            color: var(--primary);
        }
        .footer-note {
            border-top: 1px solid var(--border);
            padding-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            font-size: 12px;
            color: var(--text-muted);
        }
        .signatory-box {
            text-align: right;
        }
        .signatory-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-top: 28px;
        }
        .action-bar {
            position: fixed;
            bottom: 24px;
            right: 24px;
            display: flex;
            gap: 12px;
            z-index: 999;
        }
        .btn-action {
            background: #0f172a;
            color: #ffffff;
            border: none;
            padding: 12px 22px;
            border-radius: 9999px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        .btn-action:hover {
            background: #1e293b;
            transform: translateY(-2px);
        }
        .btn-action.btn-secondary {
            background: #ffffff;
            color: #0f172a;
            border: 1px solid #cbd5e1;
        }
        .btn-action.btn-secondary:hover {
            background: #f8fafc;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .invoice-card {
                box-shadow: none;
                border: none;
                max-width: 100%;
            }
            .action-bar {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <div class="invoice-card">
        <!-- Header -->
        <div class="header-bar">
            <table class="header-table">
                <tr>
                    <td style="width: 58%; vertical-align: top;">
                        <div class="brand-logo">
                            <span class="dot"></span>
                            {{ $invoice['seller']['company'] ?? $invoice['seller']['name'] ?? config('app.company_name', 'ZapReach Technologies') }}
                        </div>
                        @if(!empty($invoice['seller']['address']))
                            <div class="header-contact-line">
                                <strong>Address:</strong> {{ $invoice['seller']['address'] }}
                            </div>
                        @endif
                        @if(!empty($invoice['seller']['email']))
                            <div class="header-contact-line">
                                <strong>Email:</strong> {{ $invoice['seller']['email'] }}
                            </div>
                        @endif
                        @if(!empty($invoice['seller']['gstin']))
                            <div class="header-gstin-tag">
                                GSTIN: {{ $invoice['seller']['gstin'] }}
                            </div>
                        @endif
                    </td>
                    <td style="width: 42%; vertical-align: top; text-align: right;">
                        <div class="invoice-title">
                            <h1>Tax Invoice</h1>
                            <div class="invoice-no-box">
                                <span class="invoice-no-label">Invoice Number</span>
                                <span class="invoice-no">{{ $invoice['invoice_number'] ?? 'INV' }}</span>
                            </div>
                            <div class="invoice-date-sub">
                                Date: {{ $invoice['date'] ?? date('d M, Y') }}
                            </div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="content-body">
            <!-- Meta Strip -->
            <table class="meta-strip-table" style="width: 100%; border-collapse: collapse; background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; margin-bottom: 28px;">
                <tr>
                    <td style="width: 25%; vertical-align: top; padding: 14px 18px; border-right: 1px solid #e2e8f0;">
                        <div class="meta-label">Invoice Date</div>
                        <div class="meta-value">{{ $invoice['date'] ?? date('d M, Y') }}</div>
                    </td>
                    <td style="width: 25%; vertical-align: top; padding: 14px 18px; border-right: 1px solid #e2e8f0;">
                        <div class="meta-label">Receipt / Ref ID</div>
                        <div class="meta-value">{{ $invoice['receipt'] ?? 'N/A' }}</div>
                    </td>
                    <td style="width: 25%; vertical-align: top; padding: 14px 18px; border-right: 1px solid #e2e8f0;">
                        <div class="meta-label">Payment Method</div>
                        <div class="meta-value">{{ $invoice['payment_method'] ?? 'Online' }}</div>
                    </td>
                    <td style="width: 25%; vertical-align: top; padding: 14px 18px; text-align: right;">
                        <div class="meta-label">Payment Status</div>
                        <div>
                            <span class="badge-status badge-paid">{{ $invoice['status'] ?? 'PAID' }}</span>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Billed To (Customer / Subscriber Only) -->
            <div class="billed-to-card">
                <h3>Billed To (Customer / Subscriber)</h3>
                <div class="entity-name">{{ $invoice['customer']['company'] ?: $invoice['customer']['name'] }}</div>
                @if(!empty($invoice['customer']['name']) && $invoice['customer']['name'] !== $invoice['customer']['company'])
                    <div class="entity-line"><strong>Attn:</strong> {{ $invoice['customer']['name'] }}</div>
                @endif
                @if(!empty($invoice['customer']['address']))
                    <div class="entity-line"><strong>Address:</strong> {{ $invoice['customer']['address'] }}</div>
                @endif
                @if(!empty($invoice['customer']['email']))
                    <div class="entity-line"><strong>Email:</strong> {{ $invoice['customer']['email'] }}</div>
                @endif
                @if(!empty($invoice['customer']['phone']))
                    <div class="entity-line"><strong>Phone:</strong> {{ $invoice['customer']['phone'] }}</div>
                @endif
                @if(!empty($invoice['customer']['gstin']))
                    <div class="gst-tag">Customer GSTIN: {{ $invoice['customer']['gstin'] }}</div>
                @endif
            </div>

            <!-- Line Items Table -->
            <div class="table-container">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Description of Services</th>
                            <th style="width: 100px;">SAC Code</th>
                            <th style="width: 60px; text-align: center;">Qty</th>
                            <th style="width: 120px; text-align: right;">Taxable Value</th>
                            <th style="width: 130px; text-align: right;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($invoice['items'] as $item)
                        <tr>
                            <td>{{ $item['sr_no'] ?? 1 }}</td>
                            <td>
                                <div class="item-title" style="font-weight: 700; font-size: 13.5px; color: #0f172a; margin-bottom: 0;">{{ $item['name'] }}</div>
                            </td>
                            <td>{{ $item['sac_code'] ?? '998313' }}</td>
                            <td style="text-align: center;">{{ $item['quantity'] ?? 1 }}</td>
                            <td style="text-align: right; font-weight: 700;"><span class="rupee">&#8377;</span>{{ number_format($item['taxable_amount'] ?? $item['unit_price'], 2) }}</td>
                            <td style="text-align: right; font-weight: 700;"><span class="rupee">&#8377;</span>{{ number_format($item['total_amount'], 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Totals & Calculations (Universal Table Layout) -->
            <table class="totals-table" style="width: 100%; border-collapse: collapse; margin-bottom: 28px;">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding-right: 20px;">
                        <div class="words-block" style="background-color: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 16px 18px;">
                            <div class="words-title" style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 6px;">Total In Words</div>
                            <div class="words-content" style="font-size: 13px; font-weight: 700; color: #0f172a; line-height: 1.4;">{{ $invoice['tax_breakdown']['amount_in_words'] ?? 'Rupees Only' }}</div>
                            @if(!empty($invoice['notes']))
                            <div style="margin-top: 10px; font-size: 11.5px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 8px;">
                                <strong>Remarks:</strong> {{ $invoice['notes'] }}
                            </div>
                            @endif
                        </div>
                    </td>
                    <td style="width: 50%; vertical-align: top;">
                        <div class="totals-block" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 20px;">
                            <table style="width: 100%; border-collapse: collapse;">
                                <tr>
                                    <td style="padding: 4px 0; color: #475569; font-size: 13px; text-align: left;">Taxable Base Value:</td>
                                    <td style="padding: 4px 0; font-weight: 700; font-size: 13px; text-align: right; color: #0f172a;"><span class="rupee">&#8377;</span>{{ number_format($invoice['tax_breakdown']['base_amount'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 4px 0; color: #475569; font-size: 13px; text-align: left;">GST ({{ (int)($invoice['tax_breakdown']['gst_percentage'] ?? 18) }}%):</td>
                                    <td style="padding: 4px 0; font-weight: 700; font-size: 13px; text-align: right; color: #0f172a;"><span class="rupee">&#8377;</span>{{ number_format($invoice['tax_breakdown']['total_tax'] ?? ($invoice['tax_breakdown']['gst_amount'] ?? 0), 2) }}</td>
                                </tr>
                                <tr style="border-top: 1px solid #cbd5e1;">
                                    <td style="padding: 8px 0 0; font-weight: 700; font-size: 15px; color: #0f172a; text-align: left;">Grand Total:</td>
                                    <td style="padding: 8px 0 0; font-weight: 700; font-size: 17px; color: #2563eb; text-align: right;"><span class="rupee">&#8377;</span>{{ number_format($invoice['tax_breakdown']['total_amount'], 2) }}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Footer (Universal Table Layout) -->
            <table class="footer-table" style="width: 100%; border-collapse: collapse; margin-top: 28px; padding-top: 20px; border-top: 1px dashed #cbd5e1;">
                <tr>
                    <td style="width: 65%; vertical-align: bottom; font-size: 11.5px; color: #64748b; line-height: 1.5;">
                        <div style="font-weight: 700; color: #0f172a; margin-bottom: 2px;">Terms & Conditions:</div>
                        <div>1. This is a computer generated tax invoice and requires no physical signature.</div>
                        <div>2. Subscriptions are billed per platform service period and non-refundable.</div>
                        <div>3. HSN/SAC 998313 applies for information technology and internet messaging services.</div>
                    </td>
                    <td style="width: 35%; vertical-align: bottom; text-align: right;">
                        <div class="signatory-box" style="display: inline-block; text-align: center; border-top: 1px solid #cbd5e1; padding-top: 6px; min-width: 180px;">
                            <div style="font-weight: 700; color: #0f172a; font-size: 13px;">For {{ $invoice['seller']['company'] ?? $invoice['seller']['name'] }}</div>
                            <div class="signatory-label" style="font-size: 11px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">Authorized Signatory</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    @if(empty($isPdf))
    <!-- Floating Action Buttons -->
    <div class="action-bar">
        @if(!empty($invoice['download_url']))
        <a href="{{ $invoice['download_url'] }}" class="btn-action" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M.5 9.9a.5.5 0 0 1 .5.5v2.5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-2.5a.5.5 0 0 1 1 0v2.5a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2v-2.5a.5.5 0 0 1 .5-.5z"/>
                <path d="M7.646 11.854a.5.5 0 0 0 .708 0l3-3a.5.5 0 0 0-.708-.708L8.5 10.293V1.5a.5.5 0 0 0-1 0v8.793L5.354 8.146a.5.5 0 1 0-.708.708l3 3z"/>
            </svg>
            Download PDF
        </a>
        @endif
        <button class="btn-action" onclick="window.print()">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            Print
        </button>
        <button class="btn-action btn-secondary" onclick="window.close()">Close</button>
    </div>
    @endif

</body>
</html>
