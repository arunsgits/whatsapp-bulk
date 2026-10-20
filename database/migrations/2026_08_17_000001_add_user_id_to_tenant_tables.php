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
        if (Schema::hasTable('leads') && !Schema::hasColumn('leads', 'userId')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->foreignId('userId')->nullable()->after('id')->constrained('users')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('media') && !Schema::hasColumn('media', 'userId')) {
            Schema::table('media', function (Blueprint $table) {
                $table->foreignId('userId')->nullable()->after('id')->constrained('users')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('whatsapp_automation_rules') && !Schema::hasColumn('whatsapp_automation_rules', 'userId')) {
            Schema::table('whatsapp_automation_rules', function (Blueprint $table) {
                $table->foreignId('userId')->nullable()->after('id')->constrained('users')->onDelete('cascade');
            });
        }

        if (Schema::hasTable('whatsapp_campaigns') && !Schema::hasColumn('whatsapp_campaigns', 'userId')) {
            Schema::table('whatsapp_campaigns', function (Blueprint $table) {
                $table->foreignId('userId')->nullable()->after('id')->constrained('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'userId')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropForeign(['userId']);
                $table->dropColumn('userId');
            });
        }

        if (Schema::hasTable('media') && Schema::hasColumn('media', 'userId')) {
            Schema::table('media', function (Blueprint $table) {
                $table->dropForeign(['userId']);
                $table->dropColumn('userId');
            });
        }

        if (Schema::hasTable('whatsapp_automation_rules') && Schema::hasColumn('whatsapp_automation_rules', 'userId')) {
            Schema::table('whatsapp_automation_rules', function (Blueprint $table) {
                $table->dropForeign(['userId']);
                $table->dropColumn('userId');
            });
        }

        if (Schema::hasTable('whatsapp_campaigns') && Schema::hasColumn('whatsapp_campaigns', 'userId')) {
            Schema::table('whatsapp_campaigns', function (Blueprint $table) {
                $table->dropForeign(['userId']);
                $table->dropColumn('userId');
            });
        }
    }
};
