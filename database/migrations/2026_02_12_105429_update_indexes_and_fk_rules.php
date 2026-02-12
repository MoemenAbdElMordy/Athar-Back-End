<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            Schema::table('locations', function (Blueprint $table) {
                $table->dropForeign(['government_id']);
                $table->foreign('government_id')->references('id')->on('governments')->restrictOnDelete();
            });
        }

        if ($driver === 'sqlite') {
            DB::statement('CREATE INDEX IF NOT EXISTS bookings_scheduled_start_scheduled_end_index ON bookings (scheduled_start, scheduled_end)');
        } else {
            Schema::table('bookings', function (Blueprint $table) {
                $table->index(['scheduled_start', 'scheduled_end']);
            });
        }

        if ($driver === 'mysql') {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
            });
        }

        if ($driver === 'sqlite') {
            DB::statement('CREATE INDEX IF NOT EXISTS reviews_user_id_index ON reviews (user_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS reviews_companion_id_index ON reviews (companion_id)');
            DB::statement('CREATE INDEX IF NOT EXISTS reviews_location_id_index ON reviews (location_id)');
        } else {
            Schema::table('reviews', function (Blueprint $table) {
                $table->index('user_id');
                $table->index('companion_id');
                $table->index('location_id');
            });
        }

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `payments` MODIFY `currency` VARCHAR(3) NOT NULL DEFAULT 'EGP'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `payments` MODIFY `currency` VARCHAR(3) NOT NULL");
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS reviews_user_id_index');
            DB::statement('DROP INDEX IF EXISTS reviews_companion_id_index');
            DB::statement('DROP INDEX IF EXISTS reviews_location_id_index');
        } else {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropIndex(['user_id']);
                $table->dropIndex(['companion_id']);
                $table->dropIndex(['location_id']);
            });
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS bookings_scheduled_start_scheduled_end_index');
        } else {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropIndex(['scheduled_start', 'scheduled_end']);
            });
        }

        if ($driver === 'mysql') {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if ($driver === 'mysql') {
            Schema::table('locations', function (Blueprint $table) {
                $table->dropForeign(['government_id']);
                $table->foreign('government_id')->references('id')->on('governments')->cascadeOnDelete();
            });
        }
    }
};
