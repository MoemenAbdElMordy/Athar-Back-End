<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_requests', function (Blueprint $table) {
            $table->unsignedSmallInteger('hours')->default(1)->after('service_fee');
            $table->unsignedInteger('price_per_hour')->default(0)->after('hours');
        });
    }

    public function down(): void
    {
        Schema::table('help_requests', function (Blueprint $table) {
            $table->dropColumn(['hours', 'price_per_hour']);
        });
    }
};
