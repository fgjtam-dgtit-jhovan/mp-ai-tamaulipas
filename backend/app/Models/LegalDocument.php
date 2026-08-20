<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocument extends Model
{
    protected $fillable = [
        'title',
        'type',
        'jurisdiction',
        'official_source_url',
        'mvp_level',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(LegalVersion::class);
    }

    /**
     * Versión vigente hoy: la que ya entró en vigor y aún no ha sido derogada.
     */
    public function currentVersion(): ?LegalVersion
    {
        return $this->versions()
            ->whereDate('effective_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('repealed_date')
                  ->orWhereDate('repealed_date', '>', now());
            })
            ->orderByDesc('effective_date')
            ->first();
    }

    /**
     * Versión vigente en una fecha jurídicamente relevante específica
     * (ej. la fecha en que ocurrió el hecho denunciado).
     */
    public function versionAt(\DateTimeInterface $date): ?LegalVersion
    {
        return $this->versions()
            ->whereDate('effective_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('repealed_date')
                  ->orWhereDate('repealed_date', '>', $date);
            })
            ->orderByDesc('effective_date')
            ->first();
    }
}
