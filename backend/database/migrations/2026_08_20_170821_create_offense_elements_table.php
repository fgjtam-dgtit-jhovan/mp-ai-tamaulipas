<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offense_elements', function (Blueprint $table) {
            $table->id();

            // ID del delito en tu catálogo externo (ej. 107 para ROBO SIMPLE, 94 para VIOLENCIA FAMILIAR)
            $table->unsignedBigInteger('external_offense_id')->index();

            // Enlace con el artículo correspondiente en LegalCore
            $table->foreignId('legal_article_id')
                ->nullable()
                ->constrained('legal_articles')
                ->nullOnDelete();

            $table->enum('element_type', ['objetivo', 'subjetivo', 'normativo']);
            $table->string('name');                      // Nombre del elemento (ej. "Apoderamiento")
            $table->text('verification_criteria');       // Qué hecho o prueba valida este elemento
            $table->boolean('is_required')->default(true);
            $table->unsignedInteger('display_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offense_elements');
    }
};
