<?php

namespace Tests\Integration\Security;

use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\TestHandler;
use Monolog\Processor\PsrLogMessageProcessor;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

#[Group('security')]
class HttpSecurityAuditTest extends TestCase
{
    use RefreshDatabase,StatefulBrowser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowser();
        config(['logging.default' => 'security_audit', 'logging.channels.security_audit' => ['driver' => 'monolog', 'handler' => TestHandler::class, 'processors' => [PsrLogMessageProcessor::class]]]);
    }

    private function handler(): TestHandler
    {
        return Log::channel('security_audit')->getLogger()->getHandlers()[0];
    }

    private function login(): UserRecord
    {
        $user = UserRecord::factory()->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'testing-only-password'])->assertOk();

        return $user;
    }

    public function test_unexpected_errors_are_reported_but_http_and_logs_do_not_reveal_request_credentials(): void
    {
        $user = $this->login();
        $handler = $this->handler();
        $cookies = $this->browserCookies;
        $sessionId = app('session')->driver()->getId();
        Route::post('api/v1/auth/_security/failure', function (Request $request) use ($user) {
            Log::error('Diagnostic {password}', ['password' => $request->input('password'), 'nested' => ['Authorization' => $request->header('Authorization'), 'session_id' => $request->session()->getId()], 'hash' => $user->password, 'object' => $request, 'exception' => new RuntimeException('Failure '.$request->input('password'))]);
            throw new RuntimeException('Internal credential '.$request->input('password'));
        })->middleware(['api', 'auth:sanctum', 'auth.session.valid']);
        config(['app.debug' => true]);
        $response = $this->browserRequest('POST', '/api/v1/auth/_security/failure', ['password' => 'http-password-sentinel'], ['HTTP_AUTHORIZATION' => 'Bearer bearer-secret-sentinel']);
        $response->assertStatus(500)->assertJsonPath('detail', 'An unexpected error occurred.')->assertHeader('Content-Type', 'application/problem+json');
        $records = $handler->getRecords();
        $this->assertCount(2, $records);
        $encoded = json_encode($records, JSON_THROW_ON_ERROR);
        foreach (['http-password-sentinel', 'bearer-secret-sentinel', $user->password, $sessionId, ...array_values($cookies)] as $secret) {
            $this->assertStringNotContainsString($secret, $encoded);
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
        $this->assertStringContainsString(RuntimeException::class, $encoded);
        $this->assertStringContainsString('[REDACTED]', $encoded);
        $this->assertArrayNotHasKey('trace', $response->json());
    }

    public function test_framework_context_and_interpolated_values_are_redacted_before_handlers(): void
    {
        $handler = $this->handler();
        try {
            Context::add('metadata', ['api_key' => 'framework-secret-sentinel']);
            Log::info('Diagnostic {password}', ['password' => 'interpolated-secret-sentinel', 'operation' => 'security-audit']);
            $encoded = json_encode($handler->getRecords(), JSON_THROW_ON_ERROR);
            $this->assertStringNotContainsString('framework-secret-sentinel', $encoded);
            $this->assertStringNotContainsString('interpolated-secret-sentinel', $encoded);
            $this->assertStringContainsString('security-audit', $encoded);
        } finally {
            Context::flush();
        }
    }

    public function test_cached_logger_reads_current_request_instead_of_capturing_a_previous_one(): void
    {
        $handler = $this->handler();
        $original = app('request');
        try {
            foreach (['first-request-secret', 'second-request-secret'] as $secret) {
                app()->instance('request', Request::create('/api/v1/_security/probe', 'POST', ['password' => $secret]));
                Log::error('Unexpected '.$secret);
            }
            foreach ($handler->getRecords() as $record) {
                $this->assertSame('Unexpected [REDACTED]', $record->message);
            }
        } finally {
            app()->instance('request', $original);
        }
    }

    public function test_emergency_fallback_logger_applies_the_same_redaction(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bacurim-security-');
        try {
            config(['logging.channels.emergency.path' => $path, 'logging.channels.broken_security' => ['driver' => 'invalid-security-driver']]);
            Log::channel('broken_security')->error('password=emergency-secret-sentinel', ['remember_token' => 'remember-secret-sentinel']);
            $contents = file_get_contents($path);
            $this->assertStringNotContainsString('emergency-secret-sentinel', $contents);
            $this->assertStringNotContainsString('remember-secret-sentinel', $contents);
            $this->assertStringContainsString('[REDACTED]', $contents);
        } finally {
            unlink($path);
        }
    }

    public function test_security_headers_cover_success_errors_and_only_real_https_enables_hsts(): void
    {
        foreach (['/api/v1/health', '/api/v1/absent', '/api/v1/auth/me'] as $uri) {
            $response = $this->browserRequest('GET', $uri);
            $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Referrer-Policy', 'no-referrer')->assertHeader('X-Frame-Options', 'DENY');
            $this->assertFalse($response->headers->has('Strict-Transport-Security'));
        }
        $this->browserRequest('GET', 'https://api-bacurim.mamura.test/api/v1/health')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $response = $this->browserRequest('GET', 'http://api-bacurim.mamura.test/api/v1/health', headers: ['HTTPS' => 'off', 'REMOTE_ADDR' => '192.0.2.77', 'HTTP_X_FORWARDED_PROTO' => 'https']);
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }
}
