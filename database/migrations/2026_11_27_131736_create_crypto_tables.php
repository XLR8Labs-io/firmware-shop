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
        Schema::create('crypto_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('network', 8);
            $table->decimal('base_amount', 18, 2);
            $table->decimal('pay_amount', 18, 2);
            $table->string('status', 16)->default('pending');
            $table->string('tx_hash', 80)->nullable()->unique();
            $table->decimal('received_amount', 24, 6)->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['network', 'status', 'pay_amount']);
            $table->index(['user_id', 'id']);
        });

        Schema::create('crypto_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('network', 8);
            $table->string('tx_hash', 80)->unique();
            $table->string('from_address', 64)->nullable();
            $table->decimal('amount', 24, 6);
            $table->timestamp('block_time');
            $table->foreignId('crypto_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('admin_note', 500)->nullable();
            $table->timestamps();

            $table->index(['network', 'block_time']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crypto_transfers');
        Schema::dropIfExists('crypto_invoices');
    }
};
