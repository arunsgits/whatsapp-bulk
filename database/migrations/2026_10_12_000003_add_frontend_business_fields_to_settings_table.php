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
            if (!Schema::hasColumn('settings', 'industry')) {
                $table->string('industry')->nullable()->after('business_type');
            }
            if (!Schema::hasColumn('settings', 'services')) {
                $table->text('services')->nullable()->after('business_details');
            }
            if (!Schema::hasColumn('settings', 'products')) {
                $table->text('products')->nullable()->after('services');
            }
            if (!Schema::hasColumn('settings', 'location')) {
                $table->string('location')->nullable()->after('business_address');
            }
            if (!Schema::hasColumn('settings', 'working_days')) {
                $table->json('working_days')->nullable()->after('business_hours');
            }
            if (!Schema::hasColumn('settings', 'language')) {
                $table->string('language', 100)->nullable()->after('working_days');
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
                'industry',
                'services',
                'products',
                'location',
                'working_days',
                'language',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
