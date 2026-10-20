<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds business contact and address fields to settings table and ensures unrequested extra fields are removed.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Business Contact & Location Details requested by API
            if (! Schema::hasColumn('settings', 'business_address')) {
                $table->text('business_address')->nullable()->after('business_hours');
            }
            if (! Schema::hasColumn('settings', 'website_url')) {
                $table->string('website_url', 500)->nullable()->after('business_address');
            }
            if (! Schema::hasColumn('settings', 'support_phone')) {
                $table->string('support_phone', 100)->nullable()->after('website_url');
            }
            if (! Schema::hasColumn('settings', 'support_email')) {
                $table->string('support_email', 150)->nullable()->after('support_phone');
            }

            // Drop unrequested extra fields if they exist
            $extraColumns = [
                'google_maps_link',
                'delivery_info',
                'payment_methods',
                'return_policy',
                'order_process',
                'business_keywords',
                'faqs',
                'custom_instructions',
                'ai_fallback_message',
                'ai_provider',
                'ai_api_key',
                'ai_model',
            ];

            foreach ($extraColumns as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $columns = [
                'business_address',
                'website_url',
                'support_phone',
                'support_email',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
