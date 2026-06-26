<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('includes_prospect_search')->default(false)->after('is_default');
            $table->unsignedSmallInteger('max_prospect_results_per_run')->nullable()->after('includes_prospect_search');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('prospect_search_override')->nullable()->after('customer_limit_override');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('google_place_id')->nullable()->after('notes');
            $table->index(['company_id', 'google_place_id']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'google_place_id']);
            $table->dropColumn('google_place_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('prospect_search_override');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['includes_prospect_search', 'max_prospect_results_per_run']);
        });
    }
};
