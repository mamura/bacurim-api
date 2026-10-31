<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    public function test_health_returns_json_without_an_accept_header(): void
    {
        $this->get('/api/v1/health')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_unknown_api_route_returns_json_even_when_html_is_requested(): void
    {
        $this->get('/api/v1/unknown', ['Accept' => 'text/html'])
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance']);
    }

    public function test_unsupported_health_method_returns_json(): void
    {
        $this->post('/api/v1/health', [], ['Accept' => 'text/html'])
            ->assertStatus(405)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonStructure(['type', 'title', 'status', 'detail', 'instance']);
    }
}
