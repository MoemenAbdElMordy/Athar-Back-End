<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accessibility_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('accessibility_reports', 'accessible_toilet')) {
                $table->boolean('accessible_toilet')->default(false)->after('parking');
            }

            if (!Schema::hasColumn('accessibility_reports', 'notes')) {
                $table->text('notes')->nullable()->after('accessible_toilet');
            }
        });
    }

    public function down(): void
    {
        Schema::table('accessibility_reports', function (Blueprint $table) {
            $columns = ['accessible_toilet', 'notes'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('accessibility_reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
