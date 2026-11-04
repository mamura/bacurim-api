<?php

namespace Tests\Unit\IdentityAccess;

use App\Modules\IdentityAccess\Domain\Entities\OrganizationMembership;
use App\Modules\IdentityAccess\Domain\Exceptions\InvalidMembership;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Domain\MembershipStatus;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('security')]
class OrganizationMembershipTest extends TestCase
{
    private const UUID = 'c4ca63e2-92a4-4c17-bf34-c08753927800';

    private function membership(): OrganizationMembership
    {
        return OrganizationMembership::create(strtoupper(self::UUID), 2, 3, MembershipRole::Owner, new DateTimeImmutable('2026-11-04T12:00:00Z'), true, true);
    }

    public function test_lifecycle_preserves_references_and_identity_without_mutating_original(): void
    {
        $member = $this->membership();
        $later = $member->updatedAt->modify('+1 second');
        $changed = $member->changeRole(MembershipRole::Viewer, $later);
        $revoked = $changed->revoke($later);
        $active = $revoked->activate($later->modify('+1 second'), true, true);
        $this->assertSame(self::UUID, $active->publicId);
        $this->assertSame(2, $active->organizationId);
        $this->assertSame(3, $active->userId);
        $this->assertSame(MembershipRole::Owner, $member->role);
        $this->assertSame(MembershipRole::Viewer, $active->role);
        $this->assertSame(MembershipStatus::Revoked, $revoked->status);
        $this->assertEquals($later, $revoked->revokedAt);
        $this->assertNull($active->revokedAt);
        $this->assertNull($active->id);
        $this->assertSame(0, $active->version);
        $this->assertEquals($member->createdAt, $active->createdAt);
    }

    public function test_effective_access_requires_all_three_active_states(): void
    {
        $member = $this->membership();
        $this->assertTrue($member->isEffective(true, true));
        $this->assertFalse($member->isEffective(false, true));
        $this->assertFalse($member->isEffective(true, false));
        $this->assertFalse($member->revoke($member->updatedAt)->isEffective(true, true));
    }

    public static function inactiveReferences(): array
    {
        return [[false, true], [true, false], [false, false]];
    }

    #[DataProvider('inactiveReferences')]
    public function test_creation_rejects_inactive_references(bool $userActive, bool $organizationActive): void
    {
        $this->expectException(InvalidMembership::class);
        OrganizationMembership::create(self::UUID, 1, 1, MembershipRole::Member, new DateTimeImmutable, $userActive, $organizationActive);
    }

    #[DataProvider('inactiveReferences')]
    public function test_reactivation_rejects_inactive_references(bool $userActive, bool $organizationActive): void
    {
        $member = $this->membership();
        $this->expectException(InvalidMembership::class);
        $member->revoke($member->updatedAt)->activate($member->updatedAt, $userActive, $organizationActive);
    }

    public static function invalidStates(): array
    {
        return [[null, 0, 1, 0], [null, 1, 0, 0], [0, 1, 1, 1], [null, 1, 1, 1], [1, 1, 1, 0], [1, 1, 1, -1]];
    }

    #[DataProvider('invalidStates')]
    public function test_invalid_persistence_states_are_rejected(?int $id, int $org, int $user, int $version): void
    {
        $now = new DateTimeImmutable;
        $this->expectException(InvalidMembership::class);
        new OrganizationMembership($id, self::UUID, $org, $user, MembershipRole::Member, MembershipStatus::Active, $now, $now, null, $version);
    }

    public static function invalidTransitions(): array
    {
        return [['activate'], ['revoke_twice'], ['role_revoked'], ['backwards'], ['invalid_uuid'], ['missing_revocation'], ['active_revocation'], ['revocation_time']];
    }

    #[DataProvider('invalidTransitions')]
    public function test_invalid_transitions_and_dates_are_rejected(string $scenario): void
    {
        $member = $this->membership();
        $now = $member->updatedAt;
        $this->expectException(InvalidMembership::class);
        match ($scenario) {
            'activate' => $member->activate($now, true, true),
            'revoke_twice' => $member->revoke($now)->revoke($now),
            'role_revoked' => $member->revoke($now)->changeRole(MembershipRole::Admin, $now),
            'backwards' => $member->revoke($now->modify('-1 second')),
            'invalid_uuid' => OrganizationMembership::create('invalid', 1, 1, MembershipRole::Member, $now, true, true),
            'missing_revocation' => new OrganizationMembership(null, self::UUID, 1, 1, MembershipRole::Member, MembershipStatus::Revoked, $now, $now),
            'active_revocation' => new OrganizationMembership(null, self::UUID, 1, 1, MembershipRole::Member, MembershipStatus::Active, $now, $now, $now),
            'revocation_time' => new OrganizationMembership(null, self::UUID, 1, 1, MembershipRole::Member, MembershipStatus::Revoked, $now, $now, $now->modify('-1 second')),
        };
    }
}
