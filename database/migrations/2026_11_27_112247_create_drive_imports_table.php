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
        Schema::create('drive_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drive_folder_id');
            $table->foreignId('parent_folder_id')->nullable()->constrained('folders')->nullOnDelete();
            $table->decimal('default_price', 18, 2)->default(0);
            $table->string('status', 16)->default('queued');
            $table->unsignedInteger('folders_created')->default(0);
            $table->unsignedInteger('products_created')->default(0);
            $table->unsignedInteger('products_skipped')->default(0);
            $table->unsignedInteger('files_skipped')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drive_imports');
    }
};
