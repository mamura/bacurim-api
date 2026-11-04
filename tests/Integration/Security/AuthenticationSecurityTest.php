<?php

namespace Tests\Integration\Security;

use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Monolog\Handler\TestHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

#[Group('security')]
class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase,StatefulBrowser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowser();
    }

    private function login(): UserRecord
    {
        $user = UserRecord::factory()->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'testing-only-password'])->assertOk();

        return $user;
    }

    public function test_tampered_cookie_is_denied_without_destroying_valid_session(): void
    {
        $this->login();
        $valid = $this->browserCookies;
        $name = config('session.cookie');
        $this->browserCookies[$name] = 'tampered-'.$valid[$name];
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized()->assertHeader('Content-Type', 'application/problem+json');
        $this->browserCookies = $valid;
        $this->browserRequest('GET', '/api/v1/auth/me')->assertOk();
    }

    public function test_pre_login_csrf_cannot_logout_authenticated_session(): void
    {
        $user = UserRecord::factory()->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $old = $this->browserCookies['XSRF-TOKEN'];
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'testing-only-password'])->assertOk();
        $this->browserRequest('POST', '/api/v1/auth/logout', headers: ['HTTP_X_XSRF_TOKEN' => $old])->assertStatus(419);
        $this->browserRequest('GET', '/api/v1/auth/me')->assertOk();
        $this->browserRequest('POST', '/api/v1/auth/logout')->assertNoContent();
    }

    public static function corruptStamps(): array
    {
        return [['auth.identity_version', '1'], ['auth.identity_version', null], ['auth.identity_version', -1], ['auth.authenticated_at', 'future'], ['auth.authenticated_at', null], ['auth.authenticated_at', []], ['auth.authenticated_at', PHP_INT_MAX]];
    }

    #[DataProvider('corruptStamps')]
    public function test_missing_malformed_or_future_security_stamps_fail_closed(string $key, mixed $value): void
    {
        $this->login();
        $session = app('session')->driver();
        $session->put($key, $value);
        $session->save();
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->browserRequest('GET', '/api/v1/auth/organizations')->assertUnauthorized();
    }

    public function test_expired_real_redis_session_cannot_be_replayed_and_fresh_login_still_works(): void
    {
        $this->assertSame(15, (int) config('database.redis.auth_testing.database'));
        config(['session.driver' => 'redis', 'session.connection' => 'auth_testing', 'session.store' => 'auth_sessions']);
        $ids = [];
        try {
            $user = $this->login();
            $session = app('session')->driver();
            $id = $session->getId();
            $ids[] = $id;
            $store = $session->getHandler()->getCache()->getStore();
            Redis::connection('auth_testing')->expire($store->getPrefix().$id, 0);
            $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
            $ids[] = app('session')->driver()->getId();
            $this->browserRequest('GET', '/sanctum/csrf-cookie');
            $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'testing-only-password'])->assertOk();
            $ids[] = app('session')->driver()->getId();
            $this->browserRequest('GET', '/api/v1/auth/me')->assertOk();
        } finally {
            foreach ($ids as $id) {
                app('session')->driver()->getHandler()->destroy($id);
            }
        }
    }

    public function test_expected_authentication_failures_do_not_log_credentials_or_response_secrets(): void
    {
        config(['logging.default' => 'authentication_probe', 'logging.channels.authentication_probe' => ['driver' => 'monolog', 'handler' => TestHandler::class]]);
        $logger = Log::channel('authentication_probe')->getLogger();
        $user = UserRecord::factory()->create();
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $response = $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'incorrect-secret-sentinel'])->assertUnauthorized();
        Log::info('Authentication probe completed', ['result' => 'expected-denial']);
        $records = $logger->getHandlers()[0]->getRecords();
        $this->assertCount(1, $records);
        foreach (['incorrect-secret-sentinel', $user->password, $this->browserCookies[config('session.cookie')], app('session')->driver()->getId()] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
            $this->assertStringNotContainsString($secret, json_encode($records, JSON_THROW_ON_ERROR));
        }
    }
}
