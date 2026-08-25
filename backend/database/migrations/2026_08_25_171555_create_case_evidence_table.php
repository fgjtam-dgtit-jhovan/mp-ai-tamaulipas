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
        Schema::create('case_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_analysis_id')->constrained('case_analyses')->cascadeOnDelete();
            $table->foreignId('offense_element_id')->nullable()->constrained('offense_elements')->nullOnDelete();
            $table->string('evidence_type');
            $table->string('source');
            $table->date('evidence_date')->nullable();
            $table->text('related_fact');
            $table->string('authenticity_status')->default('pendiente');
            $table->string('procedural_relation')->default('neutral');
            $table->timestamps();

            $table->index(['case_analysis_id', 'procedural_relation']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_evidence');
    }
};
