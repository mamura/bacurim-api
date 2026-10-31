<?php

namespace Tests\Integration;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PostgreSqlPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public static function isolatedRuns(): array
    {
        return [['first@example.test'], ['second@example.test']];
    }

    #[DataProvider('isolatedRuns')]
    public function test_migrations_and_factory_writes_are_isolated_between_tests(string $email): void
    {
        $this->assertSame('bacurim_test', DB::selectOne('select current_database() as name')->name);
        $this->assertDatabaseCount('users', 0);

        $user = User::factory()->create(['email' => $email]);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => $email]);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_postgresql_enforces_unique_email_constraint(): void
    {
        User::factory()->create(['email' => 'unique@example.test']);
        $this->expectException(QueryException::class);

        User::factory()->create(['email' => 'unique@example.test']);
    }
}
