<?php

namespace Tests\Integration\Financial;

use App\Modules\Financial\Application\Contracts\Persistence\EconomicRecordRepository;
use App\Modules\Financial\Application\Contracts\Persistence\FinancialCommitmentRepository;
use App\Modules\Financial\Application\Data\CategoryChanges;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Application\FinancialOperation;
use App\Modules\Financial\Application\UseCases\ManageFinancialCategories;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\Entities\EconomicEvent;
use App\Modules\Financial\Domain\Entities\EconomicRecord;
use App\Modules\Financial\Domain\Entities\FinancialCommitment;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Domain\ValueObjects\FinancialDate;
use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\Modules\Financial\Domain\ValueObjects\Money;
use App\Modules\Financial\Infrastructure\Persistence\FinancialPersistenceFailure;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\FinancialRecords;
use Tests\Support\FinancialTestCase;

#[Group('security')]
class EconomicPersistenceTest extends FinancialTestCase
{
    use FinancialRecords;

    public static function natures(): array
    {
        return [[EconomicNature::Income, 'receivable'], [EconomicNature::Cost, 'payable'], [EconomicNature::Expense, 'payable']];
    }

    #[DataProvider('natures')]
    public function test_aggregate_round_trip_with_exact_values_independent_dates_and_public_origin(EconomicNature $nature, string $expected): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx, $nature, '0.30', [['amount' => '0.10', 'due_date' => '2026-02-01'], ['amount' => '0.20', 'due_date' => '2026-03-01']]);
        $saved = app(EconomicRecordRepository::class)->find($ctx, $record->event->identity->publicId);
        $this->assertEquals($record, $saved);
        $this->assertCount(2, $saved->commitments);
        $this->assertSame('0.30', $saved->event->amount->amount);
        $this->assertSame('2026-01-15', $saved->event->competenceDate->value);
        foreach ($saved->commitments as $commitment) {
            $this->assertSame($expected, $commitment->nature->value);
            $this->assertEquals($commitment, app(FinancialCommitmentRepository::class)->find($ctx, $commitment->identity->publicId));
        }
        DB::statement('SET CONSTRAINTS economic_events_aggregate_check,financial_commitments_aggregate_check IMMEDIATE');
        $this->assertDatabaseCount('financial_accounts', 0);
        $this->assertSame($user->public_id, DB::table('economic_events')->value('created_by'));
    }

    public function test_foreign_resources_and_references_remain_scoped(): void
    {
        [$user,$org,$a] = $this->tenant();
        [$other,$otherOrg,$b] = $this->tenant();
        $record = $this->economicRecord($a);
        $this->assertNull(app(EconomicRecordRepository::class)->find($b, $record->event->identity->publicId));
        $this->assertNull(app(FinancialCommitmentRepository::class)->find($b, $record->commitments[0]->identity->publicId));
        $this->assertSame(0, app(EconomicRecordRepository::class)->page($b, new FinancialQuery)->total);
        $foreign = $this->economicRecord($b);
        $this->expectException(QueryException::class);
        DB::table('financial_commitments')->where('public_id', $record->commitments[0]->identity->publicId)->update(['event_public_id' => $foreign->event->identity->publicId]);
    }

    public function test_constraint_prevents_committing_event_without_commitment(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx);
        $attributes = (array) DB::table('economic_events')->where('public_id', $record->event->identity->publicId)->first();
        unset($attributes['id']);
        $attributes['public_id'] = (string) Str::uuid();
        $this->expectException(QueryException::class);
        DB::table('economic_events')->insert($attributes);
        DB::statement('SET CONSTRAINTS economic_events_aggregate_check IMMEDIATE');
    }

    public function test_constraint_prevents_inconsistent_total_or_wrong_direction(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx);
        $this->expectException(QueryException::class);
        DB::table('financial_commitments')->where('public_id', $record->commitments[0]->identity->publicId)->update(['expected_amount' => '99.99', 'nature' => 'payable']);
        DB::statement('SET CONSTRAINTS financial_commitments_aggregate_check IMMEDIATE');
    }

    public function test_child_persistence_failure_rolls_back_event_and_audit(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $category = app(ManageFinancialCategories::class)->create($ctx, 'Income', EconomicNature::Income);
        DB::statement('ALTER TABLE financial_commitments ADD CONSTRAINT test_reject_commitment CHECK(expected_amount > 1000)');
        $now = new \DateTimeImmutable;
        $identity = fn () => new FinancialIdentity(null, (string) Str::uuid(), $ctx->organizationId, 0, $now, $now);
        $event = new EconomicEvent($identity(), 'Must rollback', EconomicNature::Income, new Money('1.00'), new FinancialDate('2026-01-01'), $category, $ctx->actorPublicId);
        try {
            app(FinancialOperation::class)->run($ctx, true, fn () => app(EconomicRecordRepository::class)->save($ctx, new EconomicRecord($event, [new FinancialCommitment($identity(), $event, new Money('1.00'), new FinancialDate('2026-02-01'))])));
            $this->fail('Expected rollback.');
        } catch (FinancialPersistenceFailure $failure) {
            $this->assertNull($failure->getPrevious());
        }
        $this->assertDatabaseCount('economic_events', 0);
        $this->assertDatabaseCount('financial_commitments', 0);
        $this->assertSame(0, DB::table('financial_revisions')->whereIn('resource_type', ['event', 'commitment'])->count());
    }

    public function test_history_preserves_category_identity_and_blocks_reclassification(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx);
        $category = $record->event->category;
        $cases = app(ManageFinancialCategories::class);
        $cases->update($ctx, $category->identity->publicId, new CategoryChanges(name: 'Historical name', status: FinancialStatus::Inactive), 1);
        $history = app(EconomicRecordRepository::class)->find($ctx, $record->event->identity->publicId);
        $this->assertSame('Historical name', $history->event->category->name);
        $this->assertSame($category->identity->publicId, $history->event->category->identity->publicId);
        try {
            $cases->update($ctx, $category->identity->publicId, new CategoryChanges(nature: EconomicNature::Expense), 2);
            $this->fail('Expected history protection.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('financial.category_in_use', $failure->errorCode);
        }
    }

    public function test_locked_history_and_stale_snapshot_cannot_be_saved(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx);
        $repo = app(EconomicRecordRepository::class);
        $updated = app(FinancialOperation::class)->run($ctx, true, fn () => $repo->save($ctx, $record));
        $this->assertSame(2, $updated->event->identity->version);
        try {
            app(FinancialOperation::class)->run($ctx, true, fn () => $repo->save($ctx, $record));
            $this->fail('Expected stale conflict.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('financial.concurrent_update', $failure->errorCode);
        }
        DB::table('economic_events')->where('public_id', $record->event->identity->publicId)->update(['locked_at' => now()]);
        $locked = $repo->find($ctx, $record->event->identity->publicId);
        $this->assertNotNull($locked->lockedAt);
        try {
            app(FinancialOperation::class)->run($ctx, true, fn () => $repo->save($ctx, $locked));
            $this->fail('Expected immutable history.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('financial.history_locked', $failure->errorCode);
        }
    }
}
