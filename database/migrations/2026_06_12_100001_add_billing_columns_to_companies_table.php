<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Laravel Cashier (Billable) Spalten
            $table->string('stripe_id')->nullable()->index();
            $table->string('pm_type')->nullable();
            $table->string('pm_last_four', 4)->nullable();
            $table->timestamp('trial_ends_at')->nullable();

            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->boolean('billing_exempt')->default(false);
            // null = Plan-Wert, -1 = unendlich
            $table->integer('staff_limit_override')->nullable();
            $table->integer('customer_limit_override')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn([
                'stripe_id',
                'pm_type',
                'pm_last_four',
                'trial_ends_at',
                'billing_exempt',
                'staff_limit_override',
                'customer_limit_override',
            ]);
        });
    }
};
