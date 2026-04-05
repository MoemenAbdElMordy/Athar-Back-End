<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutorials', function (Blueprint $table): void {
            if (!Schema::hasColumn('tutorials', 'views_count')) {
                $table->unsignedBigInteger('views_count')->default(0)->after('is_published');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tutorials', function (Blueprint $table): void {
            if (Schema::hasColumn('tutorials', 'views_count')) {
                $table->dropColumn('views_count');
            }
        });
    }
};
