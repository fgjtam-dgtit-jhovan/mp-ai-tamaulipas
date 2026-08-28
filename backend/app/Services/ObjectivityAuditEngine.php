<?php

namespace App\Services;

use App\Models\CaseAnalysis;
use App\Models\CaseFact;
use App\Models\LegalArticle;
use App\Models\OffenseElement;

class ObjectivityAuditEngine
{
    public function evaluate(CaseAnalysis $analysis, array $elementsAnalysis): array
    {
        // Fuente de verdad: el vínculo relacional element -> artículo del
        // catálogo (OffenseElement.legal_article_id), no lo que el RAG
        // haya recuperado por similitud semántica. El RAG es apoyo para
        // el LLM, no evidencia jurídica trazable.
        $elementIds = collect($elementsAnalysis)->pluck('element_id')->filter()->unique();
        $offenseElements = OffenseElement::with('legalArticle.version')
            ->whereIn('id', $elementIds)
            ->get()
            ->keyBy('id');

        return [
            'vigencia' => $this->checkVigenciaNormativa($analysis, $elementsAnalysis, $offenseElements),
            'fuentes_verificadas' => $this->checkFuentesVerificadas($elementsAnalysis, $offenseElements),
            'hechos_probados' => $this->checkManifestacionComoHechoProbado($analysis, $elementsAnalysis),
        ];
    }

    private function checkVigenciaNormativa(CaseAnalysis $analysis, array $elementsAnalysis, $offenseElements): array
    {
        if (! $analysis->fact_date) {
            return [
                'status' => 'no_verificable',
                'reason' => 'La carpeta no tiene registrada la fecha jurídicamente relevante del hecho.',
                'articles_checked' => [],
            ];
        }

        $factDate = $analysis->fact_date;
        $problematic = [];
        $checked = [];

        foreach ($elementsAnalysis as $row) {
            $offenseElement = $offenseElements->get($row['element_id'] ?? null);
            $article = $offenseElement?->legalArticle;

            if (! $article || ! $article->version) {
                continue; // sin artículo asociado en el catálogo, no hay nada que verificar aquí
            }

            $version = $article->version;
            $vigenteAlHecho = $version->effective_date <= $factDate
                && (is_null($version->repealed_date) || $version->repealed_date >= $factDate);

            $checked[] = [
                'element_id' => $row['element_id'],
                'citation' => $article->citation,
                'vigente_al_hecho' => $vigenteAlHecho,
            ];

            if (! $vigenteAlHecho) {
                $problematic[] = [
                    'element_id' => $row['element_id'],
                    'citation' => $article->citation,
                    'issue' => $version->effective_date > $factDate
                        ? 'no_vigente_aun_en_fecha_del_hecho'
                        : 'derogado_antes_de_la_fecha_del_hecho',
                ];
            }
        }

        return [
            'status' => empty($problematic) ? 'correcto' : 'alerta',
            'reason' => empty($problematic)
                ? 'Los artículos usados estaban vigentes en la fecha del hecho.'
                : 'Se detectaron elementos fundados en artículos fuera de vigencia respecto a la fecha del hecho.',
            'articles_checked' => $checked,
            'problematic_elements' => $problematic,
        ];
    }

    private function checkFuentesVerificadas(array $elementsAnalysis, $offenseElements): array
    {
        $unverified = [];

        foreach ($elementsAnalysis as $row) {
            $offenseElement = $offenseElements->get($row['element_id'] ?? null);
            $article = $offenseElement?->legalArticle;

            if ($article && ! $article->is_verified) {
                $unverified[] = [
                    'element_id' => $row['element_id'],
                    'citation' => $article->citation,
                ];
            }
        }

        return [
            'status' => empty($unverified) ? 'correcto' : 'bloqueante',
            'reason' => empty($unverified)
                ? 'Todos los artículos usados en el análisis tienen contenido verificado.'
                : 'El análisis se fundó en al menos un artículo cuyo contenido no ha sido verificado '
                    .'contra fuente oficial. La conclusión no debe considerarse confiable.',
            'unverified_elements' => $unverified,
        ];
    }

    private function checkManifestacionComoHechoProbado(CaseAnalysis $analysis, array $elementsAnalysis): array
    {
        $facts = CaseFact::where('case_analysis_id', $analysis->id)->get();
        $factsByContent = $facts->keyBy(fn (CaseFact $f) => $this->normalize($f->content));
        $flagged = [];

        foreach ($elementsAnalysis as $row) {
            if (($row['status'] ?? null) !== 'ACREDITADO') {
                continue;
            }

            $evidenceText = $row['evidence_found'] ?? null;
            if (! $evidenceText) {
                continue;
            }

            $fact = $factsByContent->get($this->normalize($evidenceText));

            if ($fact && $fact->information_type === 'MANIFESTACION') {
                $flagged[] = [
                    'element_id' => $row['element_id'],
                    'evidence_found' => $evidenceText,
                    'issue' => 'elemento_acreditado_solo_con_manifestacion_subjetiva',
                    'is_confirmed' => $fact->is_confirmed,
                ];
            }
        }

        return [
            'status' => empty($flagged) ? 'correcto' : 'alerta',
            'reason' => empty($flagged)
                ? 'Ningún elemento fue acreditado exclusivamente con una manifestación subjetiva.'
                : 'Se detectaron elementos acreditados apoyándose únicamente en una manifestación, '
                    .'sin evidencia, testimonio o dato técnico que la respalde.',
            'flagged_elements' => $flagged,
        ];
    }

    private function normalize(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }
}
