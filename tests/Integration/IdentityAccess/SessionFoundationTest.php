<?php

namespace Tests\Integration\IdentityAccess;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SessionFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guard_normalizes_email_accepts_active_users_and_rejects_inactive_users(): void
    {
        $user = User::factory()->create(['email' => 'ana@example.com']);
        $this->assertTrue(Auth::guard('web')->attempt(['email' => ' ANA@example.com ', 'password' => 'testing-only-password'], false));
        $this->assertSame($user->id, Auth::guard('web')->id());
        Auth::guard('web')->logout();
        User::factory()->inactive()->create(['email' => 'inactive@example.com']);
        $this->assertFalse(Auth::guard('web')->attempt(['email' => 'inactive@example.com', 'password' => 'testing-only-password']));
    }

    public function test_legacy_bcrypt_is_verified_and_rehashed_without_changing_password(): void
    {
        $user = User::factory()->create(['password' => password_hash('legacy-pass', PASSWORD_BCRYPT, ['cost' => 4])]);
        $this->assertTrue(Auth::guard('web')->attempt(['email' => $user->email, 'password' => 'legacy-pass']));
        $fresh = $user->fresh();
        $this->assertSame('argon2id', password_get_info($fresh->password)['algoName']);
        $this->assertTrue(password_verify('legacy-pass', $fresh->password));
        $this->assertSame(2, $fresh->version);
    }
}
