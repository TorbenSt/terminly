<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('round')->default(1);
            $table->timestamp('option_1_at');
            $table->timestamp('option_2_at');
            $table->timestamp('option_3_at');
            $table->foreignId('staff_member_id')->nullable()->constrained('staff_members')->nullOnDelete();
            $table->unsignedTinyInteger('selected_option')->nullable();
            $table->string('token', 64)->unique();
            $table->timestamp('email_sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_proposals');
    }
};
