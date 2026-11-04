<?php

namespace Tests\Integration\IdentityAccess;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

class SpaBoundaryTest extends TestCase
{
    use RefreshDatabase, StatefulBrowser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowser();
        Route::middleware('api')->post('/api/v1/auth/_contract/csrf', fn () => response()->noContent());
    }

    public function test_csrf_cookie_bootstrap_and_real_token_validation(): void
    {
        $response = $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->assertArrayHasKey('XSRF-TOKEN', $this->browserCookies);
        $this->assertArrayHasKey(config('session.cookie'), $this->browserCookies);
        $this->browserRequest('POST', '/api/v1/auth/_contract/csrf', csrf: false)->assertStatus(419)->assertHeader('Content-Type', 'application/problem+json');
        $this->browserRequest('POST', '/api/v1/auth/_contract/csrf')->assertNoContent();
        $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());
        $this->assertTrue($cookies[config('session.cookie')]->isHttpOnly());
        $this->assertFalse($cookies['XSRF-TOKEN']->isHttpOnly());
        $this->assertSame('lax', $cookies[config('session.cookie')]->getSameSite());
    }

    public function test_untrusted_origins_do_not_receive_credentialed_cors_access_or_sessions(): void
    {
        $response = $this->browserRequest('GET', '/sanctum/csrf-cookie', headers: ['HTTP_ORIGIN' => 'http://evil.example'])->assertForbidden();
        $this->assertFalse($response->headers->has('Access-Control-Allow-Origin'));
        $this->assertSame([], $this->browserCookies);
        $allowed = $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->assertSame('http://bacurim.mamura.test', $allowed->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $allowed->headers->get('Access-Control-Allow-Credentials'));
    }
}
