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
        DB::table('case_evidence')
            ->where('origin', 'ia')
            ->where('is_verified', false)
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($evidence): string => $evidence->case_analysis_id.'|'.mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($evidence->related_fact))))
            ->each(function ($duplicates): void {
                $primary = $duplicates->first();

                foreach ($duplicates->pluck('offense_element_id')->filter()->unique() as $elementId) {
                    DB::table('case_evidence_offense_elements')->insertOrIgnore([
                        'case_evidence_id' => $primary->id,
                        'offense_element_id' => $elementId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $duplicates->skip(1)->each(fn ($duplicate): int => DB::table('case_evidence')->where('id', $duplicate->id)->delete());
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Consolidated rows cannot be restored without a separate archive.
    }
};
