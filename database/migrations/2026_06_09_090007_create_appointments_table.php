<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_member_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->foreignId('recurring_service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('proposed');
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedSmallInteger('travel_time_minutes')->default(0);
            $table->text('notes')->nullable();
            $table->string('public_token', 64)->unique();
            $table->unsignedTinyInteger('negotiation_round')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
