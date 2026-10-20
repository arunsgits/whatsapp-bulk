<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            if (! Schema::hasColumn('settings', 'auto_ai_enabled')) {
                $table->boolean('auto_ai_enabled')->default(false);
            }
            if (! Schema::hasColumn('settings', 'business_name')) {
                $table->string('business_name')->nullable();
            }
            if (! Schema::hasColumn('settings', 'business_type')) {
                $table->string('business_type')->nullable();
            }
            if (! Schema::hasColumn('settings', 'business_details')) {
                $table->text('business_details')->nullable();
            }
            if (! Schema::hasColumn('settings', 'business_hours')) {
                $table->string('business_hours')->nullable();
            }
            if (! Schema::hasColumn('settings', 'auto_ai_tone')) {
                $table->string('auto_ai_tone')->nullable()->default('friendly');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            foreach (['auto_ai_enabled', 'business_name', 'business_type', 'business_details', 'business_hours', 'auto_ai_tone'] as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
