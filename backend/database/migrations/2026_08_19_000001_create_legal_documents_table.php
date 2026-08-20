<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Documentos jurídicos base (ej. "Código Penal para el Estado de
     * Tamaulipas", "Código Nacional de Procedimientos Penales").
     * No cambia con cada reforma — lo que cambia es su versión,
     * registrada en legal_versions.
     */
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title');                 // Ej. "Código Penal para el Estado de Tamaulipas"
            $table->string('type');                   // Ej. constitución, código, ley orgánica, ley general
            $table->string('jurisdiction');            // Ej. federal, Tamaulipas
            $table->string('official_source_url')->nullable(); // Periódico Oficial / fuente oficial
            $table->unsignedTinyInteger('mvp_level')->default(1); // 1 = Nivel 1 del MVP, 2 = segunda etapa
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_documents');
    }
};
