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
        Schema::create('case_facts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_analysis_id')->constrained('case_analyses')->cascadeOnDelete();
            $table->string('information_type');
            $table->text('content');
            $table->string('source')->default('narrativa_de_la_carpeta');
            $table->string('procedural_relation')->default('neutral');
            $table->boolean('is_confirmed')->default(true)->after('procedural_relation');
            $table->foreignId('case_evidence_id')->nullable()->constrained('case_evidence')->nullOnDelete();
            $table->timestamps();

            $table->index(['case_analysis_id', 'information_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_facts');
    }
};
