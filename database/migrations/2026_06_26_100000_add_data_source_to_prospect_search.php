<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prospect_search_profiles', function (Blueprint $table) {
            $table->string('data_source', 32)->default('google_places')->after('ai_instructions');
        });

        Schema::table('prospect_search_runs', function (Blueprint $table) {
            $table->string('data_source', 32)->nullable()->after('trigger');
        });
    }

    public function down(): void
    {
        Schema::table('prospect_search_runs', function (Blueprint $table) {
            $table->dropColumn('data_source');
        });

        Schema::table('prospect_search_profiles', function (Blueprint $table) {
            $table->dropColumn('data_source');
        });
    }
};
