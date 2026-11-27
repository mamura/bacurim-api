<?php

namespace Tests\Integration\Security;

use App\Modules\Financial\Application\Data\AccountChanges;
use App\Modules\Financial\Application\UseCases\ManageFinancialAccounts;
use App\Modules\Financial\Domain\AccountType;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantJobIdentity;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantJobRunner;
use App\Modules\IdentityAccess\Application\UseCases\ManageOrganizationMembers;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\FinancialRecords;
use Tests\Support\FinancialTestCase;

#[Group('security')]
class FinancialTenantSecurityTest extends FinancialTestCase
{
    use FinancialRecords;

    private function otherOwner(OrganizationRecord $org): TenantContext
    {
        $user = UserRecord::factory()->create();
        $member = MembershipRecord::factory()->create(['user_id' => $user->id, 'organization_id' => $org->id, 'role' => 'owner']);

        return new TenantContext($org->id, $org->public_id, $user->public_id, $user->version, $member->public_id, $member->version);
    }

    public function test_consultant_switches_real_financial_resources_without_reusing_roles_or_revoked_access(): void
    {
        $consultant = UserRecord::factory()->consultant()->create();
        [$same,$a,$ctxA] = $this->tenant('owner', $consultant);
        [$same,$b,$ctxB] = $this->tenant('viewer', $consultant);
        $adminB = $this->otherOwner($b);
        $accounts = app(ManageFinancialAccounts::class);
        $accountA = $accounts->create($ctxA, 'Account A', AccountType::Cash);
        $accountB = $accounts->create($adminB, 'Account B', AccountType::Bank);
        $eventA = $this->economicRecord($ctxA);
        $eventB = $this->economicRecord($adminB, EconomicNature::Expense);
        $this->loginAs($consultant);
        $base = '/api/v1/organizations/';
        foreach ([[$a, $eventA, $accountA], [$b, $eventB, $accountB], [$a, $eventA, $accountA]] as [$org,$event,$account]) {
            $path = $base.$org->public_id;
            $this->browserRequest('GET', $path.'/financial-accounts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.public_id', $account->identity->publicId);
            $this->browserRequest('GET', $path.'/economic-events')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.public_id', $event->event->identity->publicId);
            $this->browserRequest('GET', $path.'/financial-commitments')->assertOk()->assertJsonCount(1, 'data');
        }
        $this->browserRequest('POST', $base.$b->public_id.'/financial-accounts', ['name' => 'Denied', 'type' => 'cash'])->assertForbidden();
        $this->browserRequest('PATCH', $base.$b->public_id.'/economic-events/'.$eventB->event->identity->publicId, ['version' => 1, 'description' => 'Denied'])->assertForbidden();
        $this->browserRequest('GET', $base.$a->public_id.'/economic-events/'.$eventB->event->identity->publicId)->assertNotFound();
        $this->browserRequest('PATCH', $base.$a->public_id.'/financial-accounts/'.$accountA->identity->publicId, ['version' => 1, 'name' => 'Allowed'])->assertOk();
        app(ManageOrganizationMembers::class)->revoke($adminB, $ctxB->membershipPublicId, 1);
        $this->browserRequest('GET', $base.$b->public_id.'/financial-accounts')->assertForbidden();
        $this->browserRequest('GET', $base.$a->public_id.'/economic-events')->assertOk();
        $this->browserRequest('GET', '/api/v1/auth/me')->assertOk()->assertJsonPath('data.profile', 'consultant');
        $this->browserRequest('GET', '/api/v1/auth/organizations')->assertOk()->assertJsonCount(1, 'data');
        $this->assertFalse(app('request')->attributes->has(TenantContext::class));
    }

    public function test_delayed_financial_work_reauthorizes_and_next_tenant_is_clean(): void
    {
        $consultant = UserRecord::factory()->consultant()->create();
        [$same,$a,$ctxA] = $this->tenant('owner', $consultant);
        [$same,$b,$ctxB] = $this->tenant('owner', $consultant);
        $accounts = app(ManageFinancialAccounts::class);
        $accountA = $accounts->create($ctxA, 'A', AccountType::Cash);
        $accountB = $accounts->create($ctxB, 'B', AccountType::Bank);
        $payload = TenantJobIdentity::fromContext($ctxA);
        $json = json_decode(json_encode($payload, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $payload = new TenantJobIdentity($json['organization_public_id'], $json['actor_public_id']);
        $other = $this->otherOwner($a);
        app(ManageOrganizationMembers::class)->changeRole($other, $ctxA->membershipPublicId, MembershipRole::Viewer, 1);
        $runner = app(TenantJobRunner::class);
        try {
            $runner->run($payload, TenantCapability::WriteFinancial, fn ($context) => $accounts->update($context, $accountA->identity->publicId, new AccountChanges(name: 'Denied'), 1));
            $this->fail('Expected fresh authorization.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('tenant.forbidden', $failure->errorCode);
        }
        $updated = $runner->run(TenantJobIdentity::fromContext($ctxB), TenantCapability::WriteFinancial, fn ($context) => $accounts->update($context, $accountB->identity->publicId, new AccountChanges(name: 'B allowed'), 1));
        $this->assertSame('B allowed', $updated->name);
        $this->assertSame('A', DB::table('financial_accounts')->where('public_id', $accountA->identity->publicId)->value('name'));
    }

    public function test_foreign_category_cannot_be_assigned_during_event_update_and_changes_rollback(): void
    {
        [$user,$a,$ctxA] = $this->tenant();
        [$other,$b,$ctxB] = $this->tenant();
        $eventA = $this->economicRecord($ctxA);
        $eventB = $this->economicRecord($ctxB);
        $this->loginAs($user);
        $before = DB::table('economic_events')->where('public_id', $eventA->event->identity->publicId)->first();
        $this->browserRequest('PATCH', '/api/v1/organizations/'.$a->public_id.'/economic-events/'.$eventA->event->identity->publicId, ['version' => 1, 'amount' => '900', 'category_public_id' => $eventB->event->category->identity->publicId])->assertNotFound();
        $this->assertEquals($before, DB::table('economic_events')->where('public_id', $eventA->event->identity->publicId)->first());
        $this->assertSame('100.00', DB::table('financial_commitments')->where('event_public_id', $eventA->event->identity->publicId)->value('expected_amount'));
    }
}
