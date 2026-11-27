<?php

namespace Tests\Integration\Financial;

use App\Modules\Financial\Application\Data\EconomicEventChanges;
use App\Modules\Financial\Application\UseCases\ManageEconomicEvents;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Infrastructure\Persistence\FinancialPersistenceFailure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\FinancialRecords;
use Tests\Support\FinancialTestCase;

#[Group('security')]
class EconomicIntegrityRegressionTest extends FinancialTestCase
{
    use FinancialRecords;

    public function test_failure_updating_commitment_rolls_back_event_and_all_revisions(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx);
        $before = DB::table('economic_events')->where('public_id', $record->event->identity->publicId)->first();
        $children = DB::table('financial_commitments')->orderBy('id')->get()->toJson();
        $audit = DB::table('financial_revisions')->orderBy('id')->get()->toJson();
        DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        DB::statement('ALTER TABLE financial_commitments ADD CONSTRAINT test_reject_update CHECK(expected_amount <> 500)');
        try {
            app(ManageEconomicEvents::class)->update($ctx, $record->event->identity->publicId, new EconomicEventChanges(amount: '500'), 1);
            $this->fail('Expected failed child update.');
        } catch (FinancialPersistenceFailure $failure) {
            $this->assertNull($failure->getPrevious());
        }
        $this->assertEquals($before, DB::table('economic_events')->where('public_id', $record->event->identity->publicId)->first());
        $this->assertSame($children, DB::table('financial_commitments')->orderBy('id')->get()->toJson());
        $this->assertSame($audit, DB::table('financial_revisions')->orderBy('id')->get()->toJson());
    }

    public function test_late_event_audit_failure_rolls_back_successful_commitment_update(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx);
        $before = DB::table('financial_revisions')->orderBy('id')->get()->toJson();
        DB::statement("ALTER TABLE financial_revisions ADD CONSTRAINT test_reject_revision CHECK(resource_type <> 'event' OR (\"after\"->>'version')::integer < 2)");
        try {
            app(ManageEconomicEvents::class)->update($ctx, $record->event->identity->publicId, new EconomicEventChanges(amount: '200'), 1);
            $this->fail('Expected audit rollback.');
        } catch (FinancialPersistenceFailure $failure) {
            $this->assertNull($failure->getPrevious());
        }
        $this->assertSame('100.00', DB::table('economic_events')->value('amount'));
        $this->assertSame('100.00', DB::table('financial_commitments')->value('expected_amount'));
        $this->assertSame(1, DB::table('financial_commitments')->value('version'));
        $this->assertSame($before, DB::table('financial_revisions')->orderBy('id')->get()->toJson());
    }

    public function test_maximum_supported_value_round_trips_without_float_or_rounding(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx, EconomicNature::Expense, '9999999999999999.99');
        $this->assertSame('9999999999999999.99', $record->event->amount->amount);
        $this->assertSame($record->event->amount->amount, $record->commitments[0]->expectedAmount->amount);
        $this->loginAs($user);
        $this->browserRequest('GET', '/api/v1/organizations/'.$org->public_id.'/economic-events/'.$record->event->identity->publicId)->assertOk()->assertJsonPath('data.amount', '9999999999999999.99');
    }

    public function test_postgresql_preserves_historical_category_nature_even_if_application_is_bypassed(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx);
        $this->expectException(QueryException::class);
        DB::table('financial_categories')->where('public_id', $record->event->category->identity->publicId)->update(['nature' => 'expense']);
    }
}
