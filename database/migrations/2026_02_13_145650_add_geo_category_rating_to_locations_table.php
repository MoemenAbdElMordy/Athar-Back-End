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
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('locations', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->decimal('average_rating', 3, 2)->default(0);
            $table->unsignedInteger('reviews_count')->default(0);

            $table->index('category_id');
        });

        if ($driver !== 'sqlite') {
            Schema::table('locations', function (Blueprint $table) {
                $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver !== 'sqlite') {
            Schema::table('locations', function (Blueprint $table) {
                $table->dropForeign(['category_id']);
            });
        }

        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropColumn(['latitude', 'longitude', 'category_id', 'average_rating', 'reviews_count']);
        });
    }
};
