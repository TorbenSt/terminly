<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('staff_customer_binding', 32)
                ->default('prefer')
                ->after('timezone');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('primary_staff_member_id')
                ->nullable()
                ->after('is_active')
                ->constrained('staff_members')
                ->nullOnDelete();
            $table->foreignId('backup_staff_member_id')
                ->nullable()
                ->after('primary_staff_member_id')
                ->constrained('staff_members')
                ->nullOnDelete();
        });

        Schema::table('service_types', function (Blueprint $table) {
            $table->unsignedSmallInteger('completion_window_days')
                ->default(14)
                ->after('interval_months');
        });
    }

    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table) {
            $table->dropColumn('completion_window_days');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('backup_staff_member_id');
            $table->dropConstrainedForeignId('primary_staff_member_id');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('staff_customer_binding');
        });
    }
};
