<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->after('is_active');
            $table->foreignId('sandbox_source_company_id')->nullable()->after('is_sandbox')
                ->constrained('companies')->nullOnDelete();
            $table->timestamp('sandbox_snapshot_at')->nullable()->after('sandbox_source_company_id');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sandbox_source_company_id');
            $table->dropColumn(['is_sandbox', 'sandbox_snapshot_at']);
        });
    }
};
