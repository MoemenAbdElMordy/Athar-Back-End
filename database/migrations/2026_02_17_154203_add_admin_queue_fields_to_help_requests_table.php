<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('help_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('help_requests', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('volunteer_id');
                $table->index('user_id');
            }

            if (!Schema::hasColumn('help_requests', 'assigned_admin_id')) {
                $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete()->after('user_id');
                $table->index('assigned_admin_id');
            }

            if (!Schema::hasColumn('help_requests', 'name')) {
                $table->string('name')->nullable()->after('volunteer_id');
            }
            if (!Schema::hasColumn('help_requests', 'phone')) {
                $table->string('phone')->nullable()->after('name');
            }
            if (!Schema::hasColumn('help_requests', 'location_text')) {
                $table->string('location_text')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('help_requests', 'lat')) {
                $table->decimal('lat', 10, 7)->nullable()->after('location_text');
            }
            if (!Schema::hasColumn('help_requests', 'lng')) {
                $table->decimal('lng', 10, 7)->nullable()->after('lat');
            }
            if (!Schema::hasColumn('help_requests', 'message')) {
                $table->text('message')->nullable()->after('lng');
            }
            if (!Schema::hasColumn('help_requests', 'resolved_at')) {
                $table->timestamp('resolved_at')->nullable()->after('cancelled_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('help_requests', function (Blueprint $table) {
            $columns = ['user_id', 'assigned_admin_id', 'name', 'phone', 'location_text', 'lat', 'lng', 'message', 'resolved_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('help_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
