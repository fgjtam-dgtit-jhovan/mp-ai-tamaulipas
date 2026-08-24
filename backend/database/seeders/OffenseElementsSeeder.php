<?php

namespace Database\Seeders;

use App\Models\LegalArticle;
use App\Models\OffenseElement;
use Illuminate\Database\Seeder;

class OffenseElementsSeeder extends Seeder
{
    public function run(): void
    {
        $roboArticle = LegalArticle::where('article_number', '399')
            ->whereNull('fraction')
            ->whereHas('version.document', function ($query): void {
                $query->where('title', 'Código Penal para el Estado de Tamaulipas');
            })
            ->first();

        if (! $roboArticle) {
            $this->command->error('No se encontró el artículo 399 vigente para configurar ROBO SIMPLE.');

            return;
        }

        $elements = [

            [
                'external_offense_id' => 107,
                'legal_article_id' => $roboArticle->id,
                'element_type' => 'objetivo',
                'name' => 'Apoderamiento',
                'verification_criteria' => 'Acción de remoción, sustracción o toma de posesión material del bien.',
                'is_required' => true,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_offense_id' => 107,
                'legal_article_id' => $roboArticle->id,
                'element_type' => 'objetivo',
                'name' => 'Cosa Mueble',
                'verification_criteria' => 'Objeto material tangible susceptible de ser trasladado.',
                'is_required' => true,
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_offense_id' => 107,
                'legal_article_id' => $roboArticle->id,
                'element_type' => 'normativo',
                'name' => 'Ajenuidad',
                'verification_criteria' => 'Acreditación de titularidad o propiedad de un tercero distinto al sujeto activo.',
                'is_required' => true,
                'display_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_offense_id' => 107,
                'legal_article_id' => $roboArticle->id,
                'element_type' => 'subjetivo',
                'name' => 'Falta de Consentimiento',
                'verification_criteria' => 'Declaración o indicio de que la toma del bien fue sin autorización expresa o implícita.',
                'is_required' => true,
                'display_order' => 4,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($elements as $element) {
            OffenseElement::updateOrCreate(
                [
                    'external_offense_id' => $element['external_offense_id'],
                    'name' => $element['name'],
                ],
                $element
            );
        }
    }
}
