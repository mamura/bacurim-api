<?php

namespace Tests\Integration\Security;

use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantAuthorization;
use App\Modules\IdentityAccess\Application\Contracts\PublicApi\TenantCapability;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\UseCases\ResolveTenantContext;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('security')]
class ProfileAuthorizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    public static function rolesAndProfiles(): array
    {
        $cases = [];
        foreach (['standard', 'consultant'] as $profile) {
            foreach (['owner', 'admin', 'member', 'viewer'] as $role) {
                $cases[$profile.'-'.$role] = [$profile, $role];
            }
        }

        return $cases;
    }

    private function forbidden(callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected tenant denial.');
        } catch (ApplicationFailure $failure) {
            $this->assertSame('tenant.forbidden', $failure->errorCode);
        }
    }

    #[DataProvider('rolesAndProfiles')]
    public function test_platform_profile_never_overrides_membership_role_or_scope(string $profile, string $role): void
    {
        $user = UserRecord::factory()->create(['profile' => $profile]);
        $membership = MembershipRecord::factory()->create(['user_id' => $user->id, 'role' => $role]);
        $org = OrganizationRecord::query()->findOrFail($membership->organization_id);
        $identity = UserData::fromEntity(app(UserRepository::class)->findByPublicId($user->public_id));
        $resolver = app(ResolveTenantContext::class);
        $context = $resolver->execute($org->public_id, $identity);
        $authorization = app(TenantAuthorization::class);
        $authorization->authorize($context, TenantCapability::ReadFinancial);
        if ($role === 'viewer') {
            $this->forbidden(fn () => $authorization->authorize($context, TenantCapability::WriteFinancial));
        } else {
            $authorization->authorize($context, TenantCapability::WriteFinancial);
        }
        if (in_array($role, ['owner', 'admin'], true)) {
            $authorization->authorize($context, TenantCapability::ManageMembers);
        } else {
            $this->forbidden(fn () => $authorization->authorize($context, TenantCapability::ManageMembers));
        }
        $foreign = OrganizationRecord::factory()->create();
        $this->forbidden(fn () => $resolver->execute($foreign->public_id, $identity));
        $membership->forceFill(['status' => 'revoked', 'version' => 2, 'revoked_at' => $membership->updated_at])->save();
        $this->forbidden(fn () => $authorization->authorize($context, TenantCapability::ReadFinancial));
        $this->forbidden(fn () => $resolver->execute($org->public_id, $identity));
        $this->assertSame($profile, $user->fresh()->profile->value);
    }
}
