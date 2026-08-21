<?php

namespace Tests\Feature;

use App\Jobs\ProcessCaseAnalysisJob;
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
}
