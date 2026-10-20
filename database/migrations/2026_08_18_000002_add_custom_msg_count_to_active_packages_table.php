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
        if (!Schema::hasColumn('active_packages', 'custom_msg_count')) {
            Schema::table('active_packages', function (Blueprint $table) {
                $table->integer('custom_msg_count')->nullable()->after('usedMsgCount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('active_packages', 'custom_msg_count')) {
            Schema::table('active_packages', function (Blueprint $table) {
                $table->dropColumn('custom_msg_count');
            });
        }
    }
};
