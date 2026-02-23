<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accessibility_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->boolean('wide_entrance')->default(false);
            $table->boolean('wheelchair_accessible')->default(false);
            $table->boolean('elevator_available')->default(false);
            $table->boolean('ramp_available')->default(false);
            $table->boolean('parking')->default(false);
            $table->boolean('accessible_toilet')->default(false);
            $table->text('notes')->nullable();

            $table->string('status', 20)->default('pending');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['location_id', 'user_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accessibility_contributions');
    }
};
