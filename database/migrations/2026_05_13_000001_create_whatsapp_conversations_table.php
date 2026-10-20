<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('leads')->onDelete('set null');
            $table->string('phone_number');
            $table->string('whatsapp_phone_id')->nullable();
            $table->string('contact_name')->nullable();
            $table->string('profile_picture')->nullable();
            $table->enum('status', ['open', 'closed', 'archived'])->default('open');
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('unread_count')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamp('last_read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['phone_number', 'status']);
            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_conversations');
    }
};
