<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('case_evidence', function (Blueprint $table): void {
            $table->string('origin')->default('ia')->after('case_analysis_id');
            $table->boolean('is_verified')->default(false)->after('valuation_status');
            $table->foreignId('reviewed_by')->nullable()->after('is_verified')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_evidence', function (Blueprint $table): void {
            $table->dropForeign(['reviewed_by']);
            $table->dropColumn(['origin', 'is_verified', 'reviewed_by', 'reviewed_at']);
        });
    }
};
