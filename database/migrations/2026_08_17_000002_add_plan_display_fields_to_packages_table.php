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
        Schema::table('packages', function (Blueprint $table) {
            if (!Schema::hasColumn('packages', 'price')) {
                $table->string('price')->nullable()->after('contactCount');
            }
            if (!Schema::hasColumn('packages', 'period')) {
                $table->string('period')->default('/month')->after('price');
            }
            if (!Schema::hasColumn('packages', 'popular')) {
                $table->boolean('popular')->default(false)->after('period');
            }
            if (!Schema::hasColumn('packages', 'color')) {
                $table->string('color')->default('indigo')->after('popular');
            }
            if (!Schema::hasColumn('packages', 'iconName')) {
                $table->string('iconName')->default('package')->after('color');
            }
            if (!Schema::hasColumn('packages', 'features')) {
                $table->json('features')->nullable()->after('iconName');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $columnsToDrop = array_filter([
                'price',
                'period',
                'popular',
                'color',
                'iconName',
                'features'
            ], fn($col) => Schema::hasColumn('packages', $col));

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
