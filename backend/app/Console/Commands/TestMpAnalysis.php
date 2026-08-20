<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\CaseAnalysisService;

class TestMpAnalysis extends Command
{
    protected $signature = 'mp:test-analysis';
    protected $description = 'Prueba de integración entre FastAPI y PostgreSQL para MP-IA';

    public function handle(CaseAnalysisService $service)
    {
        $this->info('Enviando caso de prueba a MP-IA Engine...');

        $analysis = $service->runAnalysis(
            externalCaseId: 'NUC-TAM-2026-00892',
            externalOffenseId: 107,
            narrative: 'El día 15 de agosto de 2026, el denunciante refiere que se encontraba en su oficina cuando el imputado sustrajo de su escritorio una computadora portátil HP negra valorada en $15,000 MXN sin su autorización.'
        );

        $this->info("Análisis guardado exitosamente con ID local: {$analysis->id}");
    }
}
