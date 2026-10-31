<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class ApiProblemContractTest extends TestCase
{
    public function test_not_found_uses_problem_details_without_query_secrets(): void
    {
        $this->get('/api/v1/absent?token=secret', ['Accept' => 'text/html'])
            ->assertNotFound()->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('type', 'about:blank')->assertJsonPath('status', 404)
            ->assertJsonPath('title', 'Not Found')->assertJsonPath('instance', '/api/v1/absent')
            ->assertDontSee('secret');
    }

    public function test_method_not_allowed_preserves_allow_header(): void
    {
        $this->post('/api/v1/health')->assertStatus(405)
            ->assertHeader('Allow', 'GET, HEAD')->assertJsonPath('status', 405);
    }

    public function test_retry_after_is_preserved(): void
    {
        Route::get('/api/v1/_contract/rate', fn () => throw new TooManyRequestsHttpException(30, 'internal limit'));

        $this->get('/api/v1/_contract/rate')->assertStatus(429)
            ->assertHeader('Retry-After', '30')->assertJsonPath('status', 429)->assertDontSee('internal limit');
    }

    public function test_unexpected_errors_are_logged_without_exposing_internals_even_in_debug(): void
    {
        config(['app.debug' => true]);
        Log::spy();
        Route::get('/api/v1/_contract/failure', fn () => throw new RuntimeException('database password=secret'));

        $this->get('/api/v1/_contract/failure')->assertStatus(500)
            ->assertExactJson([
                'type' => 'about:blank', 'title' => 'Internal Server Error', 'status' => 500,
                'detail' => 'An unexpected error occurred.', 'instance' => '/api/v1/_contract/failure',
            ])->assertDontSee('secret');
        Log::shouldHaveReceived('error')->once();
    }

    public function test_web_not_found_still_returns_html(): void
    {
        $this->get('/absent', ['Accept' => 'text/html'])->assertNotFound()->assertHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
