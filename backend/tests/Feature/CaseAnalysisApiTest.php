<?php

namespace Tests\Feature;

use App\Jobs\ProcessCaseAnalysisJob;
use App\Models\CaseAnalysis;
use App\Models\CaseEvidence;
use App\Services\CaseAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CaseAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_create_a_case_analysis_via_the_api(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/cases/analyze', [
            'external_case_id' => 'EXP-123',
            'external_offense_id' => 42,
            'fact_narrative' => 'Los hechos ocurrieron en la zona urbana y el imputado fue identificado por testigos.',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('status', 'draft')
            ->assertJsonStructure([
                'message',
                'analysis_id',
                'status',
            ]);

        Queue::assertPushed(ProcessCaseAnalysisJob::class);
    }

    public function test_analysis_results_are_persisted_as_structured_evidence(): void
    {
        $analysis = CaseAnalysis::create([
            'external_case_id' => 'EXP-456',
            'external_offense_id' => 42,
            'user_id' => 1,
            'facts_breakdown' => ['narrative' => 'Una persona tomó el objeto.'],
            'status' => 'draft',
        ]);

        $service = $this->mock(CaseAnalysisService::class);
        $service->shouldReceive('runAnalysis')->once()->andReturn([
            'elements_analysis' => [[
                'element_id' => null,
                'status' => 'ACREDITADO',
                'evidence_found' => 'Una persona tomó el objeto.',
            ]],
            'objectivity_audit' => [],
            'suggested_diligences' => [],
        ]);

        (new ProcessCaseAnalysisJob($analysis, 'Una persona tomó el objeto.'))->handle($service);

        $this->assertDatabaseHas('case_evidence', [
            'case_analysis_id' => $analysis->id,
            'evidence_type' => 'hecho_narrado',
            'source' => 'narrativa_de_la_carpeta',
            'related_fact' => 'Una persona tomó el objeto.',
            'authenticity_status' => 'pendiente',
            'procedural_relation' => 'cargo',
        ]);
    }

    public function test_reanalysis_preserves_user_verified_evidence(): void
    {
        $analysis = CaseAnalysis::create([
            'external_case_id' => 'EXP-789',
            'external_offense_id' => 42,
            'user_id' => 1,
            'status' => 'reviewed',
        ]);

        CaseEvidence::create([
            'case_analysis_id' => $analysis->id,
            'origin' => 'usuario',
            'evidence_type' => 'documento',
            'source' => 'Acta ministerial',
            'related_fact' => 'El acta fue incorporada a la carpeta.',
            'authenticity_status' => 'autentica',
            'valuation_status' => 'relevante',
            'procedural_relation' => 'cargo',
            'is_verified' => true,
        ]);

        $service = $this->mock(CaseAnalysisService::class);
        $service->shouldReceive('runAnalysis')->once()->andReturn([
            'elements_analysis' => [],
            'objectivity_audit' => [],
            'suggested_diligences' => [],
        ]);

        (new ProcessCaseAnalysisJob($analysis, 'Nueva narrativa.'))->handle($service);

        $this->assertDatabaseHas('case_evidence', [
            'case_analysis_id' => $analysis->id,
            'origin' => 'usuario',
            'related_fact' => 'El acta fue incorporada a la carpeta.',
            'is_verified' => true,
        ]);
    }
}
