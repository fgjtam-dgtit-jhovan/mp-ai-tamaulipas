<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseFact extends Model
{
    protected $table = 'case_facts';

    protected $fillable = [
        'case_analysis_id',
        'information_type',
        'content',
        'source',
        'procedural_relation',
        'case_evidence_id',
    ];

    protected $casts = [
        'case_analysis_id' => 'integer',
        'case_evidence_id' => 'integer',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(CaseAnalysis::class, 'case_analysis_id');
    }

    public function evidence(): BelongsTo
    {
        return $this->belongsTo(CaseEvidence::class, 'case_evidence_id');
    }
}
