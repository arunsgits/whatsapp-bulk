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
            if (!Schema::hasColumn('settings', 'dashboard_content_status')) {
                $table->tinyInteger('dashboard_content_status')->default(1)->after('dashboard_content_link');
            }
            if (!Schema::hasColumn('settings', 'dashboard_image_status')) {
                $table->tinyInteger('dashboard_image_status')->default(1)->after('dashboard_image_image');
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
                'dashboard_content_status',
                'dashboard_image_status',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
