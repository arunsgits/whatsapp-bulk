<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_transactions') && !Schema::hasColumn('wallet_transactions', 'category')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->string('category', 30)->nullable()->after('source')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wallet_transactions') && Schema::hasColumn('wallet_transactions', 'category')) {
            Schema::table('wallet_transactions', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
