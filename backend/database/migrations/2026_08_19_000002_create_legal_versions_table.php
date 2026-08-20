<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada reforma de un documento jurídico genera una nueva versión.
     * Esto permite determinar qué versión estaba vigente en la fecha
     * jurídicamente relevante de un hecho (ver sección 7.1 del
     * anteproyecto), evitando aplicar reformas de forma retroactiva.
     */
    public function up(): void
    {
        Schema::create('legal_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->string('version_label');           // Ej. "Reforma 06-07-2026"
            $table->date('publication_date');           // fecha de publicación
            $table->date('effective_date');              // fecha de inicio de vigencia
            $table->date('repealed_date')->nullable();   // fecha de derogación (null = vigente)
            $table->string('official_source_url')->nullable(); // fuente oficial de esta versión específica
            $table->timestamps();

            // Evita registrar dos veces la misma etiqueta de versión
            // para el mismo documento.
            $table->unique(['legal_document_id', 'version_label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_versions');
    }
};
