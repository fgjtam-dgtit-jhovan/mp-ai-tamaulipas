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

    public function handle(CaseAnalysisService $aiClient): void
    {
        try {
            $response = $aiClient->runAnalysis(
                $this->analysis->external_case_id,
                $this->analysis->external_offense_id,
                $this->factNarrative
            );

            $data = $response;

            $factRows = $this->factRows($data['facts'] ?? []);
            $this->analysis->facts()->delete();
            $this->analysis->facts()->createMany($factRows);
            $this->analysis->evidence()
                ->where('origin', 'ia')
                ->where('is_verified', false)
                ->delete();
            foreach ($this->evidenceRows($data['elements_analysis'] ?? []) as $evidenceRow) {
                $elementIds = $evidenceRow['element_ids'];
                unset($evidenceRow['element_ids']);

                $evidence = $this->analysis->evidence()->create($evidenceRow);
                $evidence->offenseElements()->sync($elementIds);
            }

            $this->analysis->update([
                'facts_breakdown' => ['narrative' => $this->factNarrative, 'facts' => $factRows],
                'elements_status' => $data['elements_analysis'] ?? [],
                'objectivity_audit' => $data['objectivity_audit'] ?? [],
                'suggested_diligences' => $data['suggested_diligences'] ?? [],
                'status' => 'reviewed',
                'error_message' => null,
            ]);

        } catch (\UnexpectedValueException|\InvalidArgumentException $exception) {
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
            ->filter(fn (array $fact): bool => filled($fact['content'] ?? null))
            ->map(fn (array $fact): array => [
                'information_type' => $fact['information_type'] ?? 'MANIFESTACION',
                'content' => trim($fact['content']),
                'source' => $fact['source'] ?? 'narrativa_de_la_carpeta',
                'procedural_relation' => $fact['procedural_relation'] ?? 'neutral',
            ])
            ->unique(fn (array $fact): string => $this->normalizedKey($fact['information_type'].'|'.$fact['content']))
            ->values()
            ->all();
    }

    private function evidenceRows(array $elementsAnalysis): array
    {
        return collect($elementsAnalysis)
            ->filter(fn (array $element): bool => filled($element['evidence_found'] ?? null))
            ->groupBy(fn (array $element): string => $this->normalizedKey($element['evidence_found']))
            ->map(function ($elements): array {
                $firstElement = $elements->first();
                $elementIds = $elements->pluck('element_id')->filter()->unique()->values()->all();

                return [
                    'offense_element_id' => $elementIds[0] ?? null,
                    'element_ids' => $elementIds,
                    'origin' => 'ia',
                    'evidence_type' => 'hecho_narrado',
                    'source' => 'narrativa_de_la_carpeta',
                    'evidence_date' => null,
                    'related_fact' => trim($firstElement['evidence_found']),
                    'authenticity_status' => 'pendiente',
                    'valuation_status' => 'pendiente',
                    'procedural_relation' => $this->proceduralRelation($firstElement['status'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizedKey(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($value)));
    }

    private function proceduralRelation(?string $status): string
    {
        return match ($status) {
            'ACREDITADO' => 'cargo',
            'CONTRADICTORIO' => 'descargo',
            default => 'neutral',
        };
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
