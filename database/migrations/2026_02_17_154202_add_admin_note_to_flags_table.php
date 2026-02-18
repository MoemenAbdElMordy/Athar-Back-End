<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flags', function (Blueprint $table) {
            if (!Schema::hasColumn('flags', 'admin_note')) {
                $table->text('admin_note')->nullable()->after('details');
            }
        });
    }

    public function down(): void
    {
        Schema::table('flags', function (Blueprint $table) {
            if (Schema::hasColumn('flags', 'admin_note')) {
                $table->dropColumn('admin_note');
            }
        });
    }
};
