<?php

namespace Database\Seeders;

use App\Models\LegalArticle;
use App\Models\LegalDocument;
use App\Models\LegalVersion;
use Illuminate\Database\Seeder;

class LesionesArticlesSeeder extends Seeder
{
    /**
     * Carga el artículo base del delito de LESIONES (ID_DLTO 15 en el
     * catálogo).
     *
     * FUENTE VERIFICADA: texto del Art. 319 confirmado de forma
     * cruzada contra dos sentencias públicas del Tribunal Electrónico
     * de Tamaulipas que lo citan de forma literal (Toca Penal 21/2020
     * y otro toca posterior), además de la compilación del Congreso.
     * https://www.tribunalelectronico.gob.mx/TE/AccesoLibre/sentenciaspublicas/SentenciaPublica?ID=128306
     *
     * PENDIENTE: los artículos 320–327 (calificativas: peligro de vida,
     * lesiones entre parientes, riña, etc.) NO están cargados todavía —
     * solo la definición base. Complétalos siguiendo este mismo patrón
     * de verificación antes de usarlos en producción.
     */
    public function run(): void
    {
        $document = LegalDocument::where('title', 'Código Penal para el Estado de Tamaulipas')->first();

        if (! $document) {
            $this->command->error('Corre primero LegalCoreLevel1Seeder — no existe el documento base.');

            return;
        }

        // Reutiliza la misma versión (compilación general) que robo simple.
        $version = LegalVersion::firstOrCreate(
            [
                'legal_document_id' => $document->id,
                'version_label' => 'Reforma P.O. No. 42, 19-nov-2025',
            ],
            [
                'publication_date' => '2025-11-19',
                'effective_date' => '2025-11-19',
                'repealed_date' => null,
                'official_source_url' => 'https://www.congresotamaulipas.gob.mx/Parlamentario/Archivos/Codigos/Codigo%20Penal%20para%20el%20Estado%20de%20Tamaulipas%2019%20de%20noviembre%20de%202026-1.pdf',
            ]
        );

        LegalArticle::firstOrCreate(
            [
                'legal_version_id' => $version->id,
                'article_number' => '319',
                'fraction' => null,
            ],
            [
                'legal_version_id' => $version->id,
                'article_number' => '319',
                'fraction' => null,
                'content' => 'Comete el delito de lesiones, el que infiera a otro un daño que deje en '
                    .'su cuerpo un vestigio o altere su salud física o mental.',
                'display_order' => 1,
            ]
        );

        $this->command->warn(
            'Lesiones: solo el Art. 319 (definición base) fue cargado y verificado. '
            .'Faltan los artículos 320-327 (calificativas) — no los inventes, complétalos verificando fuente.'
        );
    }
}
