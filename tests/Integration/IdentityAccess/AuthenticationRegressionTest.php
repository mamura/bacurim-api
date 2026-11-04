<?php

namespace Tests\Integration\IdentityAccess;

use App\Models\User;
use App\Modules\IdentityAccess\Application\Contracts\PasswordChangeAuthorizer;
use App\Modules\IdentityAccess\Application\UseCases\ChangeUserPassword;
use App\Modules\IdentityAccess\Interfaces\Http\Resources\AuthenticatedUserResource;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

#[Group('security')]
class AuthenticationRegressionTest extends TestCase
{
    use RefreshDatabase, StatefulBrowser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowser();
        Route::post('/api/v1/auth/_contract/password', function (Request $request, ChangeUserPassword $change) {
            return new AuthenticatedUserResource($change->execute($request->input('target'), $request->input('current_password'), $request->input('new_password'), $request->integer('version')));
        })->middleware(['api', 'auth:sanctum', 'auth.session.valid']);
    }

    private function login(User $user, string $password = 'testing-only-password'): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => $password])->assertOk();
    }

    public function test_real_authorizer_only_allows_self_and_password_change_revokes_all_old_sessions(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->login($user);
        $firstCookies = $this->browserCookies;
        $this->browserCookies = [];
        $this->login($user);
        $secondCookies = $this->browserCookies;
        $this->browserCookies = $firstCookies;
        $this->browserRequest('POST', '/api/v1/auth/_contract/password', ['target' => $other->public_id, 'current_password' => 'testing-only-password', 'new_password' => ' another long testing passphrase ', 'version' => 1])->assertForbidden();
        $this->browserRequest('POST', '/api/v1/auth/_contract/password', ['target' => $user->public_id, 'current_password' => 'testing-only-password', 'new_password' => ' another long testing passphrase ', 'version' => 1])->assertOk();
        $this->assertFalse(app('request')->attributes->has('bacurim.authenticated_identity'));
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->browserCookies = $secondCookies;
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->browserCookies = [];
        $this->login($user, ' another long testing passphrase ');
        $this->browserRequest('GET', '/api/v1/auth/me')->assertOk();
        $this->expectException(ApplicationFailure::class);
        $this->app->make(PasswordChangeAuthorizer::class)->assertMayChange($user->public_id);
    }

    public function test_csrf_token_from_another_session_is_rejected(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $first = $this->browserCookies;
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $foreignToken = $this->browserCookies['XSRF-TOKEN'];
        $this->browserCookies = $first;
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => 'missing@example.com', 'password' => 'testing-only-password'], ['HTTP_X_XSRF_TOKEN' => $foreignToken])->assertStatus(419)->assertJsonPath('code', 'csrf_token_mismatch');
    }

    public function test_ip_limit_prevents_rotating_usernames_to_bypass_pair_limit(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        for ($i = 0; $i < 30; $i++) {
            $this->browserRequest('POST', '/api/v1/auth/login', ['email' => 'missing'.$i.'@example.com', 'password' => 'wrong-testing-passphrase'])->assertUnauthorized();
        }
        $response = $this->browserRequest('POST', '/api/v1/auth/login', ['email' => 'different@example.com', 'password' => 'wrong-testing-passphrase'])->assertStatus(429);
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_missing_version_stamp_cannot_authenticate(): void
    {
        $user = User::factory()->create();
        $this->login($user);
        $session = app('session')->driver();
        $session->forget('auth.identity_version');
        $session->save();
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_preflight_is_credentialed_only_for_exact_origins_and_creates_no_session(): void
    {
        $response = $this->browserRequest('OPTIONS', '/api/v1/auth/login', headers: ['HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST', 'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-xsrf-token'])->assertNoContent();
        $this->assertSame('http://bacurim.mamura.test', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        $this->assertSame([], $this->browserCookies);
        $denied = $this->browserRequest('OPTIONS', '/api/v1/auth/login', headers: ['HTTP_ORIGIN' => 'http://bacurim.mamura.test.evil.example', 'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST'])->assertNoContent();
        $this->assertFalse($denied->headers->has('Access-Control-Allow-Origin'));
    }

    public function test_secure_cookie_flags_and_referer_fallback(): void
    {
        config(['session.secure' => true]);
        $response = $this->browserRequest('GET', '/sanctum/csrf-cookie', headers: ['HTTP_ORIGIN' => null, 'HTTP_REFERER' => 'http://bacurim.mamura.test/login'])->assertNoContent();
        foreach ($response->headers->getCookies() as $cookie) {
            $this->assertTrue($cookie->isSecure());
            $this->assertSame('lax', $cookie->getSameSite());
            $this->assertSame('/', $cookie->getPath());
            if ($cookie->getName() === config('session.cookie')) {
                $this->assertTrue($cookie->isHttpOnly());
            }
        }
        $this->browserRequest('GET', '/sanctum/csrf-cookie', headers: ['HTTP_ORIGIN' => 'null'])->assertForbidden();
    }

    public function test_complete_cookie_auth_flow_uses_real_isolated_redis(): void
    {
        $this->assertSame(15, (int) config('database.redis.auth_testing.database'));
        config(['session.driver' => 'redis', 'session.connection' => 'auth_testing', 'session.store' => 'auth_sessions']);
        $ids = [];
        try {
            $user = User::factory()->create();
            $this->login($user);
            $ids[] = app('session')->driver()->getId();
            $this->browserRequest('GET', '/api/v1/auth/me')->assertOk()->assertJsonPath('data.public_id', $user->public_id);
            $authenticatedCookies = $this->browserCookies;
            $this->browserRequest('POST', '/api/v1/auth/logout')->assertNoContent();
            $ids[] = app('session')->driver()->getId();
            $this->browserCookies = $authenticatedCookies;
            $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
            $ids[] = app('session')->driver()->getId();
        } finally {
            foreach ($ids as $id) {
                app('session')->driver()->getHandler()->destroy($id);
            }
        }
    }
}
