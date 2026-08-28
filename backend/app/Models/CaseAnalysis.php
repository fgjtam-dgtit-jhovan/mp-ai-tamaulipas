<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseAnalysis extends Model
{
    protected $table = 'case_analyses';

    protected $fillable = [
        'external_case_id',
        'external_offense_id',
        'fact_date',
        'user_id',
        'facts_breakdown',
        'elements_status',
        'objectivity_audit',
        'suggested_diligences',
        'status',
        'error_message',
    ];

    protected $casts = [
        'facts_breakdown' => 'array',
        'elements_status' => 'array',
        'objectivity_audit' => 'array',
        'suggested_diligences' => 'array',
        'external_offense_id' => 'integer',
        'user_id' => 'integer',
        'fact_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(CaseEvidence::class, 'case_analysis_id');
    }

    public function facts(): HasMany
    {
        return $this->hasMany(CaseFact::class, 'case_analysis_id');
    }

    public function hypotheses(): HasMany
    {
        return $this->hasMany(CaseHypothesis::class, 'case_analysis_id');
    }
}
