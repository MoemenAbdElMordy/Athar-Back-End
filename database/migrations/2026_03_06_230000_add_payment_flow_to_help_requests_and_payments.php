<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_requests', function (Blueprint $table) {
            $table->string('payment_method', 10)->default('cash')->after('status');
            $table->unsignedInteger('service_fee')->default(0)->after('payment_method');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('help_request_id')->nullable()->after('user_id')
                  ->constrained('help_requests')->nullOnDelete();
            $table->index('help_request_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['help_request_id']);
            $table->dropIndex(['help_request_id']);
            $table->dropColumn('help_request_id');
        });

        Schema::table('help_requests', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'service_fee']);
        });
    }
};
