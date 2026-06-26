<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_search_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('industries');
            $table->text('ai_instructions')->nullable();
            $table->string('postal_code', 10);
            $table->unsignedSmallInteger('radius_km')->default(10);
            $table->unsignedSmallInteger('max_results_per_run')->default(25);
            $table->boolean('exclude_existing_customers')->default(true);
            $table->boolean('is_active')->default(true);
            $table->boolean('schedule_enabled')->default(false);
            $table->string('schedule_cron')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_search_profiles');
    }
};
