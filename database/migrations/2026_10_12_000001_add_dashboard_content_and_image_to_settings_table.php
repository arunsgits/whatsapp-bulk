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
            // Dashboard Content columns
            if (!Schema::hasColumn('settings', 'dashboard_content_title')) {
                $table->string('dashboard_content_title')->nullable();
            }
            if (!Schema::hasColumn('settings', 'dashboard_content_description')) {
                $table->text('dashboard_content_description')->nullable();
            }
            if (!Schema::hasColumn('settings', 'dashboard_content_image')) {
                $table->string('dashboard_content_image')->nullable();
            }
            if (!Schema::hasColumn('settings', 'dashboard_content_link')) {
                $table->string('dashboard_content_link')->nullable();
            }

            // Dashboard Image column (only image needed)
            if (!Schema::hasColumn('settings', 'dashboard_image_image')) {
                $table->string('dashboard_image_image')->nullable();
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
                'dashboard_content_title',
                'dashboard_content_description',
                'dashboard_content_image',
                'dashboard_content_link',
                'dashboard_image_image',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
