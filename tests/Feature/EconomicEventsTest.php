<?php

namespace Tests\Feature;

use App\Modules\Financial\Application\Data\CategoryChanges;
use App\Modules\Financial\Application\UseCases\ManageFinancialCategories;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\FinancialRecords;
use Tests\Support\FinancialTestCase;

#[Group('security')]
class EconomicEventsTest extends FinancialTestCase
{
    use FinancialRecords;

    /** @return array<string,mixed> */
    private function body(string $category, string $nature = 'income'): array
    {
        return ['description' => 'Consulting', 'nature' => $nature, 'amount' => '123.45', 'currency' => 'BRL', 'competence_date' => '2026-01-15', 'category_public_id' => $category, 'commitment' => ['due_date' => '2026-02-10']];
    }

    public static function natures(): array
    {
        return [['income', 'receivable'], ['cost', 'payable'], ['expense', 'payable']];
    }

    #[DataProvider('natures')]
    public function test_create_query_and_update_preserve_economic_and_expected_values(string $nature, string $direction): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $category = app(ManageFinancialCategories::class)->create($ctx, 'Category', EconomicNature::from($nature));
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id;
        $created = $this->browserRequest('POST', $path.'/economic-events', $this->body($category->identity->publicId, $nature))->assertCreated()->assertJsonPath('data.amount', '123.45')->assertJsonPath('data.currency', 'BRL')->assertJsonPath('data.competence_date', '2026-01-15')->assertJsonPath('data.commitments.0.nature', $direction)->assertJsonPath('data.commitments.0.status', 'pending');
        $id = $created->json('data.public_id');
        $cid = $created->json('data.commitments.0.public_id');
        $created->assertHeader('Location', $path.'/economic-events/'.$id);
        $this->assertSame($user->public_id, $created->json('data.created_by'));
        $this->assertArrayNotHasKey('organization_id', $created->json('data'));
        $this->browserRequest('GET', $path.'/economic-events/'.$id)->assertOk()->assertJsonPath('data.public_id', $id);
        $this->browserRequest('GET', $path.'/financial-commitments/'.$cid)->assertOk()->assertJsonPath('data.event_public_id', $id)->assertJsonPath('data.pending_amount', '123.45');
        $this->browserRequest('GET', $path.'/economic-events/'.$id.'/financial-commitments')->assertOk()->assertJsonCount(1, 'data');
        $updated = $this->browserRequest('PATCH', $path.'/economic-events/'.$id, ['version' => 1, 'amount' => '200.10', 'commitment' => ['due_date' => '2026-03-20']])->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.amount', '200.10')->assertJsonPath('data.commitments.0.expected_amount', '200.10')->assertJsonPath('data.commitments.0.due_date', '2026-03-20');
        $this->assertSame($cid, $updated->json('data.commitments.0.public_id'));
        $this->assertSame('2026-01-15', $updated->json('data.competence_date'));
        $this->browserRequest('PATCH', $path.'/economic-events/'.$id, ['version' => 1, 'description' => 'Lost'])->assertConflict()->assertJsonPath('code', 'financial.concurrent_update');
        $this->browserRequest('PATCH', $path.'/economic-events/'.$id, ['version' => 2, 'competence_date' => '2025-12-01'])->assertOk()->assertJsonPath('data.commitments.0.due_date', '2026-03-20')->assertJsonPath('data.commitments.0.version', 2);
        $this->assertDatabaseCount('financial_accounts', 0);
        $this->assertDatabaseCount('economic_events', 1);
        $this->assertDatabaseCount('financial_commitments', 1);
    }

    public function test_filters_pagination_and_commitments_remain_tenant_scoped(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $a = $this->economicRecord($ctx, EconomicNature::Income, '10');
        $b = $this->economicRecord($ctx, EconomicNature::Expense, '20');
        [$foreign,$otherOrg,$otherCtx] = $this->tenant();
        $other = $this->economicRecord($otherCtx);
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id;
        $this->browserRequest('GET', $path.'/economic-events?filter[nature]=income&per_page=1&sort=-amount')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.public_id', $a->event->identity->publicId);
        $this->browserRequest('GET', $path.'/economic-events?filter[competence_to]=2026-01-31')->assertOk()->assertJsonPath('meta.total', 2);
        $this->browserRequest('GET', $path.'/economic-events?filter[category_public_id]='.$b->event->category->identity->publicId)->assertOk()->assertJsonPath('meta.total', 1);
        $this->browserRequest('GET', $path.'/financial-commitments?filter[nature]=payable&filter[due_from]=2026-02-01&filter[due_to]=2026-02-28')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.expected_amount', '20.00');
        $this->browserRequest('GET', $path.'/financial-commitments?filter[due_to]=2026-01-01')->assertOk()->assertJsonPath('meta.total', 0);
        foreach (['/economic-events/'.$other->event->identity->publicId, '/financial-commitments/'.$other->commitments[0]->identity->publicId, '/economic-events/'.$other->event->identity->publicId.'/financial-commitments'] as $suffix) {
            $this->browserRequest('GET', $path.$suffix)->assertNotFound();
        }
        $this->browserRequest('PATCH', $path.'/economic-events/'.$other->event->identity->publicId, ['version' => 1, 'description' => 'Foreign'])->assertNotFound();
        $this->browserRequest('GET', $path.'/financial-commitments?filter[event_public_id]='.$other->event->identity->publicId)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_category_history_remains_visible_but_inactive_or_foreign_references_are_rejected(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx);
        $categories = app(ManageFinancialCategories::class);
        $categories->update($ctx, $record->event->category->identity->publicId, new CategoryChanges(status: FinancialStatus::Inactive), 1);
        $expense = $categories->create($ctx, 'Expense', EconomicNature::Expense);
        [$other,$otherOrg,$otherCtx] = $this->tenant();
        $foreign = $categories->create($otherCtx, 'Foreign', EconomicNature::Income);
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id.'/economic-events';
        $this->browserRequest('GET', $path.'/'.$record->event->identity->publicId)->assertOk()->assertJsonPath('data.category.status', 'inactive');
        $this->browserRequest('PATCH', $path.'/'.$record->event->identity->publicId, ['version' => 1, 'description' => 'Historical note'])->assertOk();
        $this->browserRequest('POST', $path, $this->body($record->event->category->identity->publicId))->assertConflict()->assertJsonPath('code', 'financial.category_inactive');
        $this->browserRequest('POST', $path, $this->body($expense->identity->publicId))->assertConflict()->assertJsonPath('code', 'financial.category_nature');
        $this->browserRequest('POST', $path, $this->body($foreign->identity->publicId))->assertNotFound();
        $this->assertDatabaseCount('economic_events', 1);
    }

    public static function invalidBodies(): array
    {
        return [[['amount' => 1.20]], [['amount' => '1.001']], [['amount' => '1e2']], [['amount' => '-1']], [['amount' => '0']], [['currency' => 'USD']], [['competence_date' => '2026-02-29']], [['competence_date' => '0001-01-01']], [['organization_id' => 123]], [['status' => 'realized']], [['account_public_id' => '00000000-0000-4000-8000-000000000001']], [['commitment' => ['expected_amount' => '999.99', 'due_date' => '2026-02-10']]]];
    }

    #[DataProvider('invalidBodies')]
    public function test_invalid_creation_never_persists_partial_financial_records(array $changes): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $category = app(ManageFinancialCategories::class)->create($ctx, 'Category', EconomicNature::Income);
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id.'/economic-events';
        $this->browserRequest('POST', $path, array_replace($this->body($category->identity->publicId), $changes))->assertUnprocessable();
        $this->assertDatabaseCount('economic_events', 0);
        $this->assertDatabaseCount('financial_commitments', 0);
    }

    public function test_multiple_commitments_are_readable_but_not_automatically_redistributed(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx, EconomicNature::Income, '100', [['amount' => '40', 'due_date' => '2026-02-01'], ['amount' => '60', 'due_date' => '2026-03-01']]);
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id.'/economic-events/'.$record->event->identity->publicId;
        $this->browserRequest('PATCH', $path, ['version' => 1, 'description' => 'New description'])->assertOk()->assertJsonCount(2, 'data.commitments');
        $before = DB::table('financial_commitments')->orderBy('id')->get()->toJson();
        $this->browserRequest('PATCH', $path, ['version' => 2, 'amount' => '120'])->assertConflict()->assertJsonPath('code', 'financial.commitment_structure');
        $this->assertSame($before, DB::table('financial_commitments')->orderBy('id')->get()->toJson());
        DB::table('economic_events')->where('public_id', $record->event->identity->publicId)->update(['locked_at' => now()]);
        $this->browserRequest('PATCH', $path, ['version' => 2, 'description' => 'Rewrite realized'])->assertConflict()->assertJsonPath('code', 'financial.history_locked');
    }

    public static function roles(): array
    {
        return [['owner', true], ['admin', true], ['member', true], ['viewer', false]];
    }

    #[DataProvider('roles')]
    public function test_economic_reads_and_writes_follow_membership_permissions(string $role, bool $write): void
    {
        [$owner,$org,$ctx] = $this->tenant();
        $record = $this->economicRecord($ctx);
        $member = MembershipRecord::query()->where('public_id', $ctx->membershipPublicId)->firstOrFail();
        $member->forceFill(['role' => $role])->save();
        $this->loginAs($owner);
        $path = '/api/v1/organizations/'.$org->public_id;
        $this->browserRequest('GET', $path.'/economic-events')->assertOk();
        $this->browserRequest('GET', $path.'/financial-commitments')->assertOk();
        $this->browserRequest('POST', $path.'/economic-events', $this->body($record->event->category->identity->publicId))->assertStatus($write ? 201 : 403);
        $this->browserRequest('PATCH', $path.'/economic-events/'.$record->event->identity->publicId, ['version' => 1, 'description' => 'Update'])->assertStatus($write ? 200 : 403);
    }

    public function test_no_lifecycle_or_standalone_commitment_write_endpoints_exist(): void
    {
        [$user,$org] = $this->tenant();
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id;
        $this->browserRequest('POST', $path.'/financial-commitments', ['amount' => '1'])->assertStatus(405);
        $this->browserRequest('POST', $path.'/financial-commitments/00000000-0000-4000-8000-000000000001/pay')->assertNotFound();
        $this->browserRequest('DELETE', $path.'/economic-events/00000000-0000-4000-8000-000000000001')->assertStatus(405);
        foreach (['filter[competence_from]=2026-03-01&filter[competence_to]=2026-02-01', 'filter[competence_from]=0000-01-01', 'sort[]=amount', 'filter[unknown]=x'] as $query) {
            $this->browserRequest('GET', $path.'/economic-events?'.$query)->assertUnprocessable();
        }
    }
}
