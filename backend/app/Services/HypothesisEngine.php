<?php

namespace App\Services;

use App\Models\CaseAnalysis;
use App\Models\OffenseElement;

class HypothesisEngine
{
    /**
     * Calcula completitud de la hipótesis jurídica a partir del análisis
     * de elementos YA GENERADO (Fase 1b). No hace llamadas al LLM: la
     * completitud debe ser un dato verificable y reproducible, no una
     * apreciación redactada por el modelo (sección 7.4 del anteproyecto).
     */
    public function evaluate(CaseAnalysis $analysis, array $elementsAnalysis): array
    {
        // Catálogo REAL de elementos del delito — no los que el LLM
        // decidió regresar. Así detectamos si el LLM omitió evaluar
        // algún elemento por completo, en vez de asumir silenciosamente
        // que "no evaluado" equivale a "no existe".
        $catalogElements = OffenseElement::where('external_offense_id', $analysis->external_offense_id)
            ->get()
            ->keyBy('id');

        $total = $catalogElements->count();
        $required = $catalogElements->where('is_required', true)->count();

        // Si el LLM regresó el mismo element_id dos veces, solo se
        // considera la primera aparición — evita inflar conteos.
        $analysisByElement = collect($elementsAnalysis)
            ->filter(fn (array $row) => isset($row['element_id']))
            ->unique('element_id')
            ->keyBy('element_id');

        $accreditedCount = 0;
        $missingCount = 0;
        $contradictoryCount = 0;
        $notEvaluatedCount = 0;
        $accreditedRequired = 0;
        $requiredContradictions = 0;
        $missingRequired = [];
        $notEvaluatedRequired = [];

        foreach ($catalogElements as $elementId => $element) {
            $row = $analysisByElement->get($elementId);
            $isRequired = (bool) $element->is_required;

            if (! $row) {
                // Elemento del catálogo que el LLM nunca evaluó. Se
                // reporta aparte de FALTANTE: esto es una falla del
                // pipeline de análisis, no un vacío sustantivo del caso.
                $notEvaluatedCount++;
                if ($isRequired) {
                    $notEvaluatedRequired[] = [
                        'element_id' => $elementId,
                        'name' => $element->name,
                    ];
                }
                continue;
            }

            $status = $row['status'] ?? null;

            match ($status) {
                'ACREDITADO' => $accreditedCount++,
                'FALTANTE' => $missingCount++,
                'CONTRADICTORIO' => $contradictoryCount++,
                default => $notEvaluatedCount++,
            };

            if ($status === 'ACREDITADO' && $isRequired) {
                $accreditedRequired++;
            }

            if ($status === 'CONTRADICTORIO' && $isRequired) {
                $requiredContradictions++;
            }

            if (in_array($status, ['FALTANTE', 'CONTRADICTORIO'], true) && $isRequired) {
                $missingRequired[] = [
                    'element_id' => $elementId,
                    'name' => $element->name,
                    'status' => $status,
                    'reason' => $row['missing_reason'] ?? null,
                ];
            }
        }

        $completeness = $required > 0
            ? round(($accreditedRequired / $required) * 100, 2)
            : 0.0;

        $hasRequiredContradictions = $requiredContradictions > 0;
        $hasUnevaluatedRequired = count($notEvaluatedRequired) > 0;
        $isComplete = $required > 0 && $accreditedRequired === $required;

        $status = match (true) {
            $required === 0 => 'insuficiente', // el delito no tiene elementos obligatorios configurados
            $hasUnevaluatedRequired => 'evaluacion_incompleta', // falla de pipeline, no del caso
            $hasRequiredContradictions => 'con_contradicciones',
            $isComplete => 'completa',
            default => 'incompleta',
        };

        return [
            'external_offense_id' => $analysis->external_offense_id,
            'total_elements' => $total,
            'required_elements' => $required,
            'accredited_count' => $accreditedCount,
            'missing_count' => $missingCount,
            'contradictory_count' => $contradictoryCount,
            'not_evaluated_count' => $notEvaluatedCount,
            'completeness_percentage' => $completeness,
            'status' => $status,
            // can_conclude EXCLUSIVAMENTE cuando de verdad no falta nada
            // por evaluar — nunca cuando el pipeline dejó huecos.
            'can_conclude' => $status === 'completa',
            'missing_required_elements' => $missingRequired,
            'not_evaluated_required_elements' => $notEvaluatedRequired,
        ];
    }
}
