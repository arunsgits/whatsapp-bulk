<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Package;
use App\Models\ActivePackage;
use App\Models\InvoiceTemplate;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminInvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService)
    {
    }

    /**
     * List all Invoices (Admin)
     * GET /api/v1/admin/invoices
     */
    public function index(Request $request)
    {
        try {
            $filters = [
                'page' => $request->input('page', 1),
                'limit' => $request->input('limit', 20),
                'user_id' => $request->input('user_id', $request->input('userId')),
                'status' => $request->input('status'),
                'payment_type' => $request->input('payment_type', $request->input('type')),
                'search' => $request->input('search', $request->input('q')),
                'date_from' => $request->input('date_from', $request->input('startDate')),
                'date_to' => $request->input('date_to', $request->input('endDate')),
            ];

            $result = $this->invoiceService->listInvoices($filters);

            return response()->json([
                'success' => true,
                'status' => true,
                'data' => $result['invoices'],
                'pagination' => $result['pagination'],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Error retrieving invoices: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get Single Invoice Details (JSON)
     * GET /api/v1/admin/invoices/{invoiceId}
     */
    public function show(Request $request, $invoiceId)
    {
        try {
            $invoice = $this->invoiceService->getInvoiceData($invoiceId);
            return response()->json([
                'success' => true,
                'status' => true,
                'data' => $invoice
            ], 200);
        } catch (\Throwable $e) {
            $invoice = $this->invoiceService->fallbackInvoiceData($invoiceId);
            return response()->json([
                'success' => true,
                'status' => true,
                'data' => $invoice
            ], 200);
        }
    }

    /**
     * Render HTML invoice using active dynamic template or fallback to Blade
     */
    protected function renderInvoiceHtml(array $invoice, bool $isPdf = false): string
    {
        try {
            $template = InvoiceTemplate::getActiveTemplate();
            if ($template) {
                return $template->renderInvoiceHtml($invoice, $isPdf);
            }
        } catch (\Throwable $e) {
            // Graceful fallback to Blade on error
        }

        return view('invoices.show', ['invoice' => $invoice, 'isPdf' => $isPdf])->render();
    }

    /**
     * Generate DomPDF object using active dynamic template or fallback to Blade
     */
    protected function generateInvoicePdf(array $invoice)
    {
        try {
            $template = InvoiceTemplate::getActiveTemplate();
            if ($template) {
                $html = $template->renderInvoiceHtml($invoice, true);
                return Pdf::loadHTML($html)->setPaper('a4', 'portrait');
            }
        } catch (\Throwable $e) {
            // Graceful fallback to Blade on error
        }

        return Pdf::loadView('invoices.show', [
            'invoice' => $invoice,
            'isPdf' => true,
        ])->setPaper('a4', 'portrait');
    }

    /**
     * Render Printable HTML Invoice (Admin View)
     * GET /api/v1/admin/invoices/{invoiceId}/view or /html
     */
    public function viewHtml(Request $request, $invoiceId)
    {
        try {
            $invoice = $this->invoiceService->getInvoiceData($invoiceId);
        } catch (\Throwable $e) {
            $invoice = $this->invoiceService->fallbackInvoiceData($invoiceId);
        }

        $html = $this->renderInvoiceHtml($invoice, false);
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Download Invoice Document
     * GET /api/v1/admin/invoices/{invoiceId}/download
     */
    public function download(Request $request, $invoiceId)
    {
        try {
            $invoice = $this->invoiceService->getInvoiceData($invoiceId);
        } catch (\Throwable $e) {
            $invoice = $this->invoiceService->fallbackInvoiceData($invoiceId);
        }

        $invNumber = $invoice['invoice_number'] ?? $invoiceId;

        if ($request->query('format') === 'html') {
            $html = $this->renderInvoiceHtml($invoice, false);
            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="Invoice-' . $invNumber . '.html"',
            ]);
        }

        try {
            $pdf = $this->generateInvoicePdf($invoice);
            return $pdf->download('Invoice-' . $invNumber . '.pdf');
        } catch (\Throwable $e) {
            $html = $this->renderInvoiceHtml($invoice, false);
            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="Invoice-' . $invNumber . '.html"',
            ]);
        }
    }

    /**
     * List Invoices for Specific User
     * GET /api/v1/admin/users/{userId}/invoices
     */
    public function userInvoices(Request $request, $userId)
    {
        $rawId = str_replace('usr_', '', $userId);
        $request->merge(['user_id' => $rawId]);
        return $this->index($request);
    }

    /**
     * Generate Invoice On-Demand for User Plan / Active Package
     * POST /api/v1/admin/users/{userId}/generate-invoice
     */
    public function generateForUser(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'packageId' => 'nullable',
            'package_id' => 'nullable',
            'price' => 'nullable|numeric',
            'base_amount' => 'nullable|numeric',
            'gst_percentage' => 'nullable|numeric',
            'payment_method' => 'nullable|string',
            'payment_status' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        try {
            $rawId = str_replace('usr_', '', $userId);
            $user = User::find($rawId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'status' => false,
                    'message' => 'User not found'
                ], 404);
            }

            $activePkg = ActivePackage::where('userId', $user->id)->latest('id')->first();
            $packageId = $request->input('packageId', $request->input('package_id', $activePkg?->packageId));
            $package = $packageId ? Package::find($packageId) : null;

            if (!$package && $activePkg) {
                $package = $activePkg->package;
            }

            if (!$package) {
                return response()->json([
                    'success' => false,
                    'status' => false,
                    'message' => 'No subscription package found for this user to generate invoice.'
                ], 422);
            }

            $options = [
                'base_amount' => $request->input('base_amount', $request->input('price', $activePkg?->custom_price ?? $package->price)),
                'gst_percentage' => $request->input('gst_percentage'),
                'payment_method' => $request->input('payment_method', 'admin'),
                'payment_status' => $request->input('payment_status', 'paid'),
                'notes' => $request->input('notes'),
                'assigned_by' => $request->user()?->name ?: 'Super Admin',
                'type' => $request->input('type', 'custom_plan'),
            ];

            $result = $this->invoiceService->createPlanInvoice($user, $package, $activePkg, $options);

            return response()->json([
                'success' => true,
                'status' => true,
                'message' => 'Invoice generated successfully',
                'data' => $result['invoice'],
                'order' => $result['order'],
                'transaction' => $result['transaction'],
                'wallet' => $result['wallet'],
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Error generating invoice: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send Invoice Payment Reminder Email to User
     * POST /api/v1/admin/invoices/{invoiceId}/send-reminder
     */
    public function sendReminder(Request $request, $invoiceId)
    {
        try {
            $invoice = null;
            try {
                $invoice = $this->invoiceService->getInvoiceData($invoiceId);
            } catch (\Throwable $invEx) {
                $invoice = $this->invoiceService->fallbackInvoiceData($invoiceId);
            }

            $userId = $invoice['customer']['db_id'] ?? null;
            if (!$userId && !empty($invoice['customer']['user_id'])) {
                $userId = str_replace('usr_', '', $invoice['customer']['user_id']);
            }

            $user = $userId ? User::find($userId) : null;
            if (!$user && !empty($invoice['customer']['email'])) {
                $user = User::where('email', $invoice['customer']['email'])->first();
            }

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'status' => false,
                    'message' => 'Associated customer / user for this invoice could not be found.'
                ], 404);
            }

            $options = array_merge([
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoice['invoice_number'] ?? $invoiceId,
                'amount' => $invoice['pricing']['total_amount'] ?? ($invoice['total_amount'] ?? null),
                'due_date' => $invoice['dates']['due_date_formatted'] ?? null,
                'description' => $invoice['items'][0]['description'] ?? ($invoice['items'][0]['name'] ?? 'Invoice payment'),
                'payment_link' => $request->input('payment_link', $invoice['payment']['payment_link_url'] ?? null),
                'message' => $request->input('message', $request->input('notes')),
            ], $request->all());

            $reminderService = app(\App\Services\ReminderMailService::class);
            $result = $reminderService->sendPaymentReminder($user, $options);

            return response()->json($result, ($result['success'] ?? false) ? 200 : 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => false,
                'message' => 'Error sending invoice reminder: ' . $e->getMessage()
            ], 500);
        }
    }
}
