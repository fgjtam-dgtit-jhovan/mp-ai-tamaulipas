<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unidad mínima de consulta del RAG jurídico y del Motor de Reglas:
     * un artículo (con su fracción, si aplica) dentro de una versión
     * específica de un documento. El texto completo se guarda aquí
     * para generar sus embeddings hacia Qdrant.
     */
    public function up(): void
    {
        Schema::create('legal_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_version_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('article_number');          // Ej. "308"
            $table->string('fraction')->nullable();      // Ej. "II" — null si el artículo no tiene fracciones
            $table->text('content');                     // texto completo del artículo/fracción
            $table->unsignedInteger('display_order')->default(0); // orden de lectura dentro del documento
            $table->boolean('is_verified')->default(true)->after('content');
            $table->timestamps();

            $table->index(['legal_version_id', 'article_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_articles');
    }
};
