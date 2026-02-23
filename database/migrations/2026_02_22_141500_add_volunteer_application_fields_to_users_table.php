<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('city')->nullable()->after('phone');
            $table->string('national_id')->nullable()->after('city');
            $table->date('date_of_birth')->nullable()->after('national_id');
            $table->string('id_document_path')->nullable()->after('date_of_birth');
            $table->json('volunteer_languages')->nullable()->after('id_document_path');
            $table->json('volunteer_availability')->nullable()->after('volunteer_languages');
            $table->text('volunteer_motivation')->nullable()->after('volunteer_availability');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'city',
                'national_id',
                'date_of_birth',
                'id_document_path',
                'volunteer_languages',
                'volunteer_availability',
                'volunteer_motivation',
            ]);
        });
    }
};
