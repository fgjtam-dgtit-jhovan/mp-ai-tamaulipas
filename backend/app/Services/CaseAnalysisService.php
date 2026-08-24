<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaseAnalysisService
{
    public function runAnalysis(string $externalCaseId, int $externalOffenseId, string $narrative): array
    {
        // 1. Obtener la URL desde la configuración
        $baseUrl = config('services.mpia_engine.url');

        // Validación de seguridad para evitar enviar peticiones a URLs vacías
        if (empty($baseUrl)) {
            throw new \InvalidArgumentException('La URL de MP-IA Engine no está definida en config/services.php.');
        }

        Log::info("Iniciando análisis para el caso: {$externalCaseId} con delito: {$externalOffenseId}");
        // 2. Realizar la petición a FastAPI
        $response = Http::timeout(300)->post("{$baseUrl}/api/v1/analyze-case", [
            'external_case_id' => $externalCaseId,
            'external_offense_id' => $externalOffenseId,
            'fact_narrative' => $narrative,
        ]);

        if ($response->failed()) {
            throw new \Exception('Error al procesar el análisis con MP-IA Engine: '.$response->body());
        }

        return $response->json()['data'] ?? [];
    }
}
