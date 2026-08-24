<?php

namespace App\Jobs;

use App\Models\CaseAnalysis;
use App\Services\CaseAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessCaseAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 200;

    public function __construct(
        public CaseAnalysis $analysis,
        public string $factNarrative
    ) {}

    public function handle(CaseAnalysisService $aiClient): void
    {
        try {
            $response = $aiClient->runAnalysis(
                $this->analysis->external_case_id,
                $this->analysis->external_offense_id,
                $this->factNarrative
            );

            $data = $response;

            // Guardamos directamente en tus columnas existentes
            $this->analysis->update([
                'elements_status' => $data['elements_analysis'] ?? [],
                'objectivity_audit' => $data['objectivity_audit'] ?? [],
                'suggested_diligences' => $data['suggested_diligences'] ?? [],
                'status' => 'reviewed',
            ]);

        } catch (Throwable $e) {
            $this->analysis->update([
                'status' => 'rejected',
            ]);

            throw $e;
        }
    }
}
