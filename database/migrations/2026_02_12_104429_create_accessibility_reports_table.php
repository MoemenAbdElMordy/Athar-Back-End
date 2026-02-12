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
        Schema::create('accessibility_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->unique()->constrained('locations')->cascadeOnDelete();

            $table->boolean('verified')->default(false);
            $table->boolean('wide_entrance')->default(false);
            $table->boolean('wheelchair_accessible')->default(false);
            $table->boolean('elevator_available')->default(false);
            $table->boolean('ramp_available')->default(false);
            $table->boolean('parking')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accessibility_reports');
    }
};
