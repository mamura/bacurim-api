<?php

namespace Tests\Integration\IdentityAccess;

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
class ProfileMembershipConcurrencyTest extends TestCase
{
    public static function operations(): array
    {
        return [
            ['add', 'add', false], ['add', 'activate', false],
            ['activate', 'activate', false], ['register', 'add', false],
            ['add', 'add', true],
        ];
    }

    #[DataProvider('operations')]
    public function test_cross_organization_writers_share_the_user_lock(string $first, string $second, bool $consultant): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $target = $consultant ? UserRecord::factory()->consultant()->create() : UserRecord::factory()->create();
        $owners = UserRecord::factory()->count(2)->create();
        $orgs = OrganizationRecord::factory()->count(2)->create();
        $processes = [];
        $tags = [];
        $registrationName = 'profile-race-'.Str::uuid();
        try {
            foreach ($owners as $i => $owner) {
                MembershipRecord::factory()->create(['user_id' => $owner->id, 'organization_id' => $orgs[$i]->id, 'role' => 'owner']);
            }
            DB::beginTransaction();
            UserRecord::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            foreach ([$first, $second] as $i => $operation) {
                $subject = $target->public_id;
                if ($operation === 'activate') {
                    $subject = MembershipRecord::factory()->revoked()->create(['user_id' => $target->id, 'organization_id' => $orgs[$i]->id])->public_id;
                }
                $tag = 'profile-test-'.Str::uuid();
                $tags[] = $tag;
                $actor = $operation === 'register' ? $target->public_id : $owners[$i]->public_id;
                $process = new Process([PHP_BINARY, base_path('tests/Support/profile-membership-worker.php'), $actor, $orgs[$i]->public_id, $subject, $operation, $tag, $registrationName], base_path(), ['TEST_DB_HOST' => config('database.connections.pgsql.host'), 'TEST_DB_PORT' => (string) config('database.connections.pgsql.port')], timeout: 20);
                // Fixtures must be visible to independent processes before the barrier.
                $processes[] = $process;
            }
            DB::commit();
            DB::beginTransaction();
            UserRecord::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            foreach ($processes as $process) {
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
            $this->assertSame(2, $waiting, 'Both processes must wait on the shared identity lock.');
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            $registrationSucceeded = $first === 'register' && $results[0] === 'success';
            sort($results);
            $expected = $consultant ? ['success', 'success'] : ['memberships.active_limit', 'success'];
            sort($expected);
            $this->assertSame($expected, $results);
            $this->assertSame($consultant ? 2 : 1, MembershipRecord::query()->where('user_id', $target->id)->where('status', 'active')->count());
            $registered = OrganizationRecord::query()->where('name', $registrationName)->get();
            $this->assertCount($registrationSucceeded ? 1 : 0, $registered);
            foreach ($registered as $org) {
                $this->assertSame(1, MembershipRecord::query()->where('organization_id', $org->id)->where('user_id', $target->id)->count());
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
            $ids = [...$orgs->modelKeys(), ...OrganizationRecord::query()->where('name', $registrationName)->pluck('id')->all()];
            MembershipRecord::query()->whereIn('organization_id', $ids)->delete();
            OrganizationRecord::query()->whereIn('id', $ids)->delete();
            UserRecord::query()->whereIn('id', [$target->id, ...$owners->modelKeys()])->delete();
        }
    }
}
