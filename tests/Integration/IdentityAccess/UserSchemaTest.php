<?php

namespace Tests\Integration\IdentityAccess;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class UserSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_factories_cover_states_and_prepare_verification_without_organization(): void
    {
        $active = User::factory()->create();
        $inactive = User::factory()->inactive()->unverified()->create();
        $this->assertNotSame($active->public_id, $inactive->public_id);
        $this->assertSame('inactive', $inactive->getRawOriginal('status'));
        $this->assertNull($inactive->email_verified_at);
        $this->assertNotNull($active->email_verified_at);
        $this->assertDatabaseCount('users', 2);
    }

    public static function invalidValues(): array
    {
        return [['public_id', null], ['status', 'deleted'], ['version', 0], ['email', 'UPPER@example.com'], ['email', ' padded@example.com '], ['email', 'bad'], ['password', 'plain-text-password'], ['name', ''], ['updated_at', '2000-01-01'], ['email_verified_at', '2000-01-01']];
    }

    #[DataProvider('invalidValues')]
    public function test_postgresql_rejects_invalid_identity_data(string $field, mixed $value): void
    {
        $attributes = User::factory()->raw();
        $attributes[$field] = $value;
        $this->expectException(QueryException::class);
        DB::table('users')->insert($attributes);
    }

    public function test_public_uuid_is_unique(): void
    {
        $user = User::factory()->create();
        $this->expectException(QueryException::class);
        User::factory()->create(['public_id' => $user->public_id]);
    }

    public function test_additive_migration_preserves_existing_users_and_normalizes_email(): void
    {
        $migration = require database_path('migrations/2026_11_04_000002_extend_users_identity.php');
        $migration->down();
        $time = now()->startOfSecond();
        $id = DB::table('users')->insertGetId(['name' => 'Existing', 'email' => ' Ana+tag@EXAMPLE.COM ', 'password' => password_hash('legacy-password', PASSWORD_BCRYPT), 'created_at' => $time, 'updated_at' => $time]);
        $migration->up();
        $user = DB::table('users')->where('id', $id)->first();
        $this->assertSame('ana+tag@example.com', $user->email);
        $this->assertNotNull($user->public_id);
        $this->assertSame('active', $user->status);
        $this->assertTrue(password_verify('legacy-password', $user->password));
        $migration->down();
        $this->assertDatabaseHas('users', ['id' => $id, 'email' => 'ana+tag@example.com']);
        $migration->up();
    }

    public function test_legacy_email_collision_is_rejected_before_changing_schema_or_users(): void
    {
        $migration = require database_path('migrations/2026_11_04_000002_extend_users_identity.php');
        $migration->down();
        $time = now()->startOfSecond();
        foreach (['Ana@example.com', 'ana@example.com'] as $email) {
            DB::table('users')->insert(['name' => 'Existing', 'email' => $email, 'password' => password_hash('legacy-password', PASSWORD_BCRYPT), 'created_at' => $time, 'updated_at' => $time]);
        }
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Existing users have colliding normalized emails');
        $migration->up();
    }
}
