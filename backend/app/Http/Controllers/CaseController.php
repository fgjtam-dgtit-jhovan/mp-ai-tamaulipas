<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCaseAnalysisJob;
use App\Models\CaseAnalysis;
use App\Models\Ms\Crime;
use App\Repositories\CaseRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CaseController extends Controller
{
    public function __construct(
        protected CaseRepository $caseRepository
    ) {}

    // Búsqueda y despliegue de la lista de carpetas
    public function index(Request $request): Response
    {
        $expediente = $request->query('expediente');
        $casesList = [];

        if ($expediente) {
            $casesList = $this->caseRepository->searchByExpediente($expediente);
        }

        return Inertia::render('Cases/Search', [
            'expedienteQuery' => $expediente,
            'casesList' => $casesList,
        ]);
    }

    // Detalle de una carpeta específica seleccionada de la lista
    public function show(string $expediente, string $idCarpeta): Response
    {
        $decodedExpediente = urldecode($expediente);
        $caseData = $this->caseRepository->findByIdCarpeta($decodedExpediente, $idCarpeta);

        if (! $caseData) {
            abort(404, 'Carpeta de investigación no encontrada.');
        }

        // Buscar el análisis específico para esta carpeta usando expediente e ID_CARPETA
        $externalCaseId = "{$caseData['EXPEDIENTE']}-{$caseData['ID_CARPETA']}";

        $latestAnalysis = CaseAnalysis::with(['evidence', 'facts', 'hypotheses'])
            ->where('external_case_id', $externalCaseId)
            ->latest()
            ->first();

        return Inertia::render('CaseAnalysis/Show', [
            'caseData' => $caseData,
            'latestAnalysis' => $latestAnalysis,
        ]);
    }

    // Disparar análisis para la carpeta seleccionada
    public function analyze(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expediente' => 'required|string',
            'id_carpeta' => 'required|string',
        ]);

        $caseData = $this->caseRepository->findByIdCarpeta($validated['expediente'], $validated['id_carpeta']);

        if (! $caseData) {
            return back()->withErrors(['expediente' => 'No se encontró la carpeta para analizar.']);
        }

        $externalCaseId = "{$caseData['EXPEDIENTE']}-{$caseData['ID_CARPETA']}";

        $activeAnalysis = CaseAnalysis::where('external_case_id', $externalCaseId)
            ->where('status', 'draft')
            ->latest()
            ->first();

        if ($activeAnalysis) {
            return back()->withErrors(['analysis' => 'Esta carpeta ya tiene un análisis en proceso. Espera a que termine.']);
        }

        $crime = Crime::where('DLTO', $caseData['DELITO'])->first();

        if (! $crime) {
            return back()->withErrors(['expediente' => 'No se encontró el delito asociado a esta carpeta.']);
        }

        $analysis = CaseAnalysis::create([
            'external_case_id' => $externalCaseId,
            'external_offense_id' => $crime->ID_DLTO,
            'user_id' => Auth::id() ?? 1,
            'fact_date' => null,
            'facts_breakdown' => ['narrative' => $caseData['DESCRIPCION_HECHOS'] ?? ''],
            'status' => 'draft',
            'error_message' => null,
        ]);

        ProcessCaseAnalysisJob::dispatch($analysis, $caseData['DESCRIPCION_HECHOS']);

        return back();
    }
}
