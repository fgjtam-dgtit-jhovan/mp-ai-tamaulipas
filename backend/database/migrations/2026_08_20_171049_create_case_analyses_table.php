<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('case_analyses', function (Blueprint $table) {
            $table->id();

            $table->string('external_case_id')->index();       // Folio / ID de la carpeta en Fiscalía Digital
            $table->unsignedBigInteger('external_offense_id'); // ID del delito denunciado (ej. 107)
            $table->foreignId('user_id')->nullable();          // ID del Agente del MP

            // Resultados estructurados procesados por FastAPI
            $table->json('facts_breakdown')->nullable();      // Hechos clasificados
            $table->json('elements_status')->nullable();      // Matriz de cumplimiento de elementos
            $table->json('objectivity_audit')->nullable();    // Auditoría de Cargo vs. Descargo
            $table->json('suggested_diligences')->nullable(); // Diligencias recomendadas con artículo

            $table->enum('status', ['draft', 'reviewed', 'approved', 'rejected'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_analyses');
    }
};
