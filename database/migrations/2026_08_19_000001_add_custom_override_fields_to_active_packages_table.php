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
        Schema::table('active_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('active_packages', 'custom_msg_price')) {
                $table->decimal('custom_msg_price', 10, 2)->nullable()->after('custom_msg_count');
            }
            if (!Schema::hasColumn('active_packages', 'custom_contact_count')) {
                $table->integer('custom_contact_count')->nullable()->after('custom_msg_price');
            }
            if (!Schema::hasColumn('active_packages', 'custom_contact_price')) {
                $table->decimal('custom_contact_price', 10, 2)->nullable()->after('custom_contact_count');
            }
            if (!Schema::hasColumn('active_packages', 'custom_template_count')) {
                $table->integer('custom_template_count')->nullable()->after('custom_contact_price');
            }
            if (!Schema::hasColumn('active_packages', 'custom_template_price')) {
                $table->decimal('custom_template_price', 10, 2)->nullable()->after('custom_template_count');
            }
            if (!Schema::hasColumn('active_packages', 'custom_price')) {
                $table->decimal('custom_price', 10, 2)->nullable()->after('custom_template_price');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('active_packages', function (Blueprint $table) {
            $columns = [
                'custom_msg_price',
                'custom_contact_count',
                'custom_contact_price',
                'custom_template_count',
                'custom_template_price',
                'custom_price',
            ];
            foreach ($columns as $col) {
                if (Schema::hasColumn('active_packages', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
