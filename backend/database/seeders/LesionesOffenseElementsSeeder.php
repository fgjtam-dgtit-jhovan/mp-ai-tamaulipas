<?php

namespace Database\Seeders;

use App\Models\LegalArticle;
use App\Models\OffenseElement;
use Illuminate\Database\Seeder;

class LesionesOffenseElementsSeeder extends Seeder
{
    /**
     * Elementos del tipo penal de LESIONES (ID_DLTO 15), derivados
     * ÚNICAMENTE de las palabras del Art. 319 verificado — sin
     * agregar elementos doctrinales que el texto no sostiene
     * literalmente. Corre LesionesArticlesSeeder antes que este.
     */
    public function run(): void
    {
        $lesionesArticle = LegalArticle::where('article_number', '319')
            ->whereNull('fraction')
            ->whereHas('version.document', function ($query): void {
                $query->where('title', 'Código Penal para el Estado de Tamaulipas');
            })
            ->first();

        if (! $lesionesArticle) {
            $this->command->error('No se encontró el Art. 319 — corre primero LesionesArticlesSeeder.');

            return;
        }

        $elements = [
            [
                'external_offense_id' => 15,
                'legal_article_id' => $lesionesArticle->id,
                'element_type' => 'objetivo',
                'name' => 'Acción de Inferir',
                'verification_criteria' => 'Conducta activa de causar o producir un daño físico o mental sobre otra persona.',
                'is_required' => true,
                'display_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_offense_id' => 15,
                'legal_article_id' => $lesionesArticle->id,
                'element_type' => 'normativo',
                'name' => 'Sujeto Pasivo Distinto',
                'verification_criteria' => 'El daño se causa "a otro" — persona física distinta del sujeto activo.',
                'is_required' => true,
                'display_order' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_offense_id' => 15,
                'legal_article_id' => $lesionesArticle->id,
                'element_type' => 'objetivo',
                'name' => 'Resultado Material: Vestigio Corporal',
                'verification_criteria' => 'El daño deja en el cuerpo de la víctima un vestigio (huella física verificable, ej. dictamen médico).',
                'is_required' => false,
                'display_order' => 3,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'external_offense_id' => 15,
                'legal_article_id' => $lesionesArticle->id,
                'element_type' => 'objetivo',
                'name' => 'Resultado Material: Alteración de la Salud',
                'verification_criteria' => 'Alternativamente al vestigio corporal, el daño altera la salud física o mental de la víctima (ej. dictamen psicológico/médico). El Art. 319 exige vestigio O alteración de salud, no ambos.',
                'is_required' => false,
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

        $this->command->info('OffenseElements de Lesiones (Art. 319 base) cargados.');
    }
}
