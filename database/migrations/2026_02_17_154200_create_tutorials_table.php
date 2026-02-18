<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tutorials', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('video_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('category')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index('is_published');
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorials');
    }
};
