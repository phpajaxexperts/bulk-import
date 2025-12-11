<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('uploads', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('filename');
            $table->string('original_name');
            $table->unsignedBigInteger('size');
            $table->string('mime_type');
            $table->string('checksum')->nullable();
            $table->enum('status', ['pending', 'uploading', 'completed', 'failed'])->default('pending');
            $table->integer('total_chunks')->default(0);
            $table->integer('uploaded_chunks')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('uuid');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('uploads');
    }
};
