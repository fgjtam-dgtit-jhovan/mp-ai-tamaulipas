<?php

namespace Database\Seeders;

use App\Models\LegalArticle;
use App\Models\LegalDocument;
use App\Models\LegalVersion;
use Illuminate\Database\Seeder;

class RoboSimplePilotSeeder extends Seeder
{
    /**
     * Carga el delito piloto "robo simple" dentro del Código Penal para
     * el Estado de Tamaulipas.
     *
     * FUENTE VERIFICADA: texto tomado del documento de consulta oficial
     * del Congreso de Tamaulipas y confirmado contra citas textuales en
     * sentencias públicas del Tribunal Electrónico de Tamaulipas.
     * https://www.congresotamaulipas.gob.mx/Parlamentario/Archivos/Codigos/
     *
     * IMPORTANTE: la fracción II del artículo 402 quedó marcada como
     * PENDIENTE porque no se encontró su texto verbatim en una fuente
     * oficial confiable durante esta carga. NO uses este seeder en
     * producción sin completar y verificar esa fracción (y sin volver
     * a cotejar TODO el contenido) contra el Periódico Oficial vigente.
     */
    public function run(): void
    {
        $document = LegalDocument::where('title', 'Código Penal para el Estado de Tamaulipas')->first();

        if (! $document) {
            $this->command->error('Corre primero LegalCoreLevel1Seeder — no existe el documento base.');

            return;
        }

        $version = LegalVersion::firstOrCreate(
            [
                'legal_document_id' => $document->id,
                'version_label' => 'Reforma P.O. No. 42, 19-nov-2025',
            ],
            [
                'publication_date' => '2025-11-19',
                'effective_date' => '2025-11-19',
                'repealed_date' => null, // vigente al momento de esta carga — VERIFICAR antes de usar en producción
                'official_source_url' => 'https://www.congresotamaulipas.gob.mx/Parlamentario/Archivos/Codigos/Codigo%20Penal%20para%20el%20Estado%20de%20Tamaulipas%2019%20de%20noviembre%20de%202026-1.pdf',
            ]
        );

        $articles = [
            [
                'article_number' => '399',
                'fraction' => null,
                'content' => 'Comete el delito de robo, el que se apodera de una cosa mueble ajena.',
                'display_order' => 1,
            ],
            [
                'article_number' => '402',
                'fraction' => 'I',
                'content' => 'Cuando el valor de lo robado no exceda de cien veces el valor diario de la '
                    .'Unidad de Medida y Actualización, se impondrá una sanción de dos meses a dos años de '
                    .'prisión y multa de cinco a cuarenta veces el valor diario de la Unidad de Medida y '
                    .'Actualización.',
                'display_order' => 2,
            ],
            [
                'article_number' => '402',
                'fraction' => 'II',
                'content' => '[PENDIENTE DE VERIFICACIÓN — no se encontró el texto verbatim de esta fracción '
                    .'en una fuente oficial confiable durante esta carga. Consultar el Periódico Oficial o el '
                    .'documento de consulta del Congreso de Tamaulipas antes de usar este artículo en producción.]',
                'display_order' => 3,
            ],
            [
                'article_number' => '402',
                'fraction' => 'III',
                'content' => 'Cuando excediere de doscientos y sea menor de quinientas veces el valor diario '
                    .'de la Unidad de Medida y Actualización, la sanción será de seis a doce años de prisión '
                    .'y multa de ochenta a ciento cuarenta veces el valor diario de la Unidad de Medida y '
                    .'Actualización;',
                'display_order' => 4,
            ],
            [
                'article_number' => '402',
                'fraction' => 'IV',
                'content' => 'Cuando el valor de lo robado exceda de quinientas veces el valor diario de la '
                    .'Unidad de Medida y Actualización, se impondrá una sanción de doce a quince años de '
                    .'prisión y multa de ciento cuarenta a ciento ochenta veces el valor diario de la Unidad '
                    .'de Medida y Actualización.',
                'display_order' => 5,
            ],
        ];

        foreach ($articles as $article) {
            LegalArticle::firstOrCreate(
                [
                    'legal_version_id' => $version->id,
                    'article_number' => $article['article_number'],
                    'fraction' => $article['fraction'],
                ],
                $article + ['legal_version_id' => $version->id]
            );
        }

        $this->command->warn(
            'Robo simple cargado (Art. 399 y 402 fracciones I, III, IV verificados). '
            .'La fracción II quedó pendiente — complétala manualmente contra la fuente oficial.'
        );
    }
}
