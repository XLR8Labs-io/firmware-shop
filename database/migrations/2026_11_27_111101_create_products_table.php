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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('model_names')->nullable();
            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();
            $table->decimal('price', 18, 2)->default(0);
            $table->boolean('is_featured')->default(false);
            $table->string('status', 16)->default('draft');
            $table->string('drive_file_id')->unique();
            $table->string('mirror_drive_file_id')->nullable();
            $table->string('file_name');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->char('md5', 32)->nullable();
            $table->string('mime_type')->nullable();
            $table->text('file_password')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->fullText(['title', 'model_names']);
            $table->index(['folder_id', 'status']);
            $table->index(['status', 'published_at']);
            $table->index(['status', 'download_count']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
