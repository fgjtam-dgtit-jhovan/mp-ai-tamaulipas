<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OffenseElement extends Model
{
    protected $fillable = [
        'external_offense_id',
        'legal_article_id',
        'element_type',
        'name',
        'verification_criteria',
        'is_required',
        'display_order',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'external_offense_id' => 'integer',
        'legal_article_id' => 'integer',
    ];

    public function legalArticle(): BelongsTo
    {
        return $this->belongsTo(LegalArticle::class);
    }
}
