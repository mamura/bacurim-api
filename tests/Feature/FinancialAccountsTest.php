<?php

namespace Tests\Feature;

use App\Modules\Financial\Application\Data\AccountChanges;
use App\Modules\Financial\Application\UseCases\GetFinancialAccountPosition;
use App\Modules\Financial\Application\UseCases\ManageFinancialAccounts;
use App\Modules\Financial\Domain\AccountType;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Infrastructure\Persistence\FinancialPersistenceFailure;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\FinancialTestCase;

#[Group('security')]
class FinancialAccountsTest extends FinancialTestCase
{
    public function test_http_create_read_partial_update_state_history_and_conflict(): void
    {
        [$user,$org] = $this->tenant();
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id.'/financial-accounts';
        $created = $this->browserRequest('POST', $path, ['name' => ' Banco ', 'type' => 'bank'])->assertCreated()->assertJsonPath('data.name', 'Banco')->assertJsonPath('data.version', 1);
        $id = $created->json('data.public_id');
        $created->assertHeader('Location', $path.'/'.$id);
        $this->assertArrayNotHasKey('balance', $created->json('data'));
        $this->assertArrayNotHasKey('organization_id', $created->json('data'));
        $this->browserRequest('GET', $path.'/'.$id)->assertOk()->assertJsonPath('data.type', 'bank');
        $updated = $this->browserRequest('PATCH', $path.'/'.$id, ['version' => 1, 'name' => 'Renamed', 'status' => 'inactive'])->assertOk()->assertJsonPath('data.version', 2)->assertJsonPath('data.status', 'inactive');
        $this->assertSame($id, $updated->json('data.public_id'));
        $this->browserRequest('PATCH', $path.'/'.$id, ['version' => 1, 'name' => 'Lost update'])->assertConflict()->assertJsonPath('code', 'financial.concurrent_update');
        $this->browserRequest('GET', $path.'/'.$id)->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->browserRequest('PATCH', $path.'/'.$id, ['version' => 2, 'status' => 'active'])->assertOk()->assertJsonPath('data.version', 3);
        $this->assertDatabaseCount('financial_accounts', 1);
        $revisions = DB::table('financial_revisions')->where('resource_public_id', $id)->orderBy('id')->get();
        $this->assertCount(3, $revisions);
        $this->assertSame($user->public_id, $revisions[2]->actor_public_id);
        $this->assertSame('Renamed', json_decode($revisions[1]->after, true)['name']);
        $this->assertSame('Banco', json_decode($revisions[1]->before, true)['name']);
    }

    public static function roles(): array
    {
        return [['owner', true], ['admin', true], ['member', true], ['viewer', false]];
    }

    #[DataProvider('roles')]
    public function test_roles_use_real_sessions_and_no_financial_write_for_viewer(string $role, bool $allowed): void
    {
        [$user,$org] = $this->tenant($role);
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id.'/financial-accounts';
        $this->browserRequest('GET', $path)->assertOk();
        $response = $this->browserRequest('POST', $path, ['name' => 'Cash', 'type' => 'cash']);
        $response->assertStatus($allowed ? 201 : 403);
        if (! $allowed) {
            $this->assertDatabaseCount('financial_accounts', 0);
        }
    }

    public function test_known_foreign_uuid_cannot_be_read_updated_or_listed(): void
    {
        [$a,$orgA,$ctxA] = $this->tenant();
        [$b,$orgB,$ctxB] = $this->tenant();
        $created = app(ManageFinancialAccounts::class)->create($ctxB, 'Private', AccountType::Bank);
        $this->loginAs($a);
        $path = '/api/v1/organizations/'.$orgA->public_id.'/financial-accounts';
        $this->browserRequest('GET', $path.'/'.$created->identity->publicId)->assertNotFound();
        $this->browserRequest('PATCH', $path.'/'.$created->identity->publicId, ['version' => 1, 'name' => 'Leak'])->assertNotFound();
        $this->browserRequest('GET', $path)->assertOk()->assertJsonCount(0, 'data');
        $this->browserRequest('GET', '/api/v1/organizations/'.$orgB->public_id.'/financial-accounts')->assertForbidden();
        $this->assertSame('Private', app(ManageFinancialAccounts::class)->read($ctxB, $created->identity->publicId)->name);
    }

    public function test_pagination_filters_sort_and_unknown_parameters_are_validated(): void
    {
        [$user,$org,$context] = $this->tenant();
        $cases = app(ManageFinancialAccounts::class);
        $a = $cases->create($context, 'A', AccountType::Bank);
        $cases->create($context, 'B', AccountType::Cash);
        $cases->create($context, 'C', AccountType::Bank);
        $cases->update($context, $a->identity->publicId, new AccountChanges(status: FinancialStatus::Inactive), 1);
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id.'/financial-accounts';
        $r = $this->browserRequest('GET', $path.'?per_page=1&filter[type]=bank&sort=-name')->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.name', 'C');
        $this->assertStringContainsString('filter', $r->json('links.next'));
        $this->browserRequest('GET', $path.'?filter[status]=active')->assertOk()->assertJsonPath('meta.total', 2);
        foreach (['per_page=101', 'page=0', 'filter[status]=other', 'filter[organization_id]=1', 'sort=password', 'include=all'] as $query) {
            $this->browserRequest('GET', $path.'?'.$query)->assertUnprocessable();
        }
    }

    public function test_validation_csrf_authentication_and_non_destructive_contract(): void
    {
        [$user,$org] = $this->tenant();
        $path = '/api/v1/organizations/'.$org->public_id.'/financial-accounts';
        $this->browserRequest('GET', $path)->assertUnauthorized();
        $this->loginAs($user);
        $this->browserRequest('POST', $path, ['name' => 'X', 'type' => 'cash'], csrf: false)->assertStatus(419);
        foreach ([['name' => '', 'type' => 'bank'], ['name' => 'X', 'type' => 'invalid'], ['name' => 'X', 'type' => 'cash', 'organization_id' => 123], ['name' => 'X', 'type' => 'cash', 'balance' => '100.00']] as $body) {
            $this->browserRequest('POST', $path, $body)->assertUnprocessable();
        }
        $this->browserRequest('DELETE', $path.'/00000000-0000-4000-8000-000000000001')->assertStatus(405);
        $this->assertDatabaseCount('financial_accounts', 0);
    }

    public function test_position_is_explicitly_unavailable_instead_of_a_fictitious_zero_balance(): void
    {
        [$user,$org,$context] = $this->tenant();
        $account = app(ManageFinancialAccounts::class)->create($context, 'No ledger yet', AccountType::Cash);
        $position = app(GetFinancialAccountPosition::class)->execute($context, $account->identity->publicId);
        $this->assertSame($account->identity->publicId, $position->accountPublicId);
        $this->assertNull($position->balance);
        $this->assertSame('unavailable', $position->source);
        $this->assertSame('BRL', $position->currency);
    }

    public function test_audit_failure_rolls_back_account_and_safe_persistence_error(): void
    {
        [$user,$org,$context] = $this->tenant();
        DB::statement("ALTER TABLE financial_revisions ADD CONSTRAINT test_block_financial_audit CHECK (resource_type <> 'account')");
        try {
            app(ManageFinancialAccounts::class)->create($context, 'Must roll back', AccountType::Cash);
            $this->fail('Expected storage failure.');
        } catch (FinancialPersistenceFailure $failure) {
            $this->assertNull($failure->getPrevious());
        }
        $this->assertDatabaseCount('financial_accounts', 0);
        $this->assertDatabaseCount('financial_revisions', 0);
    }
}
