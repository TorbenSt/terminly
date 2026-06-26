<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('grok_feedback_collection_id')->nullable()->after('prospect_search_override');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('prospect_outreach_limit_per_day')->nullable()->after('max_prospect_results_per_run');
        });

        Schema::table('prospect_feedback', function (Blueprint $table) {
            $table->string('grok_document_id')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('prospect_feedback', function (Blueprint $table) {
            $table->dropColumn('grok_document_id');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('prospect_outreach_limit_per_day');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('grok_feedback_collection_id');
        });
    }
};
