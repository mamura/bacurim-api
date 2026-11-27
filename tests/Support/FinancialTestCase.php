<?php

namespace Tests\Support;

use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class FinancialTestCase extends TestCase
{
    use RefreshDatabase, StatefulBrowser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configureBrowser();
    }

    /** @return array{UserRecord,OrganizationRecord,TenantContext} */
    protected function tenant(string $role = 'owner', ?UserRecord $user = null): array
    {
        $user ??= UserRecord::factory()->create();
        $org = OrganizationRecord::factory()->create();
        $membership = MembershipRecord::factory()->create(['user_id' => $user->id, 'organization_id' => $org->id, 'role' => $role]);

        return [$user, $org, new TenantContext($org->id, $org->public_id, $user->public_id, $user->version, $membership->public_id, $membership->version)];
    }

    protected function loginAs(UserRecord $user): void
    {
        $this->browserCookies = [];
        $this->browserRequest('GET', '/sanctum/csrf-cookie')->assertNoContent();
        $this->browserRequest('POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'testing-only-password'])->assertOk();
    }
}
