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
        Schema::table('case_analyses', function (Blueprint $table): void {
            $table->json('motor_status')->nullable()->after('suggested_diligences');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_analyses', function (Blueprint $table): void {
            $table->dropColumn('motor_status');
        });
    }
};
