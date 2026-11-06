<?php

namespace Tests\Integration\IdentityAccess;

use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserCredentials;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Data\UserDetails;
use App\Modules\IdentityAccess\Application\UseCases\CreateUser;
use App\Modules\IdentityAccess\Application\UseCases\UpdateUser;
use App\Modules\IdentityAccess\Domain\UserProfile;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class UserProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_common_creation_defaults_to_standard_and_consultant_survives_existing_updates(): void
    {
        $created = app(CreateUser::class)->execute(new UserDetails('Standard', 'standard@example.com'), 'a valid long testing password');
        $this->assertSame(UserProfile::Standard, $created->profile);
        $record = UserRecord::factory()->consultant()->create();
        $repository = app(UserRepository::class);
        $user = $repository->findByPublicId($record->public_id);
        $this->assertSame(UserProfile::Consultant, $user->profile);
        $updated = app(UpdateUser::class)->execute($user->publicId, new UserDetails('Updated', 'changed@example.com'), $user->version);
        $this->assertSame(UserProfile::Consultant, $updated->profile);
        $user = $repository->findByPublicId($user->publicId);
        $user = $repository->update($user->changeStatus(UserStatus::Inactive, $user->updatedAt));
        $credentials = app(UserCredentials::class);
        $user = $credentials->replace($user->publicId, $credentials->getHash($user->publicId, $user->version), $user->version, $user->updatedAt);
        $this->assertSame(UserProfile::Consultant, $user->profile);
        $this->assertSame('consultant', $record->fresh()->profile->value);
    }

    public static function invalidProfiles(): array
    {
        return [[null], ['admin'], ['owner'], ['']];
    }

    #[DataProvider('invalidProfiles')]
    public function test_database_rejects_invalid_or_null_profile(?string $profile): void
    {
        $record = UserRecord::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', $record->id)->update(['profile' => $profile]);
    }

    public function test_diagnostic_counts_active_even_in_inactive_organizations_without_mutation(): void
    {
        UserRecord::factory()->create();
        $one = UserRecord::factory()->create();
        MembershipRecord::factory()->create(['user_id' => $one->id]);
        $multiple = UserRecord::factory()->create();
        MembershipRecord::factory()->count(2)->create(['user_id' => $multiple->id]);
        DB::table('organizations')->update(['status' => 'inactive']);
        $before = DB::table('organization_memberships')->orderBy('id')->get()->toJson();
        $this->artisan('identity:assess-memberships')->expectsOutputToContain('{"zero":1,"one":1,"multiple":1}')->expectsOutputToContain('incompatible=1')->assertExitCode(1);
        $this->assertSame($before, DB::table('organization_memberships')->orderBy('id')->get()->toJson());
        $this->assertSame(UserProfile::Standard, $multiple->fresh()->profile);
    }

    public function test_consultant_multiple_memberships_are_compatible_with_read_only_diagnostic(): void
    {
        $user = UserRecord::factory()->consultant()->inactive()->create();
        MembershipRecord::factory()->count(2)->create(['user_id' => $user->id]);
        $before = DB::table('users')->where('id', $user->id)->first();
        $this->artisan('identity:assess-memberships')->expectsOutputToContain('{"zero":0,"one":0,"multiple":1}')->expectsOutputToContain('incompatible=0')->assertExitCode(0);
        $this->assertEquals($before, DB::table('users')->where('id', $user->id)->first());
    }

    public function test_migration_preserves_compatible_legacy_records(): void
    {
        $user = UserRecord::factory()->create();
        $member = MembershipRecord::factory()->create(['user_id' => $user->id]);
        MembershipRecord::factory()->revoked()->create(['user_id' => $user->id]);
        UserRecord::factory()->create();
        $users = DB::table('users')->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        $members = DB::table('organization_memberships')->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/2026_11_06_000001_add_user_profile.php');
        $migration->down();
        $migration->up();
        foreach ($users as $original) {
            $row = (array) DB::table('users')->where('id', $original['id'])->first();
            $this->assertSame('standard', $row['profile']);
            unset($row['profile'], $original['profile']);
            $this->assertEquals($original, $row);
        }
        $this->assertSame($members, DB::table('organization_memberships')->orderBy('id')->get()->toJson());
        $this->assertDatabaseHas('organization_memberships', ['public_id' => $member->public_id]);
    }

    public function test_migration_refuses_ambiguous_legacy_users_without_changing_history(): void
    {
        $user = UserRecord::factory()->create();
        MembershipRecord::factory()->count(2)->create(['user_id' => $user->id]);
        $before = DB::table('organization_memberships')->orderBy('id')->get()->toJson();
        $migration = require database_path('migrations/2026_11_06_000001_add_user_profile.php');
        $migration->down();
        try {
            $migration->up();
            $this->fail('Expected reconciliation requirement.');
        } catch (\RuntimeException $failure) {
            $this->assertStringContainsString('explicit legacy reconciliation', $failure->getMessage());
        }
        $this->assertFalse(Schema::hasColumn('users', 'profile'));
        $this->assertSame($before, DB::table('organization_memberships')->orderBy('id')->get()->toJson());
        $this->assertSame($user->password, DB::table('users')->where('id', $user->id)->value('password'));
    }
}
