<?php

namespace Tests\Integration\IdentityAccess;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

class AuthenticatedUserTest extends TestCase
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

    public function test_me_uses_the_persisted_cookie_and_only_exposes_public_identity_fields(): void
    {
        $user = $this->login();
        $response = $this->browserRequest('GET', '/api/v1/auth/me')->assertOk()->assertJsonPath('data.public_id', $user->public_id);
        $this->assertSame(['public_id', 'name', 'email', 'status', 'email_verified_at'], array_keys($response->json('data')));
        $this->assertStringNotContainsString($user->password, $response->getContent());
        $this->assertArrayNotHasKey('organization_id', $response->json('data'));
        $this->assertArrayNotHasKey('version', $response->json('data'));
    }

    public function test_missing_and_invalid_sessions_receive_problem_details_401(): void
    {
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized()->assertHeader('Content-Type', 'application/problem+json');
        $this->browserCookies = [config('session.cookie') => 'invalid-cookie'];
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
        $this->browserCookies = [];
        $this->browserRequest('GET', '/api/v1/auth/me', headers: ['HTTP_AUTHORIZATION' => 'Bearer unsupported-personal-token'])->assertUnauthorized();
    }

    public function test_backend_expiration_rejects_a_previously_authenticated_cookie(): void
    {
        $this->login();
        $id = app('session')->driver()->getId();
        DB::table('sessions')->where('id', $id)->update(['last_activity' => time() - 7201]);
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
    }

    public function test_replaying_the_pre_logout_cookie_cannot_restore_authentication(): void
    {
        $this->login();
        $authenticatedCookies = $this->browserCookies;
        $this->browserRequest('POST', '/api/v1/auth/logout')->assertNoContent();
        $this->browserCookies = $authenticatedCookies;
        $this->browserRequest('GET', '/api/v1/auth/me')->assertUnauthorized();
    }
}
