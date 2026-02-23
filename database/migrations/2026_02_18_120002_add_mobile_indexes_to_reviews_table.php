<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (!Schema::hasColumn('reviews', 'location_id')) {
                return;
            }

            $table->index(['user_id', 'location_id'], 'reviews_user_location_index');
            $table->index(['location_id', 'created_at'], 'reviews_location_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropIndex('reviews_user_location_index');
            $table->dropIndex('reviews_location_created_at_index');
        });
    }
};
