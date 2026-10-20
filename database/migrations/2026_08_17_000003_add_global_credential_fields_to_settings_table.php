<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'smtp_host')) {
                $table->string('smtp_host')->nullable();
            }
            if (!Schema::hasColumn('settings', 'smtp_port')) {
                $table->integer('smtp_port')->nullable();
            }
            if (!Schema::hasColumn('settings', 'smtp_user')) {
                $table->string('smtp_user')->nullable();
            }
            if (!Schema::hasColumn('settings', 'smtp_password')) {
                $table->string('smtp_password')->nullable();
            }
            if (!Schema::hasColumn('settings', 'sms_gateway_url')) {
                $table->string('sms_gateway_url')->nullable();
            }
            if (!Schema::hasColumn('settings', 'sms_api_key')) {
                $table->string('sms_api_key')->nullable();
            }
            if (!Schema::hasColumn('settings', 'webhook_secret')) {
                $table->string('webhook_secret')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'smtp_host',
                'smtp_port',
                'smtp_user',
                'smtp_password',
                'sms_gateway_url',
                'sms_api_key',
                'webhook_secret'
            ], fn($col) => Schema::hasColumn('settings', $col));

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
