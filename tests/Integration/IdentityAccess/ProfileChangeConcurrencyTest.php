<?php

namespace Tests\Integration\IdentityAccess;

use App\Modules\IdentityAccess\Domain\UserProfile;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('security')]
class ProfileChangeConcurrencyTest extends TestCase
{
    public static function operations(): array
    {
        return [['downgrade', 'add'], ['downgrade', 'activate'], ['downgrade', 'revoke'], ['promote', 'add'], ['promote', 'activate']];
    }

    #[DataProvider('operations')]
    public function test_profile_and_membership_mutations_preserve_global_invariants(string $change, string $operation): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $target = $change === 'downgrade' ? UserRecord::factory()->consultant()->create() : UserRecord::factory()->create();
        $owner = UserRecord::factory()->create();
        $orgs = OrganizationRecord::factory()->count(2)->create();
        $processes = [];
        $tags = [];
        try {
            MembershipRecord::factory()->create(['user_id' => $owner->id, 'organization_id' => $orgs[1]->id, 'role' => 'owner']);
            MembershipRecord::factory()->create(['user_id' => $target->id, 'organization_id' => $orgs[0]->id, 'role' => 'viewer']);
            $subject = $target->public_id;
            if ($operation === 'activate') {
                $subject = MembershipRecord::factory()->revoked()->create(['user_id' => $target->id, 'organization_id' => $orgs[1]->id, 'role' => 'viewer'])->public_id;
            } elseif ($operation === 'revoke') {
                $subject = MembershipRecord::factory()->create(['user_id' => $target->id, 'organization_id' => $orgs[1]->id, 'role' => 'viewer'])->public_id;
            }
            DB::beginTransaction();
            UserRecord::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            foreach ([[$change, $target->public_id], [$operation, $owner->public_id]] as [$action, $actor]) {
                $tag = 'profile-change-'.Str::uuid();
                $tags[] = $tag;
                $process = new Process([PHP_BINARY, base_path('tests/Support/profile-membership-worker.php'), $actor, $orgs[1]->public_id, $subject, $action, $tag, 'unused'], base_path(), ['TEST_DB_HOST' => config('database.connections.pgsql.host'), 'TEST_DB_PORT' => (string) config('database.connections.pgsql.port')], timeout: 20);
                $processes[] = $process;
                $process->start();
                $this->assertTrue($process->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), $process->getErrorOutput());
            }
            $deadline = microtime(true) + 5;
            do {
                $waiting = (int) DB::selectOne("select count(*) as total from pg_stat_activity where application_name in (?, ?) and wait_event_type = 'Lock'", $tags)->total;
                if ($waiting === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Both independent operations must contend for the identity.');
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            $fresh = $target->fresh();
            $active = MembershipRecord::query()->where('user_id', $target->id)->where('status', 'active')->count();
            $this->assertTrue($fresh->profile === UserProfile::Consultant || $active <= 1);
            $this->assertSame($results[0] === 'success' ? 2 : 1, $fresh->version);
            $this->assertSame($target->password, $fresh->password);
            if ($change === 'promote') {
                $this->assertSame('success', $results[0]);
                $this->assertContains($results[1], ['success', 'memberships.active_limit']);
                $this->assertSame(UserProfile::Consultant, $fresh->profile);
                $this->assertSame($results[1] === 'success' ? 2 : 1, $active);
            } elseif ($operation === 'revoke') {
                $this->assertSame('success', $results[1]);
                $this->assertContains($results[0], ['success', 'identity.profile_memberships_conflict']);
                $this->assertSame(1, $active);
            } else {
                $this->assertContains($results, [
                    ['success', 'memberships.active_limit'],
                    ['identity.profile_memberships_conflict', 'success'],
                ]);
                $this->assertSame($results[1] === 'success' ? 2 : 1, $active);
            }
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            MembershipRecord::query()->whereIn('organization_id', $orgs->modelKeys())->delete();
            OrganizationRecord::query()->whereIn('id', $orgs->modelKeys())->delete();
            UserRecord::query()->whereIn('id', [$target->id, $owner->id])->delete();
        }
    }
}
