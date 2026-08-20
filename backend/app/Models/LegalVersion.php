<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalVersion extends Model
{
    protected $fillable = [
        'legal_document_id',
        'version_label',
        'publication_date',
        'effective_date',
        'repealed_date',
        'official_source_url',
    ];

    protected $casts = [
        'publication_date' => 'date',
        'effective_date' => 'date',
        'repealed_date' => 'date',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'legal_document_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(LegalArticle::class);
    }

    public function isCurrentlyInForce(): bool
    {
        return $this->effective_date->lte(now())
            && (is_null($this->repealed_date) || $this->repealed_date->gt(now()));
    }
}
