<?php

namespace Tests\Feature;

use App\Modules\Financial\Application\UseCases\ManageFinancialCategories;
use App\Modules\Financial\Domain\EconomicNature;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\FinancialTestCase;

#[Group('security')]
class FinancialCategoriesTest extends FinancialTestCase
{
    public function test_default_structure_is_idempotent_and_never_overwrites_customization(): void
    {
        [$user,$org,$context] = $this->tenant();
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id.'/financial-categories';
        $custom = $this->browserRequest('POST', $path, ['name' => 'Custom services', 'nature' => 'income'])->assertCreated();
        $initial = $this->browserRequest('POST', $path.'/initialize')->assertOk()->assertJsonCount(3, 'data');
        $this->assertSame(['Receitas', 'Custos', 'Despesas'], array_column($initial->json('data'), 'name'));
        $root = $initial->json('data.0');
        $this->browserRequest('PATCH', $path.'/'.$root['public_id'], ['version' => 1, 'name' => 'My revenue', 'status' => 'inactive'])->assertOk();
        $again = $this->browserRequest('POST', $path.'/initialize')->assertOk();
        $this->assertSame($root['public_id'], $again->json('data.0.public_id'));
        $this->assertSame('My revenue', $again->json('data.0.name'));
        $this->assertSame('inactive', $again->json('data.0.status'));
        $this->assertDatabaseCount('financial_categories', 4);
        $this->browserRequest('GET', $path)->assertOk()->assertJsonPath('meta.total', 3);
        $this->browserRequest('GET', $path.'?filter[status]=all')->assertOk()->assertJsonPath('meta.total', 4);
        $this->browserRequest('GET', $path.'/'.$root['public_id'])->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->browserRequest('GET', $path.'/'.$custom->json('data.public_id'))->assertOk();
    }

    public function test_hierarchy_renaming_cycles_nature_and_status_have_controlled_updates(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        $cases = app(ManageFinancialCategories::class);
        $a = $cases->create($ctx, 'A', EconomicNature::Expense);
        $b = $cases->create($ctx, 'B', EconomicNature::Expense, $a->identity->publicId);
        $c = $cases->create($ctx, 'C', EconomicNature::Expense, $b->identity->publicId);
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id.'/financial-categories';
        $this->browserRequest('PATCH', $path.'/'.$a->identity->publicId, ['version' => 1, 'parent_public_id' => $c->identity->publicId])->assertConflict()->assertJsonPath('code', 'financial.category_cycle');
        $this->browserRequest('PATCH', $path.'/'.$a->identity->publicId, ['version' => 1, 'nature' => 'income'])->assertConflict()->assertJsonPath('code', 'financial.category_in_use');
        $renamed = $this->browserRequest('PATCH', $path.'/'.$b->identity->publicId, ['version' => 1, 'name' => 'Renamed', 'parent_public_id' => null])->assertOk()->assertJsonPath('data.parent_public_id', null)->assertJsonPath('data.version', 2);
        $this->assertSame($b->identity->publicId, $renamed->json('data.public_id'));
        $this->browserRequest('GET', $path.'/'.$c->identity->publicId)->assertOk()->assertJsonPath('data.parent_public_id', $b->identity->publicId);
        $this->browserRequest('PATCH', $path.'/'.$a->identity->publicId, ['version' => 1, 'status' => 'inactive'])->assertOk();
        $this->browserRequest('POST', $path, ['name' => 'New child', 'nature' => 'expense', 'parent_public_id' => $a->identity->publicId])->assertConflict()->assertJsonPath('code', 'financial.category_inactive');
        $this->browserRequest('PATCH', $path.'/'.$b->identity->publicId, ['version' => 1, 'name' => 'Lost'])->assertConflict()->assertJsonPath('code', 'financial.concurrent_update');
        $this->assertDatabaseCount('financial_categories', 3);
    }

    public function test_parent_references_are_scoped_and_nature_compatible(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        [$other,$otherOrg,$otherCtx] = $this->tenant();
        $cases = app(ManageFinancialCategories::class);
        $foreign = $cases->create($otherCtx, 'Foreign', EconomicNature::Income);
        $expense = $cases->create($ctx, 'Expense', EconomicNature::Expense);
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id.'/financial-categories';
        $this->browserRequest('POST', $path, ['name' => 'Bad ref', 'nature' => 'income', 'parent_public_id' => $foreign->identity->publicId])->assertNotFound();
        $this->browserRequest('POST', $path, ['name' => 'Bad nature', 'nature' => 'income', 'parent_public_id' => $expense->identity->publicId])->assertConflict()->assertJsonPath('code', 'financial.category_nature');
        $this->browserRequest('GET', $path.'/'.$foreign->identity->publicId)->assertNotFound();
        $this->browserRequest('PATCH', $path.'/'.$foreign->identity->publicId, ['version' => 1, 'name' => 'No'])->assertNotFound();
        $this->browserRequest('GET', $path.'?filter[nature]=expense&sort=-name&per_page=1')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.name', 'Expense');
        $this->assertDatabaseCount('financial_categories', 2);
    }

    public function test_postgresql_also_prevents_cross_tenant_parent_reference(): void
    {
        [$user,$org,$ctx] = $this->tenant();
        [$other,$otherOrg,$otherCtx] = $this->tenant();
        $cases = app(ManageFinancialCategories::class);
        $a = $cases->create($ctx, 'A', EconomicNature::Income);
        $b = $cases->create($otherCtx, 'B', EconomicNature::Income);
        $this->expectException(QueryException::class);
        DB::table('financial_categories')->where('public_id', $a->identity->publicId)->update(['parent_public_id' => $b->identity->publicId]);
    }

    public static function roles(): array
    {
        return [['owner', true], ['admin', true], ['member', true], ['viewer', false]];
    }

    #[DataProvider('roles')]
    public function test_category_writes_and_initialization_require_financial_write_permission(string $role, bool $write): void
    {
        [$user,$org] = $this->tenant($role);
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id.'/financial-categories';
        $this->browserRequest('GET', $path)->assertOk();
        $this->browserRequest('POST', $path, ['name' => 'Category', 'nature' => 'cost'])->assertStatus($write ? 201 : 403);
        $this->browserRequest('POST', $path.'/initialize')->assertStatus($write ? 200 : 403);
        if (! $write) {
            $this->assertDatabaseCount('financial_categories', 0);
        }
    }

    public function test_mutations_validate_values_and_never_delete_categories(): void
    {
        [$user,$org] = $this->tenant();
        $this->loginAs($user);
        $path = '/api/v1/organizations/'.$org->public_id.'/financial-categories';
        foreach ([['name' => '', 'nature' => 'cost'], ['name' => 'X', 'nature' => 'transfer'], ['name' => 'X', 'nature' => 'expense', 'default_key' => 'fake'], ['name' => 'X', 'nature' => 'expense', 'organization_id' => 1]] as $body) {
            $this->browserRequest('POST', $path, $body)->assertUnprocessable();
        }
        $this->browserRequest('DELETE', $path.'/00000000-0000-4000-8000-000000000001')->assertStatus(405);
        foreach (['filter[nature]=transfer', 'filter[status]=deleted', 'sort=parent', 'sort[]=name', 'per_page=0'] as $q) {
            $this->browserRequest('GET', $path.'?'.$q)->assertUnprocessable();
        }
    }
}
