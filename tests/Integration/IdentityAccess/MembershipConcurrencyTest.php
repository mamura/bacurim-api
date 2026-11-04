<?php

namespace Tests\Integration\IdentityAccess;

use App\Modules\IdentityAccess\Domain\MembershipRole;
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
class MembershipConcurrencyTest extends TestCase
{
    public static function operations(): array
    {
        return [['demote', false], ['revoke', false], ['demote', true], ['revoke', true], ['add', false]];
    }

    #[DataProvider('operations')]
    public function test_concurrent_membership_operations_preserve_integrity(string $operation, bool $crossTarget): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $org = OrganizationRecord::factory()->create();
        $first = MembershipRecord::factory()->create(['organization_id' => $org->id, 'role' => MembershipRole::Owner]);
        $second = MembershipRecord::factory()->create(['organization_id' => $org->id, 'role' => MembershipRole::Owner]);
        $members = [$first, $second];
        $extraUser = $operation === 'add' ? UserRecord::factory()->create() : null;
        $processes = [];
        $tags = [];
        try {
            DB::beginTransaction();
            OrganizationRecord::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            foreach ($members as $member) {
                $tag = 'membership-test-'.Str::uuid();
                $tags[] = $tag;
                $user = UserRecord::query()->findOrFail($member->user_id);
                $target = $extraUser?->public_id ?? ($crossTarget ? ($member->id === $first->id ? $second->public_id : $first->public_id) : $member->public_id);
                $process = new Process([PHP_BINARY, base_path('tests/Support/membership-worker.php'), $user->public_id, $org->public_id, $target, $operation, $tag], base_path(), ['TEST_DB_HOST' => config('database.connections.pgsql.host'), 'TEST_DB_PORT' => (string) config('database.connections.pgsql.port')], timeout: 15);
                $process->start();
                $processes[] = $process;
                $this->assertTrue($process->waitUntil(fn (string $type, string $output): bool => str_contains($output, 'ready')), $process->getErrorOutput());
            }
            $deadline = microtime(true) + 5;
            do {
                $waiting = (int) DB::selectOne("select count(*) as total from pg_stat_activity where application_name in (?, ?) and wait_event_type = 'Lock'", $tags)->total;
                if ($waiting === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting, 'Both independent workers must contend for the organization lock.');
            DB::commit();
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $lines = explode("\n", trim($process->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            sort($results);
            $failure = $operation === 'add' ? 'memberships.duplicate' : ($crossTarget ? 'tenant.forbidden' : 'memberships.last_owner');
            $expected = [$failure, 'success'];
            sort($expected);
            $this->assertSame($expected, $results);
            $this->assertSame($operation === 'add' ? 2 : 1, MembershipRecord::query()->where('organization_id', $org->id)->where('role', 'owner')->where('status', 'active')->count());
            $this->assertSame($operation === 'add' ? 3 : 2, MembershipRecord::query()->where('organization_id', $org->id)->count());
            if ($extraUser !== null) {
                $this->assertSame(1, MembershipRecord::query()->where('organization_id', $org->id)->where('user_id', $extraUser->id)->count());
            }
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
            MembershipRecord::query()->where('organization_id', $org->id)->delete();
            OrganizationRecord::query()->whereKey($org->id)->delete();
            UserRecord::query()->whereIn('id', array_filter([$first->user_id, $second->user_id, $extraUser?->id]))->delete();
        }
    }
}
