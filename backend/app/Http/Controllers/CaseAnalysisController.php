<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessCaseAnalysisJob;
use App\Models\CaseAnalysis;
use App\Repositories\CaseRepository;
use Illuminate\Database\Eloquent\Builder;
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

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $search = $request->string('search')->trim()->toString();

        $analysesQuery = CaseAnalysis::query()
            ->where('user_id', Auth::id() ?? 1)
            ->when($status && in_array($status, ['draft', 'reviewed', 'approved', 'rejected'], true), function (Builder $query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($search, function (Builder $query) use ($search): void {
                $query->where('external_case_id', 'like', "%{$search}%");
            });

        $analyses = $analysesQuery->latest()->limit(50)->get([
            'id',
            'external_case_id',
            'external_offense_id',
            'status',
            'error_message',
            'created_at',
            'updated_at',
        ]);

        $baseQuery = CaseAnalysis::where('user_id', Auth::id() ?? 1);

        return Inertia::render('CaseAnalysis/Index', [
            'analyses' => $analyses,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'processing' => (clone $baseQuery)->where('status', 'draft')->count(),
                'completed' => (clone $baseQuery)->whereIn('status', ['reviewed', 'approved'])->count(),
                'failed' => (clone $baseQuery)->where('status', 'rejected')->count(),
            ],
        ]);
    }

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

        $analysis = CaseAnalysis::create([
            'external_case_id' => $validated['external_case_id'],
            'external_offense_id' => $validated['external_offense_id'],
            'user_id' => Auth::id() ?? 1,
            'facts_breakdown' => ['narrative' => $validated['fact_narrative']],
            'status' => 'draft',
            'error_message' => null,
        ]);

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
