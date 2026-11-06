<?php

namespace Tests\Unit\IdentityAccess;

use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\UserProfile;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('security')]
class UserProfileTest extends TestCase
{
    public function test_profile_is_typed_and_preserved_by_domain_transitions(): void
    {
        $time = new DateTimeImmutable;
        $user = new User(null, 'c4ca63e2-92a4-4c17-bf34-c08753927800', 'User', new EmailAddress('user@example.com'), UserStatus::Active, null, $time, $time, profile: UserProfile::Consultant);
        $this->assertSame(UserProfile::Consultant, $user->updateDetails('New', $user->email, $time)->changeStatus(UserStatus::Inactive, $time)->profile);
        $this->assertSame(UserProfile::Standard, User::create($user->publicId, $user->name, $user->email, $time)->profile);
        $this->expectException(\ValueError::class);
        UserProfile::from('owner');
    }
}
