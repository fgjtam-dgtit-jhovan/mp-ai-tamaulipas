<?php

namespace Database\Seeders;

use App\Models\LegalDocument;
use Illuminate\Database\Seeder;

class LegalCoreLevel1Seeder extends Seeder
{
    /**
     * Crea los 4 documentos jurídicos del Nivel 1 definidos en la
     * sección 7.1 del anteproyecto. No incluye versiones ni artículos
     * todavía — eso se carga aparte (manualmente al inicio, según el
     * documento) una vez que definas el texto exacto de cada norma.
     */
    public function run(): void
    {
        $documents = [
            [
                'title' => 'Constitución Política de los Estados Unidos Mexicanos',
                'type' => 'constitución',
                'jurisdiction' => 'federal',
                'mvp_level' => 1,
            ],
            [
                'title' => 'Código Nacional de Procedimientos Penales',
                'type' => 'código',
                'jurisdiction' => 'federal',
                'mvp_level' => 1,
            ],
            [
                'title' => 'Código Penal para el Estado de Tamaulipas',
                'type' => 'código',
                'jurisdiction' => 'Tamaulipas',
                'mvp_level' => 1,
            ],
            [
                'title' => 'Ley Orgánica de la Fiscalía General de Justicia del Estado de Tamaulipas',
                'type' => 'ley orgánica',
                'jurisdiction' => 'Tamaulipas',
                'mvp_level' => 1,
            ],
        ];

        foreach ($documents as $doc) {
            LegalDocument::firstOrCreate(
                ['title' => $doc['title']],
                $doc
            );
        }
    }
}
