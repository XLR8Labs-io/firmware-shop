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
        Schema::create('mfs_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('method', 16);
            $table->decimal('amount_bdt', 18, 2);
            $table->decimal('rate_bdt_per_usd', 10, 4);
            $table->decimal('amount_usd', 18, 2);
            $table->string('sender_number', 20)->nullable();
            $table->string('trx_id', 32)->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('source', 16)->default('manual');
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->string('admin_note', 500)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // One real payment can exist only once, however it was submitted.
            $table->unique(['method', 'trx_id']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mfs_deposits');
    }
};
