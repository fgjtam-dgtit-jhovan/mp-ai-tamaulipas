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
        Schema::create('case_evidence_offense_elements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_evidence_id')->constrained('case_evidence')->cascadeOnDelete();
            $table->foreignId('offense_element_id')->constrained('offense_elements')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['case_evidence_id', 'offense_element_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_evidence_offense_elements');
    }
};
