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
        Schema::create('whatsapp_connected_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userId')->constrained('users')->onDelete('cascade');
            $table->string('account_name');
            $table->string('session_id')->unique();
            $table->string('phone_number')->nullable();
            $table->string('profile_name')->nullable();
            $table->text('profile_picture')->nullable();
            $table->enum('status', ['initializing', 'qr_ready', 'connected', 'disconnected', 'failed'])->default('initializing');
            $table->longText('qr_code')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            $table->timestamps();

            $table->index(['userId', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_connected_accounts');
    }
};
