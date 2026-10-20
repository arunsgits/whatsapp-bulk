<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('settings', 'smtp_cc_email')) {
            try {
                DB::statement('ALTER TABLE settings MODIFY smtp_cc_email TEXT NULL');
            } catch (\Throwable $e) {
                // Graceful fallback
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('settings', 'smtp_cc_email')) {
            try {
                DB::statement('ALTER TABLE settings MODIFY smtp_cc_email VARCHAR(255) NULL');
            } catch (\Throwable $e) {
                // Graceful fallback
            }
        }
    }
};
