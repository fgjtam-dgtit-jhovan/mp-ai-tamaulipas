<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_analysis_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_analysis_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->json('changes');
            $table->string('reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['case_analysis_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_analysis_audits');
    }
};
