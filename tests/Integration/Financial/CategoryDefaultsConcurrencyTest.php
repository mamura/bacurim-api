<?php

namespace Tests\Integration\Financial;

use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('security')]
class CategoryDefaultsConcurrencyTest extends TestCase
{
    public function test_two_initializers_create_only_one_default_structure(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $member = MembershipRecord::factory()->create(['role' => 'owner']);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $user = UserRecord::query()->findOrFail($member->user_id);
        $input = [$org->id, $org->public_id, $user->public_id, $user->version, $member->public_id, $member->version];
        $processes = [];
        $tags = [];
        try {
            DB::beginTransaction();
            OrganizationRecord::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            for ($i = 0; $i < 2; $i++) {
                $tag = 'default-categories-'.Str::uuid();
                $tags[] = $tag;
                $p = new Process([PHP_BINARY, base_path('tests/Support/financial-defaults-worker.php'), json_encode($input, JSON_THROW_ON_ERROR), $tag], base_path(), ['TEST_DB_HOST' => config('database.connections.pgsql.host'), 'TEST_DB_PORT' => (string) config('database.connections.pgsql.port')], timeout: 20);
                $processes[] = $p;
                $p->start();
                $this->assertTrue($p->waitUntil(fn ($type, $output) => str_contains($output, 'ready')), $p->getErrorOutput());
            }
            $deadline = microtime(true) + 5;
            do {
                $waiting = (int) DB::selectOne("select count(*) as total from pg_stat_activity where application_name in (?,?) and wait_event_type='Lock'", $tags)->total;
                if ($waiting === 2) {
                    break;
                }usleep(10000);
            } while (microtime(true) < $deadline);
            $this->assertSame(2, $waiting);
            DB::commit();
            $results = [];
            foreach ($processes as $p) {
                $this->assertSame(0, $p->wait(), $p->getErrorOutput());
                $lines = explode("\n", trim($p->getOutput()));
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertSame($results[0], $results[1]);
            $this->assertSame(3, DB::table('financial_categories')->where('organization_id', $org->id)->count());
            $this->assertSame(3, DB::table('financial_revisions')->where('organization_id', $org->id)->count());
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop(1);
                }
            }
            DB::table('financial_revisions')->where('organization_id', $org->id)->delete();
            DB::table('financial_categories')->where('organization_id', $org->id)->delete();
            MembershipRecord::query()->whereKey($member->id)->delete();
            OrganizationRecord::query()->whereKey($org->id)->delete();
            UserRecord::query()->whereKey($user->id)->delete();
        }
    }
}
