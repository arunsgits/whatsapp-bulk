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
        if (!Schema::hasTable('knowledge_bases')) {
            Schema::create('knowledge_bases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('custom_id', 100)->nullable()->index();
                $table->text('question');
                $table->longText('answer');
                $table->string('category', 150)->default('General')->index();
                $table->json('keywords')->nullable();
                $table->string('source', 50)->default('Manual');
                $table->text('source_url')->nullable();
                $table->boolean('is_published')->default(true)->index();
                $table->boolean('is_enabled')->default(true)->index();
                $table->timestamps();

                $table->index(['user_id', 'is_published', 'is_enabled']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_bases');
    }
};
