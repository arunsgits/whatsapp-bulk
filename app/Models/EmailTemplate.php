<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $table = 'email_templates';

    protected $fillable = [
        'name',
        'slug',
        'type',
        'subject',
        'body_html',
        'placeholders',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'placeholders' => 'array',
    ];

    /**
     * Replace placeholders in template text with provided key-value data.
     * Supports both {{key}}, {{ key }}, and {key}.
     *
     * @param string $content
     * @param array $data
     * @return string
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
     * Render email subject with given variable data
     *
     * @param array $data
     * @return string
     */
    public function renderSubject(array $data): string
    {
        return self::compile($this->subject ?? '', $data);
    }

    /**
     * Render email HTML body with given variable data
     *
     * @param array $data
     * @return string
     */
    public function renderBody(array $data): string
    {
        return self::compile($this->body_html ?? '', $data);
    }

    /**
     * Returns standard system placeholders with descriptions and sample values
     *
     * @return array
     */
    public static function getAvailablePlaceholders(): array
    {
        return [
            [
                'key' => 'user_name',
                'description' => 'User / subscriber full name',
                'sample' => 'John Doe'
            ],
            [
                'key' => 'user_email',
                'description' => 'Subscriber email address',
                'sample' => 'john@example.com'
            ],
            [
                'key' => 'app_name',
                'description' => 'Platform or business name',
                'sample' => 'WhatsApp Bulk'
            ],
            [
                'key' => 'plan_name',
                'description' => 'Subscription package name',
                'sample' => 'Premium Business'
            ],
            [
                'key' => 'action_type',
                'description' => 'Action performed (e.g. Activated, Upgraded, Renewed, Allocated)',
                'sample' => 'Activated'
            ],
            [
                'key' => 'headline',
                'description' => 'Main title / banner headline',
                'sample' => 'Plan Activated Successfully'
            ],
            [
                'key' => 'period',
                'description' => 'Plan duration / billing cycle',
                'sample' => 'Per Month'
            ],
            [
                'key' => 'days',
                'description' => 'Number of validity days',
                'sample' => '30'
            ],
            [
                'key' => 'start_date',
                'description' => 'Subscription start date',
                'sample' => '08 Oct 2026'
            ],
            [
                'key' => 'end_date',
                'description' => 'Subscription expiry date',
                'sample' => '07 Nov 2026'
            ],
            [
                'key' => 'messages',
                'description' => 'Allowed message quota count',
                'sample' => '50,000'
            ],
            [
                'key' => 'contacts',
                'description' => 'Allowed contacts quota count',
                'sample' => '10,000'
            ],
            [
                'key' => 'templates',
                'description' => 'Allowed template quota count',
                'sample' => '25'
            ],
            [
                'key' => 'base_amount',
                'description' => 'Base plan price excluding GST',
                'sample' => '1,499.00'
            ],
            [
                'key' => 'gst_amount',
                'description' => 'Calculated GST tax amount',
                'sample' => '269.82'
            ],
            [
                'key' => 'gst_rate',
                'description' => 'GST percentage rate',
                'sample' => '18'
            ],
            [
                'key' => 'total_amount',
                'description' => 'Final paid amount',
                'sample' => '1,768.82'
            ],
            [
                'key' => 'currency',
                'description' => 'Currency symbol',
                'sample' => '₹'
            ],
            [
                'key' => 'receipt_id',
                'description' => 'Receipt or order reference ID',
                'sample' => 'rcpt_982347'
            ],
            [
                'key' => 'payment_method',
                'description' => 'Payment method used',
                'sample' => 'Razorpay'
            ],
            [
                'key' => 'dashboard_url',
                'description' => 'URL to the user dashboard',
                'sample' => 'https://zapreach.com/dashboard'
            ],
            [
                'key' => 'year',
                'description' => 'Current calendar year',
                'sample' => '2026'
            ],
            [
                'key' => 'cc_notice',
                'description' => 'Admin CC notification badge text',
                'sample' => 'Admin Notice: A carbon copy (CC) of this email was sent to admin@example.com'
            ],
        ];
    }
}
