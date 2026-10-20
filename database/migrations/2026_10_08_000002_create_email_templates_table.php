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
        if (!Schema::hasTable('email_templates')) {
            Schema::create('email_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('type')->default('plan');
                $table->string('subject');
                $table->longText('body_html');
                $table->json('placeholders')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed default templates
            $this->seedDefaultTemplates();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }

    /**
     * Seed initial plan email templates
     */
    private function seedDefaultTemplates(): void
    {
        $placeholders = [
            ['key' => 'user_name', 'description' => 'User / subscriber full name'],
            ['key' => 'user_email', 'description' => 'Subscriber email address'],
            ['key' => 'app_name', 'description' => 'Platform or business name'],
            ['key' => 'plan_name', 'description' => 'Subscription package name'],
            ['key' => 'action_type', 'description' => 'Action performed (Activated, Upgraded, Renewed, Allocated)'],
            ['key' => 'headline', 'description' => 'Main title / banner headline'],
            ['key' => 'period', 'description' => 'Plan duration (Per Month, Per Year, 30 Days)'],
            ['key' => 'days', 'description' => 'Number of validity days'],
            ['key' => 'start_date', 'description' => 'Subscription start date'],
            ['key' => 'end_date', 'description' => 'Subscription expiry date'],
            ['key' => 'messages', 'description' => 'Allowed message quota count'],
            ['key' => 'contacts', 'description' => 'Allowed contacts quota count'],
            ['key' => 'templates', 'description' => 'Allowed template quota count'],
            ['key' => 'base_amount', 'description' => 'Base plan price excluding GST'],
            ['key' => 'gst_amount', 'description' => 'GST tax amount'],
            ['key' => 'gst_rate', 'description' => 'GST percentage (e.g. 18)'],
            ['key' => 'total_amount', 'description' => 'Total payment amount'],
            ['key' => 'currency', 'description' => 'Currency symbol (₹)'],
            ['key' => 'receipt_id', 'description' => 'Receipt or order reference ID'],
            ['key' => 'payment_method', 'description' => 'Payment method used'],
            ['key' => 'dashboard_url', 'description' => 'URL to the user dashboard'],
            ['key' => 'year', 'description' => 'Current year']
        ];

        $placeholdersJson = json_encode($placeholders);

        $defaultHtml = <<<'HTML'
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{headline}} - {{app_name}}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1e293b;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f1f5f9; padding: 30px 15px;">
        <tr>
            <td align="center">
                <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0;">
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%); padding: 30px 35px; text-align: left;">
                            <div style="font-size: 20px; font-weight: 700; color: #ffffff; letter-spacing: -0.5px;">
                                {{app_name}}
                            </div>
                            <div style="font-size: 13px; color: #bae6fd; margin-top: 4px;">
                                Subscription &amp; Plan Confirmation
                            </div>
                        </td>
                    </tr>

                    <!-- Status Banner -->
                    <tr>
                        <td style="padding: 25px 35px 15px 35px;">
                            <div style="display: inline-block; padding: 6px 14px; background-color: #dcfce7; color: #15803d; font-size: 13px; font-weight: 600; border-radius: 20px; margin-bottom: 12px;">
                                &#10003; {{action_type}} Successfully
                            </div>
                            <h1 style="margin: 0 0 10px 0; font-size: 22px; font-weight: 700; color: #0f172a;">
                                {{headline}}
                            </h1>
                            <p style="margin: 0; font-size: 14px; color: #475569; line-height: 1.6;">
                                Hello <strong>{{user_name}}</strong>, your plan <strong>{{plan_name}}</strong> has been successfully processed and activated.
                            </p>
                        </td>
                    </tr>

                    <!-- Details Card -->
                    <tr>
                        <td style="padding: 15px 35px;">
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 13px;">
                                <tr>
                                    <td colspan="2" style="padding: 12px 18px; border-bottom: 2px solid #e2e8f0; font-weight: 700; color: #0f172a; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px;">
                                        Plan Summary
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Plan Name</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 600; text-align: right; border-bottom: 1px solid #e2e8f0;">{{plan_name}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Action</td>
                                    <td style="padding: 10px 18px; color: #0284c7; font-weight: 600; text-align: right; border-bottom: 1px solid #e2e8f0;">{{action_type}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Validity / Duration</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 500; text-align: right; border-bottom: 1px solid #e2e8f0;">{{period}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Start Date</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 500; text-align: right; border-bottom: 1px solid #e2e8f0;">{{start_date}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Valid Until</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 600; text-align: right; border-bottom: 1px solid #e2e8f0;">{{end_date}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Total Messages</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 500; text-align: right; border-bottom: 1px solid #e2e8f0;">{{messages}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Total Contacts</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 500; text-align: right; border-bottom: 1px solid #e2e8f0;">{{contacts}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Template Limit</td>
                                    <td style="padding: 10px 18px; color: #0f172a; font-weight: 500; text-align: right; border-bottom: 1px solid #e2e8f0;">{{templates}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">Base Amount</td>
                                    <td style="padding: 10px 18px; color: #0f172a; text-align: right; border-bottom: 1px solid #e2e8f0;">₹{{base_amount}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-bottom: 1px solid #e2e8f0;">GST ({{gst_rate}}%)</td>
                                    <td style="padding: 10px 18px; color: #0f172a; text-align: right; border-bottom: 1px solid #e2e8f0;">₹{{gst_amount}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 12px 18px; color: #0f172a; font-weight: 700; font-size: 14px;">Total Price</td>
                                    <td style="padding: 12px 18px; color: #15803d; font-weight: 700; font-size: 15px; text-align: right;">₹{{total_amount}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-top: 1px solid #e2e8f0;">Receipt / Order #</td>
                                    <td style="padding: 10px 18px; color: #0f172a; text-align: right; border-top: 1px solid #e2e8f0;">{{receipt_id}}</td>
                                </tr>
                                <tr>
                                    <td style="padding: 10px 18px; color: #64748b; border-top: 1px solid #e2e8f0;">Payment Method</td>
                                    <td style="padding: 10px 18px; color: #0f172a; text-align: right; border-top: 1px solid #e2e8f0;">{{payment_method}}</td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- CTA Button -->
                    <tr>
                        <td style="padding: 20px 35px; text-align: center;">
                            <a href="{{dashboard_url}}" style="display: inline-block; padding: 12px 28px; background-color: #0284c7; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; border-radius: 8px;">
                                Open Dashboard &rarr;
                            </a>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8fafc; border-top: 1px solid #e2e8f0; padding: 20px 35px; text-align: center; font-size: 12px; color: #94a3b8;">
                            &copy; {{year}} {{app_name}}. All rights reserved.<br>
                            This is an automated notification. Please do not reply directly to this email.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        $templates = [
            [
                'name' => 'Plan Purchase Notification',
                'slug' => 'plan_purchase',
                'type' => 'plan',
                'subject' => 'Subscription Activated: {{plan_name}} - {{app_name}}',
                'body_html' => $defaultHtml,
                'placeholders' => $placeholdersJson,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Plan Upgrade Notification',
                'slug' => 'plan_upgrade',
                'type' => 'plan',
                'subject' => 'Subscription Upgraded: {{plan_name}} - {{app_name}}',
                'body_html' => $defaultHtml,
                'placeholders' => $placeholdersJson,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Plan Renewal Notification',
                'slug' => 'plan_renew',
                'type' => 'plan',
                'subject' => 'Subscription Renewed: {{plan_name}} - {{app_name}}',
                'body_html' => $defaultHtml,
                'placeholders' => $placeholdersJson,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Custom Plan Allocation Notification',
                'slug' => 'custom_plan',
                'type' => 'plan',
                'subject' => 'Subscription Allocated (Custom Plan): {{plan_name}} - {{app_name}}',
                'body_html' => $defaultHtml,
                'placeholders' => $placeholdersJson,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('email_templates')->insert($templates);
    }
};
