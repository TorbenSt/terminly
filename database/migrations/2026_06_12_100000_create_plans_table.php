<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('price_cents')->default(0);
            $table->string('currency', 3)->default('eur');
            // null = unendlich
            $table->unsignedInteger('included_staff')->nullable();
            $table->unsignedInteger('included_customers')->nullable();
            $table->unsignedInteger('extra_staff_price_cents')->default(0);
            $table->unsignedInteger('extra_customer_price_cents')->default(0);
            $table->string('stripe_product_id')->nullable();
            $table->string('stripe_base_price_id')->nullable();
            $table->string('stripe_staff_price_id')->nullable();
            $table->string('stripe_customer_price_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
