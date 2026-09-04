<?php

namespace App\Services;

use App\Models\CaseAnalysis;
use Illuminate\Support\Collection;

class CaseAnalysisAuditService
{
    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $review
     */
    public function recordHumanReview(CaseAnalysis $analysis, ?int $userId, array $before, array $review): void
    {
        $changes = [
            'element_statuses' => $this->elementStatusChanges(
                $before['elements_status'] ?? [],
                $review['elements_status'] ?? [],
            ),
            'diligences' => $this->diligenceChanges(
                $before['suggested_diligences'] ?? [],
                $review['suggested_diligences'] ?? [],
            ),
            'evidence' => $this->evidenceChanges(
                $before['evidence'] ?? collect(),
                $review['evidence'] ?? [],
            ),
            'status' => $before['status'] === ($review['status'] ?? null)
                ? null
                : ['from' => $before['status'], 'to' => $review['status']],
        ];

        $analysis->audits()->create([
            'user_id' => $userId,
            'action' => 'human_review_saved',
            'changes' => $changes,
            'reason' => $review['review_note'] ?? null,
        ]);
    }

    private function elementStatusChanges(array $before, array $after): array
    {
        $beforeByElement = collect($before)->keyBy('element_id');

        return collect($after)
            ->filter(function (array $element) use ($beforeByElement): bool {
                return ($beforeByElement->get($element['element_id'])['status'] ?? null) !== ($element['status'] ?? null);
            })
            ->map(function (array $element) use ($beforeByElement): array {
                return [
                    'element_id' => $element['element_id'] ?? null,
                    'from' => $beforeByElement->get($element['element_id'])['status'] ?? null,
                    'to' => $element['status'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function diligenceChanges(array $before, array $after): array
    {
        return collect($after)
            ->values()
            ->filter(function (array $diligence, int $index) use ($before): bool {
                return ($before[$index]['accepted'] ?? true) !== ($diligence['accepted'] ?? true);
            })
            ->map(function (array $diligence, int $index) use ($before): array {
                return [
                    'index' => $index,
                    'action' => $diligence['action'] ?? null,
                    'from' => $before[$index]['accepted'] ?? true,
                    'to' => $diligence['accepted'] ?? true,
                ];
            })
            ->values()
            ->all();
    }

    private function evidenceChanges(Collection $before, array $after): array
    {
        $beforeById = $before->keyBy('id');

        return collect($after)
            ->filter(function (array $evidence) use ($beforeById): bool {
                $original = $beforeById->get($evidence['id']);

                return $original && collect($evidence)
                    ->except('id')
                    ->contains(fn (mixed $value, string $field): bool => $this->originalEvidenceValue($original, $field) != $value);
            })
            ->map(function (array $evidence) use ($beforeById): array {
                $original = $beforeById->get($evidence['id']);

                return [
                    'evidence_id' => $original->id,
                    'fields' => collect($evidence)
                        ->except('id')
                        ->filter(fn (mixed $value, string $field): bool => $this->originalEvidenceValue($original, $field) != $value)
                        ->keys()
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function originalEvidenceValue(object $evidence, string $field): mixed
    {
        if ($field === 'evidence_date') {
            return $evidence->evidence_date?->toDateString();
        }

        return $evidence->{$field};
    }
}
