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
use Illuminate\Support\Collection;
use Throwable;

class ProcessCaseAnalysisJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public function backoff(): array
    {
        return [10, 30];
    }

    public function __construct(
        public CaseAnalysis $analysis,
        public string $factNarrative
    ) {}

    public function handle(
        CaseAnalysisService $aiClient,
        HypothesisEngine $hypothesisEngine,
        ObjectivityAuditEngine $objectivityEngine
    ): void {
        try {
            $response = $aiClient->runAnalysis(
                $this->analysis->external_case_id,
                $this->analysis->external_offense_id,
                $this->factNarrative,
                $this->analysis->fact_date,
            );

            $data = $response;

            $factRows = $this->factRows($data['facts'] ?? []);
            $this->analysis->facts()->delete();

            // Se crea uno por uno (no createMany) para poder capturar el
            // id real de BD de cada fact y mapearlo contra su id_llm.
            // Volumen bajo (máx. 8 hechos/caso), costo despreciable frente
            // a la trazabilidad ganada.
            $createdFacts = collect($factRows)->map(function (array $row) {
                $attributes = collect($row)->except('id_llm')->all();
                $fact = $this->analysis->facts()->create($attributes);

                return ['id_llm' => $row['id_llm'], 'model' => $fact];
            });

            $this->analysis->evidence()
                ->where('origin', 'ia')
                ->where('is_verified', false)
                ->delete();

            foreach ($this->evidenceRows($createdFacts, $data['elements_analysis'] ?? []) as $evidenceRow) {
                $elementIds = $evidenceRow['element_ids'];
                $factModel = $evidenceRow['fact_model'];
                unset($evidenceRow['element_ids'], $evidenceRow['fact_model']);

                $evidence = $this->analysis->evidence()->create($evidenceRow);
                $evidence->offenseElements()->sync($elementIds);

                // Backlink real: el hecho ahora sabe qué evidencia generó.
                if ($factModel) {
                    $factModel->update(['case_evidence_id' => $evidence->id]);
                }
            }

            $deterministicAudit = $objectivityEngine->evaluate($this->analysis, $data['elements_analysis'] ?? []);

            $this->analysis->update([
                'facts_breakdown' => [
                    'narrative' => $this->factNarrative,
                    'facts' => collect($factRows)
                        ->map(fn(array $row): array => collect($row)->except('id_llm')->all())
                        ->values()
                        ->all(),
                ],
                'elements_status' => $data['elements_analysis'] ?? [],
                'objectivity_audit' => array_merge(
                    $data['objectivity_audit'] ?? [],
                    ['deterministic_checks' => $deterministicAudit]
                ),
                'suggested_diligences' => $data['suggested_diligences'] ?? [],
                'status' => 'reviewed',
                'error_message' => null,
            ]);

            // Motor de Hipótesis: agrega el estado de completitud sobre el
            // análisis de elementos ya persistido.
            $hypothesisData = $hypothesisEngine->evaluate($this->analysis, $data['elements_analysis'] ?? []);
            $this->analysis->hypotheses()->delete();
            $this->analysis->hypotheses()->create($hypothesisData);
        } catch (\UnexpectedValueException | \InvalidArgumentException $exception) {
            $this->analysis->update([
                'status' => 'rejected',
                'error_message' => $exception->getMessage(),
            ]);

            return;
        } catch (Throwable $e) {
            throw $e;
        }
    }

    private function factRows(array $facts): array
    {
        return collect($facts)
            ->filter(fn(array $fact): bool => filled($fact['content'] ?? null))
            ->map(fn(array $fact): array => [
                'id_llm' => $fact['id'] ?? null,
                'information_type' => $fact['information_type'] ?? 'MANIFESTACION',
                'content' => trim($fact['content']),
                'source' => $fact['source'] ?? 'narrativa_de_la_carpeta',
                'procedural_relation' => $fact['procedural_relation'] ?? 'neutral',
                'is_confirmed' => $fact['is_confirmed'] ?? true,
            ])
            // unique()/values() ya no rompen nada aguas abajo: la
            // vinculación con elements_analysis se hace por id_llm,
            // no por posición dentro de este array.
            ->unique(fn(array $fact): string => $this->normalizedKey($fact['information_type'] . '|' . $fact['content']))
            ->values()
            ->all();
    }

    /**
     * Construye los registros de evidencia a partir de los hechos YA
     * CREADOS en BD ($createdFacts trae el modelo real + su id_llm).
     * El vínculo con elements_analysis se hace por id_llm
     * (supporting_fact_id que regresa el ai-service), nunca por
     * posición de array — un hecho eliminado por duplicado en
     * factRows() ya no desalinea nada aguas abajo. Solo los tipos
     * EVIDENCIA, TESTIMONIO y DATO_TECNICO se convierten en registros
     * de evidencia — una MANIFESTACION no es evidencia formal (7.2).
     */
    private function evidenceRows(Collection $createdFacts, array $elementsAnalysis): array
    {
        $tiposConsideradosEvidencia = ['EVIDENCIA', 'TESTIMONIO', 'DATO_TECNICO'];

        $elementIdsPorFactId = collect($elementsAnalysis)
            ->filter(fn(array $el): bool => ($el['supporting_fact_id'] ?? null) !== null)
            ->groupBy('supporting_fact_id')
            ->map(fn($group) => $group->pluck('element_id')->filter()->unique()->values()->all());

        return $createdFacts
            ->filter(fn(array $entry): bool => in_array($entry['model']->information_type, $tiposConsideradosEvidencia, true))
            ->map(function (array $entry) use ($elementIdsPorFactId): array {
                $fact = $entry['model'];
                $elementIds = $elementIdsPorFactId->get($entry['id_llm'], []);

                return [
                    'fact_model' => $fact,
                    'element_ids' => $elementIds,
                    'offense_element_id' => $elementIds[0] ?? null,
                    'origin' => 'ia',
                    'evidence_type' => match ($fact->information_type) {
                        'EVIDENCIA' => 'documental_o_material',
                        'TESTIMONIO' => 'testimonial',
                        'DATO_TECNICO' => 'pericial',
                        default => 'hecho_narrado',
                    },
                    'source' => $fact->source ?? 'narrativa_de_la_carpeta',
                    'evidence_date' => null,
                    'related_fact' => trim($fact->content),
                    'authenticity_status' => 'pendiente',
                    'valuation_status' => 'pendiente',
                    'procedural_relation' => $fact->procedural_relation ?? 'neutral',
                ];
            })
            ->values()
            ->all();
    }

    private function normalizedKey(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }

    public function failed(Throwable $exception): void
    {
        $this->analysis->update([
            'status' => 'rejected',
            'error_message' => $this->userMessage($exception),
        ]);
    }

    private function userMessage(Throwable $exception): string
    {
        if ($exception instanceof \InvalidArgumentException) {
            return 'El servicio de inteligencia artificial no está configurado. Contacta al administrador.';
        }

        if (str_contains($exception->getMessage(), 'MP-IA Engine')) {
            return 'El servicio de inteligencia artificial no respondió correctamente después de varios intentos.';
        }

        return 'No fue posible completar el análisis. Intenta nuevamente o contacta al administrador.';
    }
}
