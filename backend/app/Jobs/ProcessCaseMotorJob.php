<?php

namespace App\Jobs;

use App\Models\CaseAnalysis;
use App\Services\CaseAnalysisService;
use App\Services\HypothesisEngine;
use App\Services\ObjectivityAuditEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessCaseMotorJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public CaseAnalysis $analysis,
        public string $motor,
        public string $factNarrative
    ) {}

    public function handle(
        CaseAnalysisService $aiClient,
        HypothesisEngine $hypothesisEngine,
        ObjectivityAuditEngine $objectivityEngine
    ): void {
        try {
            $this->markMotor('draft');
            $narrative = $this->cleanNarrative($this->factNarrative);
            $facts = collect($this->analysis->facts_breakdown['facts'] ?? [])
                ->values()
                ->map(fn (array $fact, int $index): array => [
                    ...$fact,
                    'id' => $fact['id'] ?? "f{$index}",
                ])
                ->all();
            $elements = $this->analysis->elements_status ?? [];

            if ($this->motor === 'hipotesis') {
                $this->analysis->hypotheses()->delete();
                $this->analysis->hypotheses()->create($hypothesisEngine->evaluate($this->analysis, $elements));
            } elseif ($this->motor === 'registro') {
                $this->persistEvidence($facts, $elements);
            } else {
                $data = $aiClient->runMotor(
                    $this->analysis->external_case_id,
                    $this->analysis->external_offense_id,
                    $narrative,
                    $this->motor,
                    $facts,
                    $elements,
                    $this->analysis->fact_date,
                );

                if (array_key_exists('facts', $data)) {
                    $this->persistFacts($data['facts']);
                    $facts = $this->analysis->fresh()->facts_breakdown['facts'] ?? [];
                }
                if (array_key_exists('elements_analysis', $data)) {
                    $this->analysis->update(['elements_status' => $data['elements_analysis']]);
                    $elements = $data['elements_analysis'];
                }
                if (array_key_exists('objectivity_audit', $data)) {
                    $this->analysis->update(['objectivity_audit' => $data['objectivity_audit']]);
                }
                if (array_key_exists('suggested_diligences', $data)) {
                    $this->analysis->update(['suggested_diligences' => $data['suggested_diligences']]);
                }
                if ($this->motor === 'matriz') {
                    $this->persistEvidence($facts, $elements);
                }
            }

            $this->markMotor('completed');
        } catch (Throwable $exception) {
            $this->markMotor('failed', $exception->getMessage());
        }
    }

    private function persistFacts(array $facts): void
    {
        $rows = collect($facts)
            ->filter(fn (array $fact): bool => filled($fact['content'] ?? null))
            ->map(fn (array $fact): array => [
                'id_llm' => $fact['id'] ?? null,
                'information_type' => $fact['information_type'] ?? 'MANIFESTACION',
                'content' => trim($fact['content']),
                'source' => $fact['source'] ?? 'narrativa_de_la_carpeta',
                'procedural_relation' => $fact['procedural_relation'] ?? 'neutral',
                'is_confirmed' => $fact['is_confirmed'] ?? true,
            ])
            ->values();

        $this->analysis->facts()->delete();
        foreach ($rows as $row) {
            $this->analysis->facts()->create(collect($row)->except('id_llm')->all());
        }
        $this->analysis->update([
            'facts_breakdown' => [
                'narrative' => $this->cleanNarrative($this->factNarrative),
                'facts' => $rows->map(fn (array $row): array => [
                    'id' => $row['id_llm'],
                    ...collect($row)->except('id_llm')->all(),
                ])->all(),
            ],
        ]);
    }

    private function persistEvidence(array $facts, array $elements): void
    {
        $this->analysis->evidence()
            ->where('origin', 'ia')
            ->where(function ($query): void {
                $query->where('is_verified', false)->orWhereNull('is_verified');
            })
            ->delete();

        foreach ($facts as $fact) {
            $elementIds = collect($elements)
                ->where('supporting_fact_id', $fact['id'] ?? null)
                ->pluck('element_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            $isFormalEvidence = in_array($fact['information_type'] ?? null, ['EVIDENCIA', 'TESTIMONIO', 'DATO_TECNICO'], true);
            if (! $isFormalEvidence && empty($elementIds)) {
                continue;
            }

            $evidence = $this->analysis->evidence()->create([
                'offense_element_id' => $elementIds[0] ?? null,
                'origin' => 'ia',
                'evidence_type' => match ($fact['information_type']) {
                    'EVIDENCIA' => 'documental_o_material',
                    'TESTIMONIO' => 'testimonial',
                    'MANIFESTACION' => 'manifestacion_narrativa',
                    default => 'pericial',
                },
                'source' => $fact['source'] ?? 'narrativa_de_la_carpeta',
                'related_fact' => trim($fact['content']),
                'authenticity_status' => 'pendiente',
                'valuation_status' => 'pendiente',
                'procedural_relation' => $fact['procedural_relation'] ?? 'neutral',
            ]);
            $evidence->offenseElements()->sync($elementIds);
        }
    }

    private function markMotor(string $status, ?string $error = null): void
    {
        $this->analysis->refresh();
        $motorStatus = $this->analysis->motor_status ?? [];
        $motorStatus[$this->motor] = [
            'status' => $status,
            'error' => $error,
            'updated_at' => now()->toISOString(),
        ];
        $this->analysis->update([
            'motor_status' => $motorStatus,
            'status' => $status === 'draft' ? 'draft' : 'reviewed',
            'error_message' => $error,
        ]);
    }

    private function cleanNarrative(string $narrative): string
    {
        $narrative = preg_replace('/^\s*DESCRIPCION_HECHOS\s*>\s*/iu', '', $narrative) ?? $narrative;

        return ltrim($narrative, " \t\n\r\0\x0B,;:-");
    }
}
