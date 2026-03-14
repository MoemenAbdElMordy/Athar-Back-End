<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Volunteer reviews (user rates volunteer after completed help request) ──
        Schema::create('volunteer_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('help_request_id')->constrained('help_requests')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('volunteer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['help_request_id', 'reviewer_id']);
            $table->index('volunteer_id');
            $table->index('rating');
        });

        // ── Settlement fields on help_requests for earnings analytics ──
        Schema::table('help_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('help_requests', 'fee_amount_cents')) {
                $table->unsignedInteger('fee_amount_cents')->default(0)->after('service_fee');
            }
            if (!Schema::hasColumn('help_requests', 'net_amount_cents')) {
                $table->unsignedInteger('net_amount_cents')->default(0)->after('fee_amount_cents');
            }
            if (!Schema::hasColumn('help_requests', 'cleared_at')) {
                $table->timestamp('cleared_at')->nullable()->after('completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('volunteer_reviews');

        Schema::table('help_requests', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('help_requests', 'fee_amount_cents')) {
                $columns[] = 'fee_amount_cents';
            }
            if (Schema::hasColumn('help_requests', 'net_amount_cents')) {
                $columns[] = 'net_amount_cents';
            }
            if (Schema::hasColumn('help_requests', 'cleared_at')) {
                $columns[] = 'cleared_at';
            }
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
