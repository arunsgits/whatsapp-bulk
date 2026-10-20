<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceTemplate extends Model
{
    use HasFactory;

    protected $table = 'invoice_templates';

    protected $fillable = [
        'name',
        'slug',
        'subject_title',
        'header_logo',
        'accent_color',
        'body_html',
        'terms_text',
        'placeholders',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'placeholders' => 'array',
    ];

    /**
     * Retrieve the currently active invoice template or fallback to the latest
     */
    public static function getActiveTemplate(): ?self
    {
        return self::where('is_active', true)->latest('id')->first();
    }

    /**
     * Replace placeholders in template text with provided key-value data.
     * Supports {{key}}, {{ key }}, and {key}.
     */
    public static function compile(string $content, array $data): string
    {
        $replaceMap = [];
        foreach ($data as $key => $value) {
            $val = (string) ($value ?? '');
            $replaceMap['{{' . $key . '}}'] = $val;
            $replaceMap['{{ ' . $key . ' }}'] = $val;
            $replaceMap['{' . $key . '}'] = $val;
        }

        return strtr($content, $replaceMap);
    }

    /**
     * Build standard placeholders and render the full invoice HTML from structured invoice data
     */
    public function renderInvoiceHtml(array $invoice, bool $isPdf = false): string
    {
        $items = $invoice['items'] ?? [];
        $firstItem = $items[0] ?? [];
        $packageName = $firstItem['name'] ?? ($invoice['payment_type'] ?? 'Subscription Plan');

        // Build item rows HTML (strictly showing package name only, with Rupee symbol in DejaVu Sans)
        $itemsHtml = '';
        foreach ($items as $idx => $item) {
            $srNo = $item['sr_no'] ?? ($idx + 1);
            $name = htmlspecialchars($item['name'] ?? 'Subscription Plan', ENT_QUOTES, 'UTF-8');
            $sac = htmlspecialchars($item['sac_code'] ?? '998313', ENT_QUOTES, 'UTF-8');
            $qty = (int) ($item['quantity'] ?? 1);
            $taxable = number_format((float) ($item['taxable_amount'] ?? ($item['unit_price'] ?? 0)), 2);
            $total = number_format((float) ($item['total_amount'] ?? 0), 2);

            $itemsHtml .= "<tr>\n" .
                "  <td style=\"text-align: center;\">{$srNo}</td>\n" .
                "  <td><div style=\"font-weight: 700; font-size: 13.5px; color: #0f172a;\">{$name}</div></td>\n" .
                "  <td>{$sac}</td>\n" .
                "  <td style=\"text-align: center;\">{$qty}</td>\n" .
                "  <td style=\"text-align: right; font-weight: 700;\"><span class=\"rupee\">&#8377;</span>{$taxable}</td>\n" .
                "  <td style=\"text-align: right; font-weight: 700;\"><span class=\"rupee\">&#8377;</span>{$total}</td>\n" .
                "</tr>\n";
        }

        if (empty($itemsHtml)) {
            $itemsHtml = "<tr><td colspan=\"6\" style=\"text-align:center; padding: 16px; color: #64748b;\">No line items</td></tr>";
        }

        // Terms formatting
        $termsText = $this->terms_text ?: "1. Computer generated tax invoice.\n2. Subscriptions are billed per service period.";
        $termsLines = explode("\n", trim($termsText));
        $termsHtml = '';
        foreach ($termsLines as $line) {
            $line = trim($line);
            if (!empty($line)) {
                $termsHtml .= '<div>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</div>';
            }
        }

        // Customer GSTIN Block
        $customerGstin = $invoice['customer']['gstin'] ?? null;
        $customerGstinBlock = !empty($customerGstin)
            ? '<div class="gst-tag">Customer GSTIN: ' . htmlspecialchars($customerGstin, ENT_QUOTES, 'UTF-8') . '</div>'
            : '';

        // Notes Block
        $notes = $invoice['notes'] ?? null;
        $notesBlock = !empty($notes)
            ? '<div style="margin-top: 10px; font-size: 11.5px; color: #64748b; border-top: 1px solid #e2e8f0; padding-top: 6px;"><strong>Remarks:</strong> ' . htmlspecialchars($notes, ENT_QUOTES, 'UTF-8') . '</div>'
            : '';

        // Logo Tag
        $logoImgTag = '';
        if (!empty($this->header_logo)) {
            $logoUrl = filter_var($this->header_logo, FILTER_VALIDATE_URL) ? $this->header_logo : asset($this->header_logo);
            $logoImgTag = '<img src="' . htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') . '" alt="Logo" style="max-height: 48px; max-width: 180px; margin-bottom: 10px; display: block;" />';
        }

        // Unified GST calculations
        $taxBreakdown = $invoice['tax_breakdown'] ?? [];
        $taxableBase = number_format((float) ($taxBreakdown['base_amount'] ?? 0), 2);
        $gstRate = (int) ($taxBreakdown['gst_percentage'] ?? 18);
        $gstAmount = number_format((float) ($taxBreakdown['total_tax'] ?? ($taxBreakdown['gst_amount'] ?? 0)), 2);
        $grandTotal = number_format((float) ($taxBreakdown['total_amount'] ?? 0), 2);
        $amountInWords = $taxBreakdown['amount_in_words'] ?? 'Rupees Only';

        $sellerCompany = $invoice['seller']['company'] ?? ($invoice['seller']['name'] ?? 'ZapReach Technologies');

        $tokens = [
            'invoice_number' => $invoice['invoice_number'] ?? 'INV-00000',
            'invoice_date' => $invoice['date'] ?? date('d M, Y'),
            'receipt' => $invoice['receipt'] ?? 'N/A',
            'payment_method' => $invoice['payment_method'] ?? 'Online',
            'payment_status' => $invoice['status'] ?? 'PAID',
            'subject_title' => $this->subject_title ?: 'TAX INVOICE',
            'accent_color' => $this->accent_color ?: '#2563eb',
            'package_name' => $packageName,
            'seller_company' => $sellerCompany,
            'seller_name' => $invoice['seller']['name'] ?? $sellerCompany,
            'seller_gstin' => $invoice['seller']['gstin'] ?? '27AAPFU0939L1ZV',
            'seller_address' => $invoice['seller']['address'] ?? 'Ahmedabad, Gujarat, India',
            'seller_email' => $invoice['seller']['email'] ?? 'billing@zapreach.com',
            'seller_state' => $invoice['seller']['state'] ?? 'Gujarat',
            'customer_name' => $invoice['customer']['name'] ?? 'Valued Customer',
            'customer_company' => $invoice['customer']['company'] ?: ($invoice['customer']['name'] ?? 'Customer'),
            'customer_address' => $invoice['customer']['address'] ?? 'India',
            'customer_email' => $invoice['customer']['email'] ?? '',
            'customer_phone' => $invoice['customer']['phone'] ?? '',
            'customer_gstin' => $customerGstin ?? '',
            'customer_gstin_block' => $customerGstinBlock,
            'items_table_rows' => $itemsHtml,
            'taxable_base' => $taxableBase,
            'gst_rate' => (string) $gstRate,
            'gst_amount' => $gstAmount,
            'grand_total' => $grandTotal,
            'amount_in_words' => $amountInWords,
            'notes' => $notes ?? '',
            'notes_block' => $notesBlock,
            'terms_text' => $termsText,
            'terms_html' => $termsHtml,
            'logo_img_tag' => $logoImgTag,
        ];

        $html = self::compile($this->body_html, $tokens);

        // Ensure DejaVu Sans font and UTF-8 charset for DomPDF
        if ($isPdf) {
            if (!str_contains($html, 'font-family: \'DejaVu Sans\'') && !str_contains($html, 'font-family: DejaVu Sans')) {
                $html = str_replace('</head>', '<style>* { font-family: \'DejaVu Sans\', sans-serif !important; } .rupee { font-family: \'DejaVu Sans\', sans-serif !important; }</style></head>', $html);
            }
        }

        return $html;
    }

    /**
     * Get default baseline HTML template
     */
    public static function getDefaultTemplateHtml(): string
    {
        return <<<'HTML'
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
    }
}
