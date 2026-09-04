<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCaseAnalysisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'elements_status' => ['required', 'array'],
            'suggested_diligences' => ['required', 'array'],
            'evidence' => ['sometimes', 'array'],
            'evidence.*.id' => ['required', 'integer', 'exists:case_evidence,id'],
            'evidence.*.evidence_type' => ['required', 'string', 'max:100'],
            'evidence.*.source' => ['required', 'string', 'max:255'],
            'evidence.*.evidence_date' => ['nullable', 'date'],
            'evidence.*.related_fact' => ['required', 'string'],
            'evidence.*.authenticity_status' => ['required', 'string', 'in:pendiente,autentica,no_autentica,por_verificar'],
            'evidence.*.valuation_status' => ['required', 'string', 'in:pendiente,relevante,no_relevante,valorada'],
            'evidence.*.procedural_relation' => ['required', 'string', 'in:cargo,descargo,neutral'],
            'status' => ['required', 'string', 'in:draft,reviewed,approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
