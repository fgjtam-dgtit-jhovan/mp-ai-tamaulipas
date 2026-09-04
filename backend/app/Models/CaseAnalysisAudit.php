<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaseAnalysisAudit extends Model
{
    protected $fillable = [
        'case_analysis_id',
        'user_id',
        'action',
        'changes',
        'reason',
    ];

    protected $casts = [
        'case_analysis_id' => 'integer',
        'user_id' => 'integer',
        'changes' => 'array',
    ];

    public function analysis(): BelongsTo
    {
        return $this->belongsTo(CaseAnalysis::class, 'case_analysis_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
