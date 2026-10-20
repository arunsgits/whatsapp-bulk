<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\InvoiceTemplate;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminInvoiceTemplateController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService)
    {
    }

    /**
     * List all invoice templates
     * GET /api/v1/admin/invoice-templates
     */
    public function index(Request $request)
    {
        try {
            $query = InvoiceTemplate::query();

            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('search')) {
                $term = trim($request->search);
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('slug', 'like', "%{$term}%")
                      ->orWhere('subject_title', 'like', "%{$term}%");
                });
            }

            $templates = $query->orderBy('id', 'asc')->get();

            return response()->json([
                'success' => true,
                'status' => 'success',
                'data' => $templates,
                'total' => count($templates),
            ]);
        } catch (\Throwable $e) {
            Log::error("AdminInvoiceTemplateController::index error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to fetch invoice templates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available dynamic placeholders for invoice templates
     * GET /api/v1/admin/invoice-templates/placeholders
     */
    public function getPlaceholders()
    {
        $placeholders = [
            ['key' => 'invoice_number', 'description' => 'Invoice sequential number (e.g. INV-2026-00001)'],
            ['key' => 'invoice_date', 'description' => 'Invoice issue date (e.g. 08 Oct, 2026)'],
            ['key' => 'receipt', 'description' => 'Payment receipt or transaction ID'],
            ['key' => 'payment_method', 'description' => 'Payment mode (e.g. Razorpay, Online, Admin)'],
            ['key' => 'payment_status', 'description' => 'Payment status badge (e.g. PAID)'],
            ['key' => 'subject_title', 'description' => 'Invoice banner heading (e.g. TAX INVOICE)'],
            ['key' => 'package_name', 'description' => 'Purchased subscription package name'],
            ['key' => 'seller_company', 'description' => 'Legal company name of seller/admin'],
            ['key' => 'seller_gstin', 'description' => 'GSTIN of seller'],
            ['key' => 'seller_address', 'description' => 'Physical office address of seller'],
            ['key' => 'seller_email', 'description' => 'Billing / support email of seller'],
            ['key' => 'seller_state', 'description' => 'State where seller is registered'],
            ['key' => 'customer_name', 'description' => 'Subscriber full name'],
            ['key' => 'customer_company', 'description' => 'Subscriber company / organization name'],
            ['key' => 'customer_email', 'description' => 'Subscriber email address'],
            ['key' => 'customer_phone', 'description' => 'Subscriber phone number'],
            ['key' => 'customer_address', 'description' => 'Subscriber address'],
            ['key' => 'customer_gstin', 'description' => 'Subscriber GSTIN (if registered)'],
            ['key' => 'customer_gstin_block', 'description' => 'Styled HTML tag for customer GSTIN'],
            ['key' => 'items_table_rows', 'description' => 'Rendered HTML rows for items table (shows only package name)'],
            ['key' => 'taxable_base', 'description' => 'Taxable base amount formatted (e.g. 1,499.00)'],
            ['key' => 'gst_rate', 'description' => 'Unified GST percentage rate (e.g. 18)'],
            ['key' => 'gst_amount', 'description' => 'Unified GST total amount formatted (e.g. 269.82)'],
            ['key' => 'grand_total', 'description' => 'Final grand total amount formatted (e.g. 1,768.82)'],
            ['key' => 'amount_in_words', 'description' => 'Grand total spelled out in English words'],
            ['key' => 'notes', 'description' => 'Transaction remarks or special instructions'],
            ['key' => 'notes_block', 'description' => 'Formatted HTML block for transaction remarks'],
            ['key' => 'terms_text', 'description' => 'Raw terms and conditions text'],
            ['key' => 'terms_html', 'description' => 'Formatted HTML lines for terms and conditions'],
            ['key' => 'accent_color', 'description' => 'Primary theme accent color hex'],
            ['key' => 'logo_img_tag', 'description' => 'Rendered HTML image tag for invoice logo'],
        ];

        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => $placeholders,
        ]);
    }

    /**
     * Get single invoice template by ID or slug
     * GET /api/v1/admin/invoice-templates/{id}
     */
    public function show($id)
    {
        try {
            $template = InvoiceTemplate::where('id', $id)->orWhere('slug', $id)->first();
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Invoice template not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'status' => 'success',
                'data' => $template,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to retrieve invoice template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new invoice template
     * POST /api/v1/admin/invoice-templates
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:invoice_templates,slug',
            'subject_title' => 'nullable|string|max:255',
            'header_logo' => 'nullable|string|max:500',
            'accent_color' => 'nullable|string|max:30',
            'body_html' => 'required|string',
            'terms_text' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $slug = Str::slug($request->slug, '-');
            if (InvoiceTemplate::where('slug', $slug)->exists()) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => "An invoice template with slug '{$slug}' already exists.",
                ], 422);
            }

            $isActive = $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : false;

            // If new template is activated, deactivate others
            if ($isActive) {
                InvoiceTemplate::where('is_active', true)->update(['is_active' => false]);
            }

            $template = InvoiceTemplate::create([
                'name' => trim($request->name),
                'slug' => $slug,
                'subject_title' => $request->subject_title ?: 'TAX INVOICE',
                'header_logo' => $request->header_logo,
                'accent_color' => $request->accent_color ?: '#2563eb',
                'body_html' => $request->body_html,
                'terms_text' => $request->terms_text,
                'is_active' => $isActive,
            ]);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Invoice template created successfully',
                'data' => $template,
            ], 201);
        } catch (\Throwable $e) {
            Log::error("AdminInvoiceTemplateController::store error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to create invoice template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an invoice template
     * PUT/POST /api/v1/admin/invoice-templates/{id}
     */
    public function update(Request $request, $id)
    {
        $template = InvoiceTemplate::where('id', $id)->orWhere('slug', $id)->first();
        if (!$template) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Invoice template not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:100|unique:invoice_templates,slug,' . $template->id,
            'subject_title' => 'nullable|string|max:255',
            'header_logo' => 'nullable|string|max:500',
            'accent_color' => 'nullable|string|max:30',
            'body_html' => 'sometimes|required|string',
            'terms_text' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = [];
            if ($request->has('name')) $data['name'] = trim($request->name);
            if ($request->has('slug')) $data['slug'] = Str::slug($request->slug, '-');
            if ($request->has('subject_title')) $data['subject_title'] = $request->subject_title;
            if ($request->has('header_logo')) $data['header_logo'] = $request->header_logo;
            if ($request->has('accent_color')) $data['accent_color'] = $request->accent_color;
            if ($request->has('body_html')) $data['body_html'] = $request->body_html;
            if ($request->has('terms_text')) $data['terms_text'] = $request->terms_text;

            if ($request->has('is_active')) {
                $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
                $data['is_active'] = $isActive;
                if ($isActive) {
                    InvoiceTemplate::where('id', '!=', $template->id)->update(['is_active' => false]);
                }
            }

            $template->update($data);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Invoice template updated successfully',
                'data' => $template->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error("AdminInvoiceTemplateController::update error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to update invoice template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle or activate an invoice template
     * POST /api/v1/admin/invoice-templates/{id}/toggle-status
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $template = InvoiceTemplate::where('id', $id)->orWhere('slug', $id)->first();
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Invoice template not found',
                ], 404);
            }

            $newStatus = $request->has('is_active')
                ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                : !$template->is_active;

            if ($newStatus) {
                // Deactivate all other templates
                InvoiceTemplate::where('id', '!=', $template->id)->update(['is_active' => false]);
            }

            $template->update(['is_active' => $newStatus]);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => $newStatus ? 'Invoice template activated' : 'Invoice template deactivated',
                'data' => $template->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to toggle invoice template status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload an image logo for the invoice template
     * POST /api/v1/admin/invoice-templates/upload-logo
     */
    public function uploadLogo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:3072',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Invalid image file',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $file = $request->file('logo');
            $uploadDir = public_path('uploads/invoice-templates');

            if (!File::exists($uploadDir)) {
                File::makeDirectory($uploadDir, 0755, true, true);
            }

            $filename = 'logo_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $file->move($uploadDir, $filename);

            $relativePath = 'uploads/invoice-templates/' . $filename;
            $fullUrl = asset($relativePath);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Logo uploaded successfully',
                'data' => [
                    'filename' => $filename,
                    'path' => $relativePath,
                    'url' => $fullUrl,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error("AdminInvoiceTemplateController::uploadLogo error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to upload logo: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview compiled invoice template HTML or PDF
     * POST /api/v1/admin/invoice-templates/{id}/preview
     */
    public function preview(Request $request, $id)
    {
        try {
            $template = InvoiceTemplate::where('id', $id)->orWhere('slug', $id)->first();
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Invoice template not found',
                ], 404);
            }

            // Optional invoice identifier or synthesize realistic preview data
            $invoiceId = $request->input('invoice_id');
            if ($invoiceId) {
                try {
                    $invoice = $this->invoiceService->getInvoiceData($invoiceId);
                } catch (\Throwable $e) {
                    $invoice = $this->invoiceService->fallbackInvoiceData($invoiceId);
                }
            } else {
                $invoice = $this->invoiceService->fallbackInvoiceData('PREVIEW-SAMPLE');
            }

            $format = $request->input('format', 'html');
            $compiledHtml = $template->renderInvoiceHtml($invoice, $format === 'pdf');

            if ($format === 'pdf') {
                $pdf = Pdf::loadHTML($compiledHtml)->setPaper('a4', 'portrait');
                return $pdf->stream('Invoice-Preview.pdf');
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'status' => 'success',
                    'data' => [
                        'template_id' => $template->id,
                        'name' => $template->name,
                        'html' => $compiledHtml,
                    ],
                ]);
            }

            return response($compiledHtml, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        } catch (\Throwable $e) {
            Log::error("AdminInvoiceTemplateController::preview error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to preview invoice: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset invoice template body to default baseline layout
     * POST /api/v1/admin/invoice-templates/{id}/reset-default
     */
    public function resetDefault($id)
    {
        try {
            $template = InvoiceTemplate::where('id', $id)->orWhere('slug', $id)->first();
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Invoice template not found',
                ], 404);
            }

            $defaultHtml = InvoiceTemplate::getDefaultTemplateHtml();
            $template->update([
                'body_html' => $defaultHtml,
                'subject_title' => 'TAX INVOICE',
                'accent_color' => '#2563eb',
            ]);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Template reset to default layout successfully',
                'data' => $template->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to reset invoice template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an invoice template
     * DELETE /api/v1/admin/invoice-templates/{id}
     */
    public function destroy($id)
    {
        try {
            $template = InvoiceTemplate::where('id', $id)->orWhere('slug', $id)->first();
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Invoice template not found',
                ], 404);
            }

            $count = InvoiceTemplate::count();
            if ($count <= 1) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Cannot delete the only remaining invoice template.',
                ], 422);
            }

            $template->delete();

            // Ensure at least one is active if available
            if (!InvoiceTemplate::where('is_active', true)->exists()) {
                InvoiceTemplate::latest('id')->first()?->update(['is_active' => true]);
            }

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Invoice template deleted successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to delete invoice template: ' . $e->getMessage(),
            ], 500);
        }
    }
}
