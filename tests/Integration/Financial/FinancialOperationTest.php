<?php

namespace Tests\Integration\Financial;

use App\Modules\Financial\Application\FinancialOperation;
use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class FinancialOperationTest extends TestCase
{
    use RefreshDatabase;

    private function context(string $role): TenantContext
    {
        $member = MembershipRecord::factory()->create(['role' => $role]);
        $org = OrganizationRecord::query()->findOrFail($member->organization_id);
        $user = UserRecord::query()->findOrFail($member->user_id);

        return new TenantContext($org->id, $org->public_id, $user->public_id, $user->version, $member->public_id, $member->version);
    }

    public static function roles(): array
    {
        return [['owner'], ['admin'], ['member'], ['viewer']];
    }

    #[DataProvider('roles')]
    public function test_financial_operations_use_public_tenant_authorization(string $role): void
    {
        $context = $this->context($role);
        $operation = app(FinancialOperation::class);
        $this->assertSame('read', $operation->run($context, false, fn () => 'read'));
        if ($role === 'viewer') {
            $this->expectException(ApplicationFailure::class);
        }
        $this->assertSame('write', $operation->run($context, true, fn () => 'write'));
    }

    public function test_exception_rolls_back_callback_and_domain_errors_are_public_validation(): void
    {
        $context = $this->context('owner');
        $original = DB::table('organizations')->where('id', $context->organizationId)->value('name');
        try {
            app(FinancialOperation::class)->run($context, true, function () use ($context) {
                DB::table('organizations')->where('id', $context->organizationId)->update(['name' => 'Must roll back']);
                throw new InvalidFinancialData('A safe validation message.');
            });
            $this->fail('Expected validation.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('financial.invalid', $failure->errorCode);
        }
        $this->assertSame($original, DB::table('organizations')->where('id', $context->organizationId)->value('name'));
    }

    public function test_revoked_context_never_executes_callback(): void
    {
        $context = $this->context('owner');
        DB::table('organization_memberships')->where('public_id', $context->membershipPublicId)->update(['version' => 2]);
        $called = false;
        try {
            app(FinancialOperation::class)->run($context, false, function () use (&$called) {
                $called = true;
            });
            $this->fail('Expected stale context denial.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('tenant.forbidden', $failure->errorCode);
        }
        $this->assertFalse($called);
    }
}
