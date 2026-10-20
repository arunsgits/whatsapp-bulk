<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('wallet_orders')) {
            Schema::create('wallet_orders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('userId')->constrained('users')->onDelete('cascade');
                $table->string('razorpay_order_id')->nullable()->index();
                $table->string('receipt')->nullable();
                $table->decimal('amount', 12, 2);
                $table->unsignedInteger('amount_paise');
                $table->string('currency', 8)->default('INR');
                $table->string('status', 20)->default('pending');
                $table->string('razorpay_payment_id')->nullable();
                $table->string('razorpay_signature')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('wallet_transactions')) {
            Schema::create('wallet_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('userId')->constrained('users')->onDelete('cascade');
                $table->string('type', 20); // credit, debit
                $table->string('source', 30); // razorpay, admin, refund, usage
                $table->decimal('amount', 12, 2);
                $table->decimal('balance_after', 12, 2)->default(0);
                $table->string('reference_id')->nullable()->index();
                $table->unsignedBigInteger('wallet_order_id')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallet_orders');
    }
};
