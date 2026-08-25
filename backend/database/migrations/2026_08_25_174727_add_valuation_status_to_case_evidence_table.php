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
            $table->string('valuation_status')->default('pendiente')->after('authenticity_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_evidence', function (Blueprint $table): void {
            $table->dropColumn('valuation_status');
        });
    }
};
