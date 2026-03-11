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
        Schema::table('payments', function (Blueprint $table) {
            // Make booking_id nullable (payments may exist without a booking)
            // We cannot alter the existing FK inline, so we drop & re-add it.
        });

        // Drop old FK + unique constraint, re-add as nullable
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->dropUnique(['booking_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('booking_id')->nullable()->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('booking_id')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount_cents')->default(0)->after('total_amount');
            $table->string('order_reference', 64)->nullable()->after('amount_cents');
            $table->string('paymob_order_id', 64)->nullable()->after('order_reference');
            $table->string('paymob_transaction_id', 64)->nullable()->after('paymob_order_id');
            $table->text('paymob_payment_key')->nullable()->after('paymob_transaction_id');
            $table->text('iframe_url')->nullable()->after('paymob_payment_key');
            $table->string('wallet_number', 20)->nullable()->after('iframe_url');
            $table->text('wallet_redirect_url')->nullable()->after('wallet_number');
            $table->string('status', 20)->default('pending')->after('wallet_redirect_url');
            $table->boolean('success')->default(false)->after('status');
            $table->json('raw_request_json')->nullable()->after('success');
            $table->json('raw_response_json')->nullable()->after('raw_request_json');
            $table->json('callback_payload_json')->nullable()->after('raw_response_json');

            $table->index('paymob_order_id');
            $table->index('paymob_transaction_id');
            $table->index('order_reference');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['paymob_order_id']);
            $table->dropIndex(['paymob_transaction_id']);
            $table->dropIndex(['order_reference']);
            $table->dropIndex(['status']);

            $table->dropForeign(['user_id']);

            $table->dropColumn([
                'user_id',
                'amount_cents',
                'order_reference',
                'paymob_order_id',
                'paymob_transaction_id',
                'paymob_payment_key',
                'iframe_url',
                'wallet_number',
                'wallet_redirect_url',
                'status',
                'success',
                'raw_request_json',
                'raw_response_json',
                'callback_payload_json',
            ]);
        });

        // Restore original FK
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
            $table->unsignedBigInteger('booking_id')->nullable(false)->change();
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->unique('booking_id');
        });
    }
};
