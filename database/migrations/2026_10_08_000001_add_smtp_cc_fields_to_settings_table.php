<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'smtp_cc_email')) {
                $table->string('smtp_cc_email')->nullable()->after('smtp_password');
            }
            if (! Schema::hasColumn('settings', 'smtp_cc_enabled')) {
                $table->boolean('smtp_cc_enabled')->default(false)->after('smtp_cc_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('settings', 'smtp_cc_email')) {
                $columns[] = 'smtp_cc_email';
            }
            if (Schema::hasColumn('settings', 'smtp_cc_enabled')) {
                $columns[] = 'smtp_cc_enabled';
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
