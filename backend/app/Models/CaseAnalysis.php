<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaseAnalysis extends Model
{
    protected $table = 'case_analyses';

    protected $fillable = [
        'external_case_id',
        'external_offense_id',
        'user_id',
        'facts_breakdown',
        'elements_status',
        'objectivity_audit',
        'suggested_diligences',
        'status',
    ];

    protected $casts = [
        'facts_breakdown' => 'array',
        'elements_status' => 'array',
        'objectivity_audit' => 'array',
        'suggested_diligences' => 'array',
        'external_offense_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}





