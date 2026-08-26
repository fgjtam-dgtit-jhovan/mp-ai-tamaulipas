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
        $elementIds = collect($elementsAnalysis)->pluck('element_id')->filter()->unique()->values();
        $elements = OffenseElement::whereIn('id', $elementIds)->get()->keyBy('id');

        $total = $elements->count();
        $required = $elements->where('is_required', true)->count();

        $accreditedCount = 0;
        $missingCount = 0;
        $contradictoryCount = 0;
        $accreditedRequired = 0;
        $missingRequired = [];

        foreach ($elementsAnalysis as $row) {
            $element = $elements->get($row['element_id'] ?? null);
            $isRequired = $element?->is_required ?? false;

            match ($row['status'] ?? null) {
                'ACREDITADO' => $accreditedCount++,
                'FALTANTE' => $missingCount++,
                'CONTRADICTORIO' => $contradictoryCount++,
                default => null,
            };

            if (($row['status'] ?? null) === 'ACREDITADO' && $isRequired) {
                $accreditedRequired++;
            }

            if (in_array($row['status'] ?? null, ['FALTANTE', 'CONTRADICTORIO'], true) && $isRequired) {
                $missingRequired[] = [
                    'element_id' => $row['element_id'],
                    'name' => $element?->name,
                    'status' => $row['status'],
                    'reason' => $row['missing_reason'] ?? null,
                ];
            }
        }

        $completeness = $required > 0
            ? round(($accreditedRequired / $required) * 100, 2)
            : 0.0;

        $hasContradictions = $contradictoryCount > 0;
        $isComplete = $completeness === 100.0 && ! $hasContradictions;

        $status = match (true) {
            $required === 0 => 'insuficiente', // el delito no tiene elementos obligatorios configurados
            $hasContradictions => 'con_contradicciones',
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
            'completeness_percentage' => $completeness,
            'status' => $status,
            'can_conclude' => $status === 'completa',
            'missing_required_elements' => $missingRequired,
        ];
    }
}
