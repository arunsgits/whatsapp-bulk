<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('invoice_templates')) {
            Schema::create('invoice_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('subject_title')->default('TAX INVOICE');
                $table->string('header_logo')->nullable();
                $table->string('accent_color')->default('#2563eb');
                $table->longText('body_html');
                $table->text('terms_text')->nullable();
                $table->json('placeholders')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed the initial default invoice template
            $this->seedDefaultTemplate();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_templates');
    }

    /**
     * Seed initial default invoice template with unified GST (No CGST / SGST breakdown)
     */
    private function seedDefaultTemplate(): void
    {
        $placeholders = [
            ['key' => 'invoice_number', 'description' => 'Invoice sequential number e.g. INV-2026-00001'],
            ['key' => 'invoice_date', 'description' => 'Invoice generated date (e.g. 08 Oct, 2026)'],
            ['key' => 'receipt', 'description' => 'Payment receipt or transaction ID'],
            ['key' => 'payment_method', 'description' => 'Payment mode (e.g. Razorpay, Online, Admin)'],
            ['key' => 'payment_status', 'description' => 'Payment status (e.g. PAID)'],
            ['key' => 'package_name', 'description' => 'Purchased subscription or package name'],
            ['key' => 'seller_company', 'description' => 'Company legal name of seller/admin'],
            ['key' => 'seller_gstin', 'description' => 'GSTIN of seller'],
            ['key' => 'seller_address', 'description' => 'Office address of seller'],
            ['key' => 'seller_email', 'description' => 'Support / Billing email of seller'],
            ['key' => 'seller_state', 'description' => 'State where seller is registered'],
            ['key' => 'customer_name', 'description' => 'Client / Subscriber contact name'],
            ['key' => 'customer_company', 'description' => 'Client organization or business name'],
            ['key' => 'customer_email', 'description' => 'Client email address'],
            ['key' => 'customer_phone', 'description' => 'Client phone number'],
            ['key' => 'customer_address', 'description' => 'Client registered address'],
            ['key' => 'customer_gstin', 'description' => 'Client GSTIN if available'],
            ['key' => 'items_table_rows', 'description' => 'Dynamic HTML table rows of purchased items (shows only package name)'],
            ['key' => 'taxable_base', 'description' => 'Taxable base amount before tax formatted (e.g. 1,499.00)'],
            ['key' => 'gst_rate', 'description' => 'Unified GST percentage rate (e.g. 18)'],
            ['key' => 'gst_amount', 'description' => 'Total GST tax amount formatted (e.g. 269.82)'],
            ['key' => 'grand_total', 'description' => 'Final grand total amount formatted (e.g. 1,768.82)'],
            ['key' => 'amount_in_words', 'description' => 'Grand total amount spelled out in English words'],
            ['key' => 'terms_text', 'description' => 'Invoice terms and conditions lines'],
            ['key' => 'notes', 'description' => 'Customer or transaction remark notes'],
            ['key' => 'accent_color', 'description' => 'Theme highlight accent hex color'],
            ['key' => 'logo_img_tag', 'description' => 'Rendered HTML image tag of the invoice logo'],
        ];

        $terms = "1. This is a computer generated tax invoice and requires no physical signature.\n2. Subscriptions are billed per platform service period and non-refundable.\n3. HSN/SAC 998313 applies for information technology and internet messaging services.";

        $bodyHtml = <<<'HTML'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Invoice - {{invoice_number}}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'DejaVu Sans', sans-serif !important;
        }
        body {
            background-color: #ffffff;
            color: #0f172a;
            font-size: 13px;
            line-height: 1.5;
            padding: 20px;
        }
        .invoice-card {
            max-width: 860px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }
        .header-bar {
            background-color: #0f172a;
            color: #ffffff;
            padding: 28px 32px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-table td {
            vertical-align: top;
            border: none;
            padding: 0;
            background: transparent;
        }
        .brand-logo {
            font-size: 20px;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
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
            font-weight: 700;
        }
        .invoice-title {
            text-align: right;
        }
        .invoice-title h1 {
            font-size: 24px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: {{accent_color}};
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
            font-size: 14px;
            color: #ffffff;
            font-weight: 700;
        }
        .content-body {
            padding: 32px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            margin-bottom: 28px;
        }
        .meta-table td {
            padding: 12px 16px;
            border-right: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .meta-table td:last-child {
            border-right: none;
        }
        .meta-label {
            color: #64748b;
            text-transform: uppercase;
            font-size: 10.5px;
            font-weight: 700;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .meta-value {
            font-size: 13.5px;
            font-weight: 700;
            color: #0f172a;
        }
        .badge-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 9999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .billed-to-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 18px 22px;
            margin-bottom: 28px;
        }
        .billed-to-card h3 {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }
        .entity-name {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .entity-line {
            font-size: 12px;
            color: #475569;
            margin-bottom: 3px;
            line-height: 1.4;
        }
        .gst-tag {
            display: inline-block;
            margin-top: 6px;
            background: #e0f2fe;
            color: #0369a1;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
        }
        .table-container {
            margin-bottom: 28px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-table th {
            background: #0f172a;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            text-align: left;
        }
        .invoice-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 12.5px;
            color: #334155;
            vertical-align: middle;
        }
        .invoice-table tbody tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .rupee {
            font-family: 'DejaVu Sans', sans-serif !important;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .totals-table td {
            vertical-align: top;
            border: none;
        }
        .words-block {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            padding: 14px 16px;
        }
        .words-title {
            font-size: 10.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 4px;
        }
        .words-content {
            font-size: 12.5px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.4;
        }
        .totals-block {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 18px;
        }
        .totals-inner-table {
            width: 100%;
            border-collapse: collapse;
        }
        .totals-inner-table td {
            padding: 4px 0;
            font-size: 12.5px;
        }
        .totals-grand {
            border-top: 1px solid #cbd5e1;
            font-size: 15px !important;
            font-weight: 700 !important;
            color: {{accent_color}} !important;
            padding-top: 8px !important;
        }
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px dashed #cbd5e1;
        }
        .footer-table td {
            vertical-align: bottom;
            border: none;
        }
        .terms-box {
            font-size: 11px;
            color: #64748b;
            line-height: 1.5;
        }
        .signatory-box {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="invoice-card">
        <!-- Header -->
        <div class="header-bar">
            <table class="header-table">
                <tr>
                    <td style="width: 60%;">
                        {{logo_img_tag}}
                        <div class="brand-logo">{{seller_company}}</div>
                        <div class="header-contact-line"><strong>Address:</strong> {{seller_address}}</div>
                        <div class="header-contact-line"><strong>Email:</strong> {{seller_email}} &nbsp;|&nbsp; <strong>State:</strong> {{seller_state}}</div>
                        <div class="header-gstin-tag">GSTIN: {{seller_gstin}}</div>
                    </td>
                    <td style="width: 40%;" class="invoice-title">
                        <h1>{{subject_title}}</h1>
                        <div class="invoice-no-box">
                            <span class="invoice-no-label">Invoice Number</span>
                            <span class="invoice-no">{{invoice_number}}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="content-body">
            <!-- Metadata Bar -->
            <table class="meta-table">
                <tr>
                    <td style="width: 25%;">
                        <div class="meta-label">Invoice Date</div>
                        <div class="meta-value">{{invoice_date}}</div>
                    </td>
                    <td style="width: 25%;">
                        <div class="meta-label">Receipt / Ref ID</div>
                        <div class="meta-value">{{receipt}}</div>
                    </td>
                    <td style="width: 25%;">
                        <div class="meta-label">Payment Method</div>
                        <div class="meta-value">{{payment_method}}</div>
                    </td>
                    <td style="width: 25%; text-align: right;">
                        <div class="meta-label">Payment Status</div>
                        <div><span class="badge-status">{{payment_status}}</span></div>
                    </td>
                </tr>
            </table>

            <!-- Customer Details -->
            <div class="billed-to-card">
                <h3>Billed To (Customer / Subscriber)</h3>
                <div class="entity-name">{{customer_company}}</div>
                <div class="entity-line"><strong>Attn:</strong> {{customer_name}}</div>
                <div class="entity-line"><strong>Address:</strong> {{customer_address}}</div>
                <div class="entity-line"><strong>Email:</strong> {{customer_email}} &nbsp;|&nbsp; <strong>Phone:</strong> {{customer_phone}}</div>
                {{customer_gstin_block}}
            </div>

            <!-- Items Table -->
            <div class="table-container">
                <table class="invoice-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;">#</th>
                            <th>Description of Services</th>
                            <th style="width: 90px;">SAC Code</th>
                            <th style="width: 50px; text-align: center;">Qty</th>
                            <th style="width: 120px; text-align: right;">Taxable Value</th>
                            <th style="width: 120px; text-align: right;">Total Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{items_table_rows}}
                    </tbody>
                </table>
            </div>

            <!-- Unified GST Totals -->
            <table class="totals-table">
                <tr>
                    <td style="width: 50%; padding-right: 16px;">
                        <div class="words-block">
                            <div class="words-title">Total In Words</div>
                            <div class="words-content">{{amount_in_words}}</div>
                            {{notes_block}}
                        </div>
                    </td>
                    <td style="width: 50%;">
                        <div class="totals-block">
                            <table class="totals-inner-table">
                                <tr>
                                    <td style="color: #475569; text-align: left;">Taxable Base Value:</td>
                                    <td style="font-weight: 700; text-align: right; color: #0f172a;"><span class="rupee">&#8377;</span>{{taxable_base}}</td>
                                </tr>
                                <tr>
                                    <td style="color: #475569; text-align: left;">GST ({{gst_rate}}%):</td>
                                    <td style="font-weight: 700; text-align: right; color: #0f172a;"><span class="rupee">&#8377;</span>{{gst_amount}}</td>
                                </tr>
                                <tr>
                                    <td class="totals-grand" style="text-align: left;">Grand Total:</td>
                                    <td class="totals-grand" style="text-align: right;"><span class="rupee">&#8377;</span>{{grand_total}}</td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>

            <!-- Footer -->
            <table class="footer-table">
                <tr>
                    <td style="width: 65%;">
                        <div class="terms-box">
                            <strong style="color: #0f172a; display: block; margin-bottom: 2px;">Terms & Conditions:</strong>
                            {{terms_html}}
                        </div>
                    </td>
                    <td style="width: 35%;">
                        <div class="signatory-box">
                            <div style="font-weight: 700; color: #0f172a; font-size: 12.5px;">For {{seller_company}}</div>
                            <div style="font-size: 10.5px; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">Authorized Signatory</div>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
HTML;

        DB::table('invoice_templates')->insert([
            'name' => 'Default GST Tax Invoice',
            'slug' => 'default-gst-invoice',
            'subject_title' => 'TAX INVOICE',
            'header_logo' => null,
            'accent_color' => '#2563eb',
            'body_html' => $bodyHtml,
            'terms_text' => $terms,
            'placeholders' => json_encode($placeholders),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
