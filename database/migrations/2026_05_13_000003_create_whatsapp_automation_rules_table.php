<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('trigger_type', ['keyword', 'welcome', 'faq', 'drip', 'followup', 'scheduled'])->default('keyword');
            $table->text('trigger_keywords')->nullable();
            $table->foreignId('template_id')->nullable()->constrained('templates')->onDelete('set null');
            $table->text('response_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0);
            $table->integer('execution_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->json('conditions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['trigger_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_automation_rules');
    }
};
