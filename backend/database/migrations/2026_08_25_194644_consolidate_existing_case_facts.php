<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('case_facts')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($fact): string => $fact->case_analysis_id.'|'.$fact->information_type.'|'.mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($fact->content))))
            ->each(fn ($duplicates) => $duplicates->skip(1)->each(fn ($duplicate): int => DB::table('case_facts')->where('id', $duplicate->id)->delete()));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Consolidated rows cannot be restored without a separate archive.
    }
};
