<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminEmailTemplateController extends Controller
{
    /**
     * List all email templates
     * GET /api/v1/admin/email-templates
     */
    public function index(Request $request)
    {
        try {
            $query = EmailTemplate::query();

            if ($request->filled('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            if ($request->filled('search')) {
                $term = trim($request->search);
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('slug', 'like', "%{$term}%")
                      ->orWhere('subject', 'like', "%{$term}%");
                });
            }

            $perPage = (int) $request->input('per_page', 20);
            if ($perPage > 0 && $request->has('page')) {
                $templates = $query->orderBy('id', 'asc')->paginate($perPage);
            } else {
                $templates = $query->orderBy('id', 'asc')->get();
            }

            return response()->json([
                'success' => true,
                'status' => 'success',
                'data' => $templates,
                'total' => is_countable($templates) ? count($templates) : $templates->total(),
            ]);
        } catch (\Throwable $e) {
            Log::error("AdminEmailTemplateController::index error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to fetch email templates: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available dynamic placeholders
     * GET /api/v1/admin/email-templates/placeholders
     */
    public function getPlaceholders()
    {
        return response()->json([
            'success' => true,
            'status' => 'success',
            'data' => EmailTemplate::getAvailablePlaceholders(),
        ]);
    }

    /**
     * Get single email template by ID or slug
     * GET /api/v1/admin/email-templates/{id}
     */
    public function show($id)
    {
        try {
            $template = EmailTemplate::where('id', $id)->orWhere('slug', $id)->first();
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Email template not found',
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
                'message' => 'Failed to retrieve email template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new email template
     * POST /api/v1/admin/email-templates
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:email_templates,slug',
            'subject' => 'required|string|max:255',
            'body_html' => 'required|string',
            'type' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'placeholders' => 'nullable',
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
            $slug = Str::slug($request->slug, '_');
            // Re-check uniqueness after normalization
            if (EmailTemplate::where('slug', $slug)->exists()) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => "An email template with slug '{$slug}' already exists.",
                ], 422);
            }

            $template = EmailTemplate::create([
                'name' => trim($request->name),
                'slug' => $slug,
                'type' => $request->type ?: 'plan',
                'subject' => trim($request->subject),
                'body_html' => $request->body_html,
                'is_active' => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true,
                'placeholders' => $request->placeholders ?: EmailTemplate::getAvailablePlaceholders(),
            ]);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Email template created successfully',
                'data' => $template,
            ], 201);
        } catch (\Throwable $e) {
            Log::error("AdminEmailTemplateController::store error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to create email template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an email template
     * PUT/POST /api/v1/admin/email-templates/{id}
     */
    public function update(Request $request, $id)
    {
        $template = EmailTemplate::where('id', $id)->orWhere('slug', $id)->first();
        if (!$template) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Email template not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'slug' => 'sometimes|required|string|max:100|unique:email_templates,slug,' . $template->id,
            'subject' => 'sometimes|required|string|max:255',
            'body_html' => 'sometimes|required|string',
            'type' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'placeholders' => 'nullable',
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
            if ($request->has('slug')) $data['slug'] = Str::slug($request->slug, '_');
            if ($request->has('type')) $data['type'] = $request->type;
            if ($request->has('subject')) $data['subject'] = trim($request->subject);
            if ($request->has('body_html')) $data['body_html'] = $request->body_html;
            if ($request->has('is_active')) $data['is_active'] = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            if ($request->has('placeholders')) $data['placeholders'] = $request->placeholders;

            $template->update($data);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Email template updated successfully',
                'data' => $template->fresh(),
            ]);
        } catch (\Throwable $e) {
            Log::error("AdminEmailTemplateController::update error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to update email template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an email template
     * DELETE /api/v1/admin/email-templates/{id}
     */
    public function destroy($id)
    {
        try {
            $template = EmailTemplate::where('id', $id)->orWhere('slug', $id)->first();
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Email template not found',
                ], 404);
            }

            $template->delete();

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Email template deleted successfully',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to delete email template: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle active status of an email template
     * POST /api/v1/admin/email-templates/{id}/toggle-status
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $template = EmailTemplate::where('id', $id)->orWhere('slug', $id)->first();
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Email template not found',
                ], 404);
            }

            if ($request->has('is_active')) {
                $template->is_active = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            } else {
                $template->is_active = !$template->is_active;
            }

            $template->save();

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Email template status updated to ' . ($template->is_active ? 'active' : 'inactive'),
                'data' => [
                    'id' => $template->id,
                    'slug' => $template->slug,
                    'is_active' => (bool) $template->is_active,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to toggle status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload Image for Email Template
     * POST /api/v1/admin/email-templates/upload-image
     */
    public function uploadImage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'image' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
            'file' => 'nullable|file|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('image') ?: $request->file('file');
        if (!$file || !$file->isValid()) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'No valid image file was uploaded. Please upload a JPEG, PNG, GIF, WEBP, or SVG file up to 10MB.',
            ], 422);
        }

        try {
            $destPath = public_path('uploads/email-templates');
            if (!file_exists($destPath)) {
                @mkdir($destPath, 0755, true);
            }

            $ext = strtolower($file->getClientOriginalExtension() ?: 'png');
            $filename = 'img_' . time() . '_' . Str::random(10) . '.' . $ext;
            $file->move($destPath, $filename);

            $relativeUrl = 'uploads/email-templates/' . $filename;
            $fullUrl = url($relativeUrl);

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => 'Image uploaded successfully for email template',
                'data' => [
                    'url' => $fullUrl,
                    'relative_url' => '/' . $relativeUrl,
                    'filename' => $filename,
                    'html_snippet' => '<img src="' . $fullUrl . '" alt="Image" style="max-width: 100%; height: auto; border: 0;" />',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error("AdminEmailTemplateController::uploadImage error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to upload image: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview Rendered HTML of an Email Template
     * POST /api/v1/admin/email-templates/{id}/preview or /api/v1/admin/email-templates/preview
     */
    public function preview(Request $request, $id = null)
    {
        try {
            $subject = '';
            $bodyHtml = '';

            if ($id) {
                $template = EmailTemplate::where('id', $id)->orWhere('slug', $id)->first();
                if (!$template) {
                    return response()->json([
                        'success' => false,
                        'status' => 'error',
                        'message' => 'Email template not found',
                    ], 404);
                }
                $subject = $template->subject;
                $bodyHtml = $template->body_html;
            }

            // Allow request overrides for live preview before saving
            if ($request->filled('subject')) {
                $subject = $request->subject;
            }
            if ($request->filled('body_html')) {
                $bodyHtml = $request->body_html;
            }

            if (empty($bodyHtml)) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'No body_html content provided for preview',
                ], 422);
            }

            // Sample dummy data
            $sampleData = [
                'user_name' => 'John Doe',
                'userName' => 'John Doe',
                'user_email' => 'john.doe@example.com',
                'userEmail' => 'john.doe@example.com',
                'app_name' => config('app.name', 'WhatsApp Bulk'),
                'appName' => config('app.name', 'WhatsApp Bulk'),
                'plan_name' => 'Premium Business',
                'packageName' => 'Premium Business',
                'action_type' => 'Activated',
                'actionType' => 'Activated',
                'headline' => 'Subscription Plan Activated',
                'period' => 'Per Month',
                'period_label' => 'Per Month',
                'periodLabel' => 'Per Month',
                'days' => '30',
                'start_date' => now()->format('d M Y'),
                'startDateFormatted' => now()->format('d M Y'),
                'end_date' => now()->addDays(30)->format('d M Y'),
                'endDateFormatted' => now()->addDays(30)->format('d M Y'),
                'expiry_date' => now()->addDays(30)->format('d M Y'),
                'messages' => '50,000',
                'msgCount' => '50,000',
                'contacts' => '10,000',
                'contactCount' => '10,000',
                'templates' => '25',
                'templateCount' => '25',
                'base_amount' => '1,499.00',
                'baseAmountFormatted' => '1,499.00',
                'gst_amount' => '269.82',
                'gstAmountFormatted' => '269.82',
                'gst_rate' => '18',
                'gstRateDisplay' => '18',
                'total_amount' => '1,768.82',
                'totalAmountFormatted' => '1,768.82',
                'amount' => '1,768.82',
                'currency' => '₹',
                'receipt_id' => 'RCPT_849204',
                'receiptId' => 'RCPT_849204',
                'order_id' => 'order_9837264',
                'payment_method' => 'Razorpay',
                'paymentMethod' => 'Razorpay',
                'dashboard_url' => rtrim(config('app.url'), '/') . '/dashboard',
                'dashboardUrl' => rtrim(config('app.url'), '/') . '/dashboard',
                'year' => date('Y'),
                'cc_notice' => 'Admin Notice: A carbon copy (CC) of this email was sent to admin@example.com',
            ];

            // Merge any custom test variables passed by admin
            if ($request->isJson() && is_array($request->input('custom_data'))) {
                $sampleData = array_merge($sampleData, $request->input('custom_data'));
            }

            $renderedSubject = EmailTemplate::compile($subject, $sampleData);
            $renderedHtml = EmailTemplate::compile($bodyHtml, $sampleData);

            if ($request->query('format') === 'html' || $request->input('format') === 'html') {
                return response($renderedHtml, 200)->header('Content-Type', 'text/html; charset=UTF-8');
            }

            return response()->json([
                'success' => true,
                'status' => 'success',
                'data' => [
                    'rendered_subject' => $renderedSubject,
                    'rendered_html' => $renderedHtml,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to render preview: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send Test Email with Template
     * POST /api/v1/admin/email-templates/{id}/test-send
     */
    public function testSend(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'recipient_email' => 'required|email',
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
            $template = EmailTemplate::where('id', $id)->orWhere('slug', $id)->first();
            if (!$template) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'Email template not found',
                ], 404);
            }

            $settings = Settings::whereNull('userId')->first() ?: Settings::first();
            if (!$settings || empty($settings->smtp_host) || empty($settings->smtp_user)) {
                return response()->json([
                    'success' => false,
                    'status' => 'error',
                    'message' => 'SMTP settings are not configured in system settings.',
                ], 400);
            }

            // Configure SMTP
            $port = (int) ($settings->smtp_port ?? 587);
            $encryption = ($port === 465) ? 'ssl' : (($port === 587) ? 'tls' : null);
            $appName = $settings->business_name ?: config('app.name', 'WhatsApp Bulk');

            Config::set('mail.default', 'smtp');
            Config::set('mail.mailers.smtp.transport', 'smtp');
            Config::set('mail.mailers.smtp.host', (string) $settings->smtp_host);
            Config::set('mail.mailers.smtp.port', $port);
            Config::set('mail.mailers.smtp.encryption', $encryption);
            Config::set('mail.mailers.smtp.username', (string) $settings->smtp_user);
            Config::set('mail.mailers.smtp.password', (string) ($settings->smtp_password ?? ''));
            Config::set('mail.from.address', (string) $settings->smtp_user);
            Config::set('mail.from.name', $appName);

            $sampleData = [
                'user_name' => 'Test Recipient',
                'userName' => 'Test Recipient',
                'user_email' => $request->recipient_email,
                'userEmail' => $request->recipient_email,
                'app_name' => $appName,
                'appName' => $appName,
                'plan_name' => 'Premium Business (Test)',
                'packageName' => 'Premium Business (Test)',
                'action_type' => 'Tested',
                'actionType' => 'Tested',
                'headline' => 'Test Template: ' . $template->name,
                'period' => 'Per Month',
                'days' => '30',
                'start_date' => now()->format('d M Y'),
                'end_date' => now()->addDays(30)->format('d M Y'),
                'messages' => '50,000',
                'contacts' => '10,000',
                'templates' => '25',
                'base_amount' => '1,499.00',
                'gst_amount' => '269.82',
                'gst_rate' => '18',
                'total_amount' => '1,768.82',
                'currency' => '₹',
                'receipt_id' => 'TEST_RECEIPT_123',
                'payment_method' => 'Test Send',
                'dashboard_url' => rtrim(config('app.url'), '/') . '/dashboard',
                'year' => date('Y'),
                'cc_notice' => 'This is a test notification generated from Admin Email Templates.',
            ];

            $renderedSubject = '[TEST] ' . EmailTemplate::compile($template->subject, $sampleData);
            $renderedHtml = EmailTemplate::compile($template->body_html, $sampleData);

            $recipient = trim($request->recipient_email);
            Mail::send([], [], function ($message) use ($recipient, $renderedSubject, $renderedHtml, $settings, $appName) {
                $message->to($recipient)
                    ->subject($renderedSubject)
                    ->from($settings->smtp_user, $appName)
                    ->html($renderedHtml);
            });

            return response()->json([
                'success' => true,
                'status' => 'success',
                'message' => "Test email successfully dispatched to {$recipient}",
            ]);
        } catch (\Throwable $e) {
            Log::error("AdminEmailTemplateController::testSend error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'Failed to send test email: ' . $e->getMessage(),
            ], 500);
        }
    }
}
