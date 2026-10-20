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
        if (Schema::hasTable('packages')) {
            Schema::table('packages', function (Blueprint $table) {
                if (!Schema::hasColumn('packages', 'renewal_price')) {
                    $table->string('renewal_price')->nullable()->after('price');
                }
            });
        }

        if (Schema::hasTable('wallet_orders')) {
            Schema::table('wallet_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('wallet_orders', 'payment_link_id')) {
                    $table->string('payment_link_id')->nullable();
                }
                if (!Schema::hasColumn('wallet_orders', 'payment_link_url')) {
                    $table->text('payment_link_url')->nullable();
                }
                if (!Schema::hasColumn('wallet_orders', 'payment_type')) {
                    $table->string('payment_type', 50)->nullable()->default('wallet_recharge');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('packages')) {
            Schema::table('packages', function (Blueprint $table) {
                if (Schema::hasColumn('packages', 'renewal_price')) {
                    $table->dropColumn('renewal_price');
                }
            });
        }

        if (Schema::hasTable('wallet_orders')) {
            Schema::table('wallet_orders', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('wallet_orders', 'payment_link_id')) $cols[] = 'payment_link_id';
                if (Schema::hasColumn('wallet_orders', 'payment_link_url')) $cols[] = 'payment_link_url';
                if (Schema::hasColumn('wallet_orders', 'payment_type')) $cols[] = 'payment_type';
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
