<?php

namespace Tests\Integration\IdentityAccess;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\StatefulBrowser;
use Tests\TestCase;

#[Group('security')]
class LoginLogoutTest extends TestCase
{
    use RefreshDatabase, StatefulBrowser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowser();
    }

    public function test_login_rotates_session_id_and_csrf_and_logout_destroys_authenticated_session(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $oldId = app('session')->driver()->getId();
        $oldToken = app('session')->driver()->token();
        $response = $this->browserRequest('POST', '/api/v1/auth/login', ['email' => ' ANA@example.com ', 'password' => 'testing-only-password'])->assertOk()->assertJsonPath('data.public_id', $user->public_id);
        $authenticatedId = app('session')->driver()->getId();
        $this->assertNotSame($oldId, $authenticatedId);
        $this->assertNotSame($oldToken, app('session')->driver()->token());
        $this->assertDatabaseMissing('sessions', ['id' => $oldId]);
        $this->assertDatabaseHas('sessions', ['id' => $authenticatedId, 'user_id' => $user->id]);
        $this->assertStringNotContainsString('testing-only-password', $response->getContent());
        $this->assertStringNotContainsString($user->password, $response->getContent());
        $this->assertStringNotContainsString($authenticatedId, $response->getContent());
        $this->browserRequest('POST', '/api/v1/auth/logout')->assertNoContent();
        $this->assertDatabaseMissing('sessions', ['id' => $authenticatedId]);
        $this->assertNull(app('auth')->guard('web')->id());
        $this->assertDatabaseCount('users', 1);
    }

    public function test_unknown_wrong_password_and_inactive_users_have_identical_errors(): void
    {
        User::factory()->create(['email' => 'active@example.com']);
        User::factory()->inactive()->create(['email' => 'inactive@example.com']);
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $bodies = [];
        foreach ([['missing@example.com', 'testing-only-password'], ['active@example.com', 'wrong-testing-password'], ['inactive@example.com', 'testing-only-password']] as [$email, $password]) {
            $response = $this->browserRequest('POST', '/api/v1/auth/login', compact('email', 'password'))->assertUnauthorized()->assertHeader('Content-Type', 'application/problem+json');
            $bodies[] = $response->json();
        }
        $this->assertSame($bodies[0], $bodies[1]);
        $this->assertSame($bodies[1], $bodies[2]);
        $this->assertSame('identity.invalid_credentials', $bodies[0]['code']);
    }

    public function test_login_requires_valid_structure_csrf_and_trusted_origin(): void
    {
        $this->browserRequest('GET', '/sanctum/csrf-cookie');
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'testing-only-password'], csrf: false)->assertStatus(419);
        $this->browserRequest('POST', '/api/v1/auth/login', [])->assertUnprocessable();
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => 'a@example.com', 'password' => 'testing-only-password'], headers: ['HTTP_ORIGIN' => 'http://evil.example'])->assertForbidden();
    }
}
