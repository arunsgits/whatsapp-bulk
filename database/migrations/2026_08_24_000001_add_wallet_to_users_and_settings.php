<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'wallet_enabled')) {
                $table->boolean('wallet_enabled')->default(false)->after('status');
            }
            if (!Schema::hasColumn('users', 'wallet_balance')) {
                $table->decimal('wallet_balance', 12, 2)->default(0)->after('wallet_enabled');
            }
        });

        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'razorpay_key_id')) {
                $table->string('razorpay_key_id')->nullable();
            }
            if (!Schema::hasColumn('settings', 'razorpay_key_secret')) {
                $table->string('razorpay_key_secret')->nullable();
            }
            if (!Schema::hasColumn('settings', 'razorpay_webhook_secret')) {
                $table->string('razorpay_webhook_secret')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'wallet_enabled')) {
                $table->dropColumn('wallet_enabled');
            }
            if (Schema::hasColumn('users', 'wallet_balance')) {
                $table->dropColumn('wallet_balance');
            }
        });

        Schema::table('settings', function (Blueprint $table) {
            foreach (['razorpay_key_id', 'razorpay_key_secret', 'razorpay_webhook_secret'] as $col) {
                if (Schema::hasColumn('settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
