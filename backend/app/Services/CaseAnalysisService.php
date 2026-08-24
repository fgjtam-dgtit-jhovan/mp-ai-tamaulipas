<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaseAnalysisService
{
    public function runAnalysis(string $externalCaseId, int $externalOffenseId, string $narrative): array
    {
        $baseUrl = config('services.mpia_engine.url');

        if (empty($baseUrl)) {
            throw new \InvalidArgumentException('La URL de MP-IA Engine no está definida en config/services.php.');
        }

        Log::info("Iniciando análisis para el caso: {$externalCaseId} con delito: {$externalOffenseId}");
        $response = Http::timeout(300)->post("{$baseUrl}/api/v1/analyze-case", [
            'external_case_id' => $externalCaseId,
            'external_offense_id' => $externalOffenseId,
            'fact_narrative' => $narrative,
        ]);

        if ($response->failed()) {
            throw new \RuntimeException('Error al procesar el análisis con MP-IA Engine: '.$response->status());
        }

        $data = $response->json('data');

        if (! is_array($data) || ! isset($data['elements_analysis'], $data['objectivity_audit'], $data['suggested_diligences'])) {
            throw new \UnexpectedValueException('MP-IA Engine devolvió una respuesta incompleta.');
        }

        return $data;
    }
}
