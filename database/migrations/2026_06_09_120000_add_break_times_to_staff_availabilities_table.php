<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_availabilities', function (Blueprint $table) {
            $table->time('break_start_time')->nullable()->after('end_time');
            $table->time('break_end_time')->nullable()->after('break_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('staff_availabilities', function (Blueprint $table) {
            $table->dropColumn(['break_start_time', 'break_end_time']);
        });
    }
};
