<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCaseAnalysisJob;
use App\Jobs\ProcessCaseMotorJob;
use App\Models\CaseAnalysis;
use App\Models\Ms\Crime;
use App\Repositories\CaseRepository;
use Carbon\Carbon;
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
        $crime = Crime::where('DLTO', $caseData['DELITO'] ?? null)->first();

        $latestAnalysis = CaseAnalysis::with(['evidence', 'facts', 'hypotheses', 'audits.user'])
            ->where('external_case_id', $externalCaseId)
            ->where('user_id', Auth::id() ?? 1)
            ->when($crime, fn ($query) => $query->where('external_offense_id', $crime->ID_DLTO))
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
        $factDate = $this->factDate($caseData);

        $activeAnalysis = CaseAnalysis::where('external_case_id', $externalCaseId)
            ->where('user_id', Auth::id() ?? 1)
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
            'fact_date' => $factDate?->toDateString(),
            'facts_breakdown' => ['narrative' => $caseData['DESCRIPCION_HECHOS'] ?? ''],
            'status' => 'draft',
            'error_message' => null,
        ]);

        ProcessCaseAnalysisJob::dispatch($analysis, $caseData['DESCRIPCION_HECHOS']);

        return back();
    }

    public function runMotor(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'expediente' => 'required|string',
            'id_carpeta' => 'required|string',
            'motor' => 'required|string|in:hechos,matriz,objetividad,imparcialidad,diligencias,registro,hipotesis',
        ]);

        $caseData = $this->caseRepository->findByIdCarpeta($validated['expediente'], $validated['id_carpeta']);
        if (! $caseData) {
            return back()->withErrors(['motor' => 'No se encontró la carpeta para ejecutar este motor.']);
        }

        $crime = Crime::where('DLTO', $caseData['DELITO'] ?? null)->first();
        if (! $crime) {
            return back()->withErrors(['motor' => 'No se encontró el delito asociado a esta carpeta.']);
        }

        $externalCaseId = "{$caseData['EXPEDIENTE']}-{$caseData['ID_CARPETA']}";
        $factDate = $this->factDate($caseData);
        $analysis = CaseAnalysis::firstOrCreate(
            [
                'external_case_id' => $externalCaseId,
                'external_offense_id' => $crime->ID_DLTO,
                'user_id' => Auth::id() ?? 1,
            ],
            [
                'fact_date' => $factDate?->toDateString(),
                'facts_breakdown' => ['narrative' => $caseData['DESCRIPCION_HECHOS'] ?? ''],
                'status' => 'draft',
            ]
        );

        if ($factDate && ! $analysis->fact_date) {
            $analysis->update([
                'fact_date' => $factDate->toDateString(),
            ]);
        }

        $motorStatus = $analysis->motor_status ?? [];
        $facts = $analysis->facts_breakdown['facts'] ?? [];
        $elementsAnalysis = $analysis->elements_status ?? [];
        $requiresLegalFoundation = in_array($validated['motor'], ['objetividad', 'imparcialidad', 'diligencias', 'hipotesis', 'registro'], true);

        if ($requiresLegalFoundation && (empty($facts) || empty($elementsAnalysis))) {
            return back()->withErrors(['motor' => 'Primero debes completar el análisis de hechos y la matriz jurídica antes de ejecutar este módulo.']);
        }

        if (! $analysis->wasRecentlyCreated && $analysis->status === 'draft' && empty($motorStatus)) {
            return back()->withErrors(['motor' => 'La base jurídica todavía está en proceso. Espera a que termine antes de ejecutar otro módulo.']);
        }

        $anotherMotorIsRunning = collect($motorStatus)->contains(
            fn (array $state, string $activeMotor): bool => $activeMotor !== $validated['motor'] && ($state['status'] ?? null) === 'draft'
        );
        if ($anotherMotorIsRunning) {
            return back()->withErrors(['motor' => 'Ya hay otro módulo en ejecución. Espera a que termine antes de continuar.']);
        }

        if (($motorStatus[$validated['motor']]['status'] ?? null) === 'draft') {
            return back()->withErrors(['motor' => 'Este motor ya está en proceso.']);
        }

        $motorStatus[$validated['motor']] = [
            'status' => 'draft',
            'error' => null,
            'updated_at' => now()->toISOString(),
        ];
        $analysis->update([
            'motor_status' => $motorStatus,
            'status' => 'draft',
            'error_message' => null,
        ]);

        ProcessCaseMotorJob::dispatch(
            $analysis,
            $validated['motor'],
            $caseData['DESCRIPCION_HECHOS'] ?? ''
        );

        return back();
    }

    private function factDate(array $caseData): ?Carbon
    {
        $value = $caseData['FECHA_HECHO'] ?? null;

        if (blank($value)) {
            return null;
        }

        return Carbon::parse($value);
    }
}
