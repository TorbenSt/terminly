<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointment_proposals', function (Blueprint $table) {
            $table->unsignedTinyInteger('recommended_option')->nullable()->after('option_3_at');
        });
    }

    public function down(): void
    {
        Schema::table('appointment_proposals', function (Blueprint $table) {
            $table->dropColumn('recommended_option');
        });
    }
};
