<?php

namespace App\Services;

use App\Models\LegalArticle;
use App\Models\Ms\Crime;
use App\Models\OffenseElement;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CaseAnalysisService
{
    public function runAnalysis(string $externalCaseId, int $externalOffenseId, string $narrative, ?\Carbon\Carbon $factDate = null): array
    {
        $baseUrl = config('services.mpia_engine.url');

        if (empty($baseUrl)) {
            throw new \InvalidArgumentException('La URL de MP-IA Engine no está definida en config/services.php.');
        }

        Log::info("Iniciando análisis para el caso: {$externalCaseId} con delito: {$externalOffenseId}");
        $elements = OffenseElement::with(['legalArticle.version.document'])
            ->where('external_offense_id', $externalOffenseId)
            ->orderBy('display_order')
            ->get();

        if ($elements->isEmpty()) {
            throw new \UnexpectedValueException('El delito no tiene elementos jurídicos configurados.');
        }

        $crime = Crime::on('sqlsrv')->find($externalOffenseId);

        $response = Http::timeout(540)->post("{$baseUrl}/api/v1/analyze-case", [
            'external_case_id' => $externalCaseId,
            'external_offense_id' => $externalOffenseId,
            'offense_name' => $crime?->DLTO,
            'fact_narrative' => $narrative,
            'fact_date' => $factDate?->toDateString(),
            'elements' => $elements->map(fn (OffenseElement $element): array => [
                'id' => $element->id,
                'name' => $element->name,
                'type' => $element->element_type,
                'criteria' => $element->verification_criteria,
                'required' => $element->is_required,
                'legal_article' => $element->legalArticle?->citation,
            ])->values()->all(),
            'legal_articles' => $elements->pluck('legalArticle')->filter()->unique('id')->map(fn (LegalArticle $article): array => [
                'id' => $article->id,
                'article' => $article->article_number,
                'fraction' => $article->fraction,
                'content' => $article->content,
                'citation' => $article->citation,
            ])->values()->all(),
        ]);

        if ($response->failed()) {
            $detail = $response->json('detail');
            $detail = is_array($detail) ? ($detail['error'] ?? $detail['message'] ?? json_encode($detail)) : $detail;

            if (is_string($detail) && str_contains($detail, 'No hay artículos jurídicos')) {
                throw new \UnexpectedValueException($detail);
            }

            throw new \RuntimeException('Error al procesar el análisis con MP-IA Engine ('.$response->status().'): '.$detail);
        }

        $data = $response->json('data');

        if (! is_array($data) || ! isset($data['facts'], $data['elements_analysis'], $data['objectivity_audit'], $data['suggested_diligences'])) {
            throw new \UnexpectedValueException('MP-IA Engine devolvió una respuesta incompleta.');
        }

        return $data;
    }
}
