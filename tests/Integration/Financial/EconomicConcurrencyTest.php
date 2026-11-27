<?php

namespace Tests\Integration\Financial;

use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\Support\FinancialRecords;
use Tests\TestCase;

#[Group('security')]
class EconomicConcurrencyTest extends TestCase
{
    use FinancialRecords;

    public function test_two_financial_writers_cannot_lose_updates_or_diverge_commitment_total(): void
    {
        $this->artisan('migrate', ['--force' => true])->assertExitCode(0);
        $member = MembershipRecord::factory()->create(['role' => 'owner']);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $user = UserRecord::query()->findOrFail($member->user_id);
        $context = new TenantContext($org->id, $org->public_id, $user->public_id, $user->version, $member->public_id, $member->version);
        $processes = [];
        $tags = [];
        try {
            $record = $this->economicRecord($context);
            $payload = json_encode([$org->id, $org->public_id, $user->public_id, $user->version, $member->public_id, $member->version], JSON_THROW_ON_ERROR);
            DB::beginTransaction();
            OrganizationRecord::query()->whereKey($org->id)->lockForUpdate()->firstOrFail();
            foreach (['111.11', '222.22'] as $amount) {
                $tag = 'financial-event-'.Str::uuid();
                $tags[] = $tag;
                $p = new Process([PHP_BINARY, base_path('tests/Support/financial-event-worker.php'), $payload, $record->event->identity->publicId, $amount, $tag], base_path(), ['TEST_DB_HOST' => config('database.connections.pgsql.host'), 'TEST_DB_PORT' => (string) config('database.connections.pgsql.port')], timeout: 20);
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
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR)['result'];
            }
            sort($results);
            $this->assertSame(['financial.concurrent_update', 'success'], $results);
            $event = DB::table('economic_events')->where('public_id', $record->event->identity->publicId)->first();
            $child = DB::table('financial_commitments')->where('event_public_id', $event->public_id)->first();
            $this->assertSame(2, $event->version);
            $this->assertSame(2, $child->version);
            $this->assertContains($event->amount, ['111.11', '222.22']);
            $this->assertSame($event->amount, $child->expected_amount);
            $this->assertSame(2, DB::table('financial_revisions')->where('organization_id', $org->id)->where('resource_type', 'event')->count());
            $this->assertSame(2, DB::table('financial_revisions')->where('organization_id', $org->id)->where('resource_type', 'commitment')->count());
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $p) {
                if ($p->isRunning()) {
                    $p->stop(1);
                }
            }
            DB::transaction(function () use ($org, $member, $user) {
                DB::table('financial_commitments')->where('organization_id', $org->id)->delete();
                DB::table('economic_events')->where('organization_id', $org->id)->delete();
                DB::table('financial_revisions')->where('organization_id', $org->id)->delete();
                DB::table('financial_categories')->where('organization_id', $org->id)->delete();
                MembershipRecord::query()->whereKey($member->id)->delete();
                OrganizationRecord::query()->whereKey($org->id)->delete();
                UserRecord::query()->whereKey($user->id)->delete();
            });
        }
    }
}
