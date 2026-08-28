<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LegalArticle extends Model
{
    protected $fillable = [
        'legal_version_id',
        'article_number',
        'fraction',
        'content',
        'display_order',
        'is_verified',
    ];

    public function version(): BelongsTo
    {
        return $this->belongsTo(LegalVersion::class, 'legal_version_id');
    }

    /**
     * Referencia legible para mostrar en el Panel del MP, ej.
     * "Art. 308, fracción II — Código Penal para el Estado de Tamaulipas".
     */
    public function getCitationAttribute(): string
    {
        $doc = $this->version->document;
        $ref = "Art. {$this->article_number}";
        if ($this->fraction) {
            $ref .= ", fracción {$this->fraction}";
        }

        return "{$ref} — {$doc->title} ({$this->version->version_label})";
    }
}
