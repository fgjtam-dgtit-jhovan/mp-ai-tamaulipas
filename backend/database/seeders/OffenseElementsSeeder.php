<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OffenseElementsSeeder extends Seeder
{
    public function run(): void
    {
        // Presuponiendo que tienes el Artículo del Robo Simple en LegalCore con ID = 1
        $roboArticleId = 1;

        DB::table('offense_elements')->insert([
            // DELITO: 107 (ROBO SIMPLE)
            [
                'external_offense_id'   => 107,
                'legal_article_id'      => $roboArticleId,
                'element_type'          => 'objetivo',
                'name'                  => 'Apoderamiento',
                'verification_criteria' => 'Acción de remoción, sustracción o toma de posesión material del bien.',
                'is_required'           => true,
                'display_order'         => 1,
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
            [
                'external_offense_id'   => 107,
                'legal_article_id'      => $roboArticleId,
                'element_type'          => 'objetivo',
                'name'                  => 'Cosa Mueble',
                'verification_criteria' => 'Objeto material tangible susceptible de ser trasladado.',
                'is_required'           => true,
                'display_order'         => 2,
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
            [
                'external_offense_id'   => 107,
                'legal_article_id'      => $roboArticleId,
                'element_type'          => 'normativo',
                'name'                  => 'Ajenuidad',
                'verification_criteria' => 'Acreditación de titularidad o propiedad de un tercero distinto al sujeto activo.',
                'is_required'           => true,
                'display_order'         => 3,
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
            [
                'external_offense_id'   => 107,
                'legal_article_id'      => $roboArticleId,
                'element_type'          => 'subjetivo',
                'name'                  => 'Falta de Consentimiento',
                'verification_criteria' => 'Declaración o indicio de que la toma del bien fue sin autorización expresa o implícita.',
                'is_required'           => true,
                'display_order'         => 4,
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
        ]);
    }
}
