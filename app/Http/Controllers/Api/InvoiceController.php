<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InvoiceTemplate;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(protected InvoiceService $invoiceService)
    {
    }

    /**
     * List Invoices for Authenticated User
     * GET /api/v1/invoices
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $filters = [
                'page' => $request->input('page', 1),
                'limit' => $request->input('limit', 20),
                'status' => $request->input('status'),
                'payment_type' => $request->input('payment_type', $request->input('type')),
                'search' => $request->input('search', $request->input('q')),
                'date_from' => $request->input('date_from', $request->input('startDate')),
                'date_to' => $request->input('date_to', $request->input('endDate')),
            ];

            $result = $this->invoiceService->listInvoices($filters, $user);

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
     * GET /api/v1/invoices/{invoiceId}
     */
    public function show(Request $request, $invoiceId)
    {
        try {
            $user = $request->user();
            $invoice = $this->invoiceService->getInvoiceData($invoiceId, $user);

            return response()->json([
                'success' => true,
                'status' => true,
                'data' => $invoice
            ], 200);
        } catch (\Throwable $e) {
            $invoice = $this->invoiceService->fallbackInvoiceData($invoiceId, $request->user());
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
     * View / Print HTML Invoice
     * GET /api/v1/invoices/{invoiceId}/view or /html
     */
    public function viewHtml(Request $request, $invoiceId)
    {
        try {
            $user = $request->user();
            $invoice = $this->invoiceService->getInvoiceData($invoiceId, $user);
        } catch (\Throwable $e) {
            $invoice = $this->invoiceService->fallbackInvoiceData($invoiceId, $request->user());
        }

        $html = $this->renderInvoiceHtml($invoice, false);
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Public View / Print Invoice (By Receipt Token / Order Identifier, No Bearer Token Needed)
     * GET /invoices/{receipt}/view
     */
    public function publicView(Request $request, $receipt)
    {
        try {
            $invoice = $this->invoiceService->getInvoiceData($receipt);
        } catch (\Throwable $e) {
            $invoice = $this->invoiceService->fallbackInvoiceData($receipt);
        }

        $html = $this->renderInvoiceHtml($invoice, false);
        return response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Download Invoice Document (Authenticated or Public)
     * GET /api/v1/invoices/{invoiceId}/download
     */
    public function download(Request $request, $invoiceId)
    {
        try {
            $user = $request->user();
            $invoice = $this->invoiceService->getInvoiceData($invoiceId, $user);
        } catch (\Throwable $e) {
            $invoice = $this->invoiceService->fallbackInvoiceData($invoiceId, $request->user());
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
     * Public Download Invoice Document (No Bearer Token Needed)
     * GET /invoices/{receipt}/download
     */
    public function publicDownload(Request $request, $receipt)
    {
        try {
            $invoice = $this->invoiceService->getInvoiceData($receipt);
        } catch (\Throwable $e) {
            $invoice = $this->invoiceService->fallbackInvoiceData($receipt);
        }

        $invNumber = $invoice['invoice_number'] ?? $receipt;

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
}
