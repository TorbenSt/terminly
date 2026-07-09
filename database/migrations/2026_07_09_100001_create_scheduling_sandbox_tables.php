<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduling_sandbox_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode');
            $table->string('scenario')->nullable();
            $table->foreignId('source_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('status')->default('ready');
            $table->boolean('use_grok_live')->default(true);
            $table->json('snapshot_meta')->nullable();
            $table->json('grok_debug')->nullable();
            $table->json('validation_results')->nullable();
            $table->timestamps();

            $table->index(['created_by_user_id', 'status']);
        });

        Schema::create('scheduling_sandbox_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scheduling_sandbox_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_proposal_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('subject');
            $table->text('body_html');
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduling_sandbox_messages');
        Schema::dropIfExists('scheduling_sandbox_runs');
    }
};
