<?php
namespace App\Services;

use App\Models\CaseAnalysis;
use Illuminate\Support\Facades\Http;

class CaseAnalysisService
{
    public function runAnalysis(string $externalCaseId, int $externalOffenseId, string $narrative): CaseAnalysis
    {
        // 1. Obtener la URL desde la configuración
        $baseUrl = config('services.mpia_engine.url');

        // Validación de seguridad para evitar enviar peticiones a URLs vacías
        if (empty($baseUrl)) {
            throw new \InvalidArgumentException("La URL de MP-IA Engine no está definida en config/services.php.");
        }

        // 2. Realizar la petición a FastAPI
        $response = Http::timeout(60)->post("{$baseUrl}/api/v1/analyze-case", [
            'external_case_id' => $externalCaseId,
            'external_offense_id' => $externalOffenseId,
            'fact_narrative' => $narrative,
        ]);

        if ($response->failed()) {
            throw new \Exception("Error al procesar el análisis con MP-IA Engine: " . $response->body());
        }

        $aiData = $response->json()['data'];

        // 3. Persistir en PostgreSQL
        return CaseAnalysis::create([
            'external_case_id'     => $externalCaseId,
            'external_offense_id'  => $externalOffenseId,
            'user_id'              => auth()->id(),
            'facts_breakdown'      => ['narrative' => $narrative],
            'elements_status'      => $aiData['elements_analysis'],
            'objectivity_audit'    => $aiData['objectivity_audit'],
            'suggested_diligences' => $aiData['suggested_diligences'],
            'status'               => 'draft',
        ]);
    }
}
