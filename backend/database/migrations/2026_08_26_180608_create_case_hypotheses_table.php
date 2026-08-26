<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_hypotheses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_analysis_id')->constrained('case_analyses')->cascadeOnDelete();
            $table->unsignedBigInteger('external_offense_id');

            $table->unsignedSmallInteger('total_elements')->default(0);
            $table->unsignedSmallInteger('required_elements')->default(0);
            $table->unsignedSmallInteger('accredited_count')->default(0);
            $table->unsignedSmallInteger('missing_count')->default(0);
            $table->unsignedSmallInteger('contradictory_count')->default(0);

            $table->decimal('completeness_percentage', 5, 2)->default(0);

            // completa | incompleta | con_contradicciones | insuficiente
            $table->string('status');

            // Refleja el principio de la sección 5.2 del anteproyecto:
            // "no puedo concluir" en vez de forzar una conclusión.
            $table->boolean('can_conclude')->default(false);

            $table->json('missing_required_elements')->nullable();

            $table->timestamps();

            $table->index(['case_analysis_id', 'external_offense_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_hypotheses');
    }
};
