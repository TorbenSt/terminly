<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_prospects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prospect_search_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('new');
            $table->string('company_name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('industry')->nullable();
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->text('match_reason')->nullable();
            $table->string('source')->default('google_places');
            $table->string('source_url')->nullable();
            $table->string('google_place_id')->nullable();
            $table->timestamp('last_contacted_at')->nullable();
            $table->unsignedSmallInteger('contact_count')->default(0);
            $table->foreignId('converted_customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('opt_out_token', 64)->nullable()->unique();
            $table->text('notes')->nullable();
            $table->timestamp('discovered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'google_place_id']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_prospects');
    }
};
