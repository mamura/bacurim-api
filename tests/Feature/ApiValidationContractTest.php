<?php

namespace Tests\Feature;

use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ApiValidationContractTest extends TestCase
{
    public function test_validation_returns_field_messages_with_dot_notation(): void
    {
        Route::post('/api/v1/_contract/validate', function (Request $request) {
            $request->validate(['name' => ['required', 'string'], 'items.0.label' => ['required', 'string']]);

            return response()->noContent();
        });

        $this->post('/api/v1/_contract/validate', [], ['Accept' => 'text/html'])
            ->assertUnprocessable()->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'urn:bacurim:problem:validation_failed')
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonStructure(['errors' => ['name', 'items.0.label']]);
    }

    public function test_known_conflict_has_stable_code_and_public_message_without_error_logging(): void
    {
        Log::spy();
        Route::post('/api/v1/_contract/conflict', fn () => throw new ApplicationFailure(
            FailureKind::Conflict, 'example.state_conflict', 'The operation is incompatible with the current state.',
        ));

        $this->postJson('/api/v1/_contract/conflict')->assertConflict()
            ->assertJsonPath('type', 'urn:bacurim:problem:example.state_conflict')
            ->assertJsonPath('code', 'example.state_conflict')
            ->assertJsonPath('detail', 'The operation is incompatible with the current state.');
        Log::shouldNotHaveReceived('error');
    }
}
