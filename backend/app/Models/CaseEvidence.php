<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CaseEvidence extends Model
{
    protected $table = 'case_evidence';

    protected $fillable = [
        'case_analysis_id',
        'origin',
        'offense_element_id',
        'evidence_type',
        'source',
        'evidence_date',
        'related_fact',
        'authenticity_status',
        'valuation_status',
        'is_verified',
        'reviewed_by',
        'reviewed_at',
        'procedural_relation',
    ];

    protected $casts = [
        'case_analysis_id' => 'integer',
        'offense_element_id' => 'integer',
        'evidence_date' => 'date',
        'is_verified' => 'boolean',
        'reviewed_by' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(CaseAnalysis::class, 'case_analysis_id');
    }

    public function offenseElement(): BelongsTo
    {
        return $this->belongsTo(OffenseElement::class);
    }

    public function offenseElements(): BelongsToMany
    {
        return $this->belongsToMany(OffenseElement::class, 'case_evidence_offense_elements');
    }
}
