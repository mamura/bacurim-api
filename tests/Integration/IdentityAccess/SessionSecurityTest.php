<?php

namespace Tests\Integration\IdentityAccess;

use App\Models\User;
use App\Modules\IdentityAccess\Application\Data\UserDetails;
use App\Modules\IdentityAccess\Application\UseCases\ChangeUserStatus;
use App\Modules\IdentityAccess\Application\UseCases\UpdateUser;
use App\Modules\IdentityAccess\Domain\UserStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Middleware\TrustProxies;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

#[Group('security')]
class SessionSecurityTest extends TestCase
{
    use RefreshDatabase, StatefulBrowser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowser();
    }

    private function login(): User
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'testing-only-password'])->assertOk();

        return $user;
    }

    public function test_deactivation_and_even_reactivation_revoke_existing_sessions(): void
    {
        $user = $this->login();
        $this->app->make(ChangeUserStatus::class)->execute($user->public_id, UserStatus::Inactive, 1);
        $this->app->make(ChangeUserStatus::class)->execute($user->public_id, UserStatus::Active, 2);
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_profile_changes_require_reauthentication_under_the_documented_coarse_version_policy(): void
    {
        $user = $this->login();
        $this->app->make(UpdateUser::class)->execute($user->public_id, new UserDetails('New', $user->email), 1);
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_absolute_expiration_is_enforced_even_when_backend_session_is_fresh(): void
    {
        $this->login();
        $session = app('session')->driver();
        $session->put('auth.authenticated_at', now()->getTimestamp() - 43201);
        $session->save();
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_login_limit_uses_canonical_email_and_retry_after_then_recovers(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $attempt % 2 ? ' UNKNOWN@example.com ' : 'unknown@example.com', 'password' => 'wrong-testing-passphrase'])->assertUnauthorized();
        }
        $response = $this->browserRequest('POST', '/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'wrong-testing-passphrase'])->assertStatus(429)->assertHeader('Content-Type', 'application/problem+json');
        $this->assertGreaterThan(0, (int) $response->headers->get('Retry-After'));
        $this->travel(61)->seconds();
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'wrong-testing-passphrase'])->assertUnauthorized();
    }

    public function test_forged_forwarded_for_does_not_bypass_limits_from_untrusted_peer(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        for ($i = 0; $i < 5; $i++) {
            $this->browserRequest('POST', '/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'wrong-testing-passphrase'], ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.'.$i])->assertUnauthorized();
        }
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'wrong-testing-passphrase'], ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.90'])->assertStatus(429);
    }

    public function test_explicitly_trusted_proxy_distinguishes_client_addresses(): void
    {
        TrustProxies::at(['10.0.0.10']);
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        for ($i = 0; $i < 5; $i++) {
            $this->browserRequest('POST', '/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'wrong-testing-passphrase'], ['REMOTE_ADDR' => '10.0.0.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.1'])->assertUnauthorized();
        }
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => 'unknown@example.com', 'password' => 'wrong-testing-passphrase'], ['REMOTE_ADDR' => '10.0.0.10', 'HTTP_X_FORWARDED_FOR' => '198.51.100.2'])->assertUnauthorized();
    }

    public function test_successful_identity_responses_are_not_cacheable(): void
    {
        $this->login();
        $response = $this->browserRequest('GET', '/api/v1/auth/me')->assertOk();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame('no-cache', $response->headers->get('Pragma'));
    }
}
