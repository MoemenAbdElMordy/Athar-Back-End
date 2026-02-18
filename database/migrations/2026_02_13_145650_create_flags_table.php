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
        Schema::create('flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flagger_id')->constrained('users')->cascadeOnDelete();
            $table->string('flaggable_type', 100);
            $table->unsignedBigInteger('flaggable_id');
            $table->string('reason', 30);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('open');
            $table->foreignId('handled_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();

            $table->index('flagger_id');
            $table->index('status');
            $table->index(['flaggable_type', 'flaggable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('flags');
    }
};
