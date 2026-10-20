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
        if (!Schema::hasTable('user_package_histories')) {
            Schema::create('user_package_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('userId')->constrained('users')->onDelete('cascade');
                $table->foreignId('packageId')->nullable()->constrained('packages')->onDelete('set null');
                $table->foreignId('assignedBy')->nullable()->constrained('users')->onDelete('set null');
                $table->string('action')->default('assigned'); // created, assigned, updated, renewed
                $table->string('packageName')->nullable();
                $table->decimal('price', 10, 2)->nullable();
                $table->integer('msgCount')->nullable();
                $table->decimal('msgPrice', 10, 2)->nullable();
                $table->integer('contactCount')->nullable();
                $table->decimal('contactPrice', 10, 2)->nullable();
                $table->integer('templateCount')->nullable();
                $table->decimal('templatePrice', 10, 2)->nullable();
                $table->timestamp('startDate')->nullable();
                $table->timestamp('endDate')->nullable();
                $table->integer('day')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_package_histories');
    }
};
