<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (!Schema::hasColumn('settings', 'wallet_min_balance')) {
                $table->decimal('wallet_min_balance', 12, 2)->default(250)->nullable();
            }
            if (!Schema::hasColumn('settings', 'wallet_price_marketing')) {
                $table->decimal('wallet_price_marketing', 8, 4)->default(0.86)->nullable();
            }
            if (!Schema::hasColumn('settings', 'wallet_price_utility')) {
                $table->decimal('wallet_price_utility', 8, 4)->default(0.17)->nullable();
            }
            if (!Schema::hasColumn('settings', 'wallet_price_authentication')) {
                $table->decimal('wallet_price_authentication', 8, 4)->default(0.17)->nullable();
            }
        });

        Schema::table('message_delivery_statuses', function (Blueprint $table) {
            if (!Schema::hasColumn('message_delivery_statuses', 'wallet_charged')) {
                $table->boolean('wallet_charged')->default(false)->after('metadata');
            }
            if (!Schema::hasColumn('message_delivery_statuses', 'wallet_amount')) {
                $table->decimal('wallet_amount', 8, 4)->nullable()->after('wallet_charged');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            foreach (['wallet_min_balance', 'wallet_price_marketing', 'wallet_price_utility', 'wallet_price_authentication'] as $col) {
                if (Schema::hasColumn('settings', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('message_delivery_statuses', function (Blueprint $table) {
            if (Schema::hasColumn('message_delivery_statuses', 'wallet_charged')) {
                $table->dropColumn('wallet_charged');
            }
            if (Schema::hasColumn('message_delivery_statuses', 'wallet_amount')) {
                $table->dropColumn('wallet_amount');
            }
        });
    }
};
