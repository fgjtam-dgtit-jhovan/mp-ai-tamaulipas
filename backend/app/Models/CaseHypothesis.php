<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseHypothesis extends Model
{
    protected $table = 'case_hypotheses';

    protected $fillable = [
        'case_analysis_id',
        'external_offense_id',
        'total_elements',
        'required_elements',
        'accredited_count',
        'missing_count',
        'contradictory_count',
        'completeness_percentage',
        'status',
        'can_conclude',
        'missing_required_elements',
        'not_evaluated_count',
        'not_evaluated_required_elements',
    ];

    protected $casts = [
        'case_analysis_id' => 'integer',
        'external_offense_id' => 'integer',
        'completeness_percentage' => 'decimal:2',
        'can_conclude' => 'boolean',
        'missing_required_elements' => 'array',
        'not_evaluated_count' => 'integer',
        'not_evaluated_required_elements' => 'array',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(CaseAnalysis::class, 'case_analysis_id');
    }
}
