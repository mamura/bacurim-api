<?php

namespace Tests\Unit\IdentityAccess;

use App\Modules\IdentityAccess\Domain\Entities\OrganizationMembership;
use App\Modules\IdentityAccess\Domain\MembershipPermission;
use App\Modules\IdentityAccess\Domain\MembershipPolicy;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('security')]
class MembershipPolicyTest extends TestCase
{
    private function member(MembershipRole $role, int $org = 1): OrganizationMembership
    {
        return OrganizationMembership::create('c4ca63e2-92a4-4c17-bf34-c08753927800', $org, 1, $role, new DateTimeImmutable, true, true);
    }

    public static function matrix(): array
    {
        $result = [];
        foreach (MembershipRole::cases() as $role) {
            foreach (MembershipPermission::cases() as $permission) {
                $allowed = match ($permission) {
                    MembershipPermission::ReadFinancial => true,
                    MembershipPermission::WriteFinancial => $role !== MembershipRole::Viewer,
                    MembershipPermission::ManageOwnership => $role === MembershipRole::Owner,
                    default => in_array($role, [MembershipRole::Owner, MembershipRole::Admin], true),
                };
                $result[$role->value.' '.$permission->value] = [$role, $permission, $allowed];
            }
        }

        return $result;
    }

    #[DataProvider('matrix')]
    public function test_explicit_permission_matrix(MembershipRole $role, MembershipPermission $permission, bool $expected): void
    {
        $this->assertSame($expected, (new MembershipPolicy)->allows(1, $this->member($role), true, true, $permission));
    }

    public function test_access_fails_closed_for_missing_revoked_inactive_or_wrong_organization(): void
    {
        $policy = new MembershipPolicy;
        $owner = $this->member(MembershipRole::Owner);
        foreach (MembershipPermission::cases() as $permission) {
            $this->assertFalse($policy->allows(1, null, true, true, $permission));
            $this->assertFalse($policy->allows(2, $owner, true, true, $permission));
            $this->assertFalse($policy->allows(1, $owner, false, true, $permission));
            $this->assertFalse($policy->allows(1, $owner, true, false, $permission));
            $this->assertFalse($policy->allows(1, $owner->revoke($owner->updatedAt), true, true, $permission));
        }
    }

    public function test_admin_cannot_grant_or_modify_owner_even_if_requested_role_is_lower(): void
    {
        $policy = new MembershipPolicy;
        foreach (MembershipRole::cases() as $role) {
            $admin = $this->member(MembershipRole::Admin);
            $this->assertFalse($policy->canManageTarget(1, $admin, true, true, $role, MembershipRole::Owner));
            $this->assertFalse($policy->canManageTarget(1, $admin, true, true, MembershipRole::Owner, $role));
            $this->assertTrue($policy->canManageTarget(1, $this->member(MembershipRole::Owner), true, true, null, $role));
        }
        $this->assertTrue($policy->canManageTarget(1, $admin, true, true, MembershipRole::Viewer, MembershipRole::Admin));
        foreach ([MembershipRole::Member, MembershipRole::Viewer] as $role) {
            $this->assertFalse($policy->canManageTarget(1, $this->member($role), true, true, $role, MembershipRole::Owner));
        }
    }

    public function test_same_user_has_independent_roles_per_organization(): void
    {
        $policy = new MembershipPolicy;
        $this->assertTrue($policy->allows(1, $this->member(MembershipRole::Owner, 1), true, true, MembershipPermission::ManageMembers));
        $this->assertFalse($policy->allows(2, $this->member(MembershipRole::Viewer, 2), true, true, MembershipPermission::WriteFinancial));
    }
}
