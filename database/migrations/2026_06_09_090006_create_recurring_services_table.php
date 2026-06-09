<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_type_id')->constrained()->cascadeOnDelete();
            $table->timestamp('last_completed_at')->nullable();
            $table->timestamp('next_due_at');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_services');
    }
};
