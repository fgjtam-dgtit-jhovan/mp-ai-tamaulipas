<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCaseAnalysisJob;
use App\Models\CaseAnalysis;
use App\Repositories\CaseRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class CaseAnalysisController extends Controller
{
    public function __construct(
        protected CaseRepository $caseRepository
    ) {}

    public function show(int $id): Response
    {
        $analysis = CaseAnalysis::findOrFail($id);

        return Inertia::render('CaseAnalysis/Show', [
            'analysis' => $analysis,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'external_case_id' => 'required|string',
            'external_offense_id' => 'required|integer',
            'fact_narrative' => 'required|string|min:10',
        ]);

        // 1. Crear el registro inicial en estado 'draft'
        $analysis = CaseAnalysis::create([
            'external_case_id' => $validated['external_case_id'],
            'external_offense_id' => $validated['external_offense_id'],
            'user_id' => Auth::id() ?? 1,
                'facts_breakdown' => ['narrative' => $validated['fact_narrative']],
                'status' => 'draft',
        ]);

        // 2. Despachar el trabajo a la cola de Laravel
        ProcessCaseAnalysisJob::dispatch($analysis, $validated['fact_narrative']);

        return response()->json([
            'message' => 'Análisis de causa iniciado en segundo plano.',
            'analysis_id' => $analysis->id,
            'status' => $analysis->status,
        ], 202);
    }

    public function update(Request $request, int $id)
    {
        $validated = $request->validate([
            'elements_status' => 'required|array',
            'suggested_diligences' => 'required|array',
            'status' => 'required|string|in:draft,reviewed,approved,rejected',
        ]);

        $analysis = CaseAnalysis::findOrFail($id);

        $analysis->update([
            'elements_status' => $validated['elements_status'],
            'suggested_diligences' => $validated['suggested_diligences'],
            'status' => $validated['status'],
                'user_id' => Auth::id() ?? $analysis->user_id ?? 1,
        ]);

        return redirect()->back()->with('success', 'Revisión ministerial actualizada correctamente.');
    }
}
