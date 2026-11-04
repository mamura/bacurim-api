<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\Contracts\IdentityClock;
use App\Modules\IdentityAccess\Application\Contracts\PasswordChangeAuthorizer;
use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserCredentials;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\UserLookup;
use App\Modules\IdentityAccess\Domain\Exceptions\InvalidIdentity;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use SensitiveParameter;

final readonly class ChangeUserPassword
{
    public function __construct(private PasswordChangeAuthorizer $authorizer, private UserLookup $lookup, private UserCredentials $credentials, private PasswordHasher $hasher, private IdentityClock $clock) {}

    public function execute(string $publicId, #[SensitiveParameter] string $currentPassword, #[SensitiveParameter] string $newPassword, int $expectedVersion): UserData
    {
        $this->authorizer->assertMayChange($publicId);
        $user = $this->lookup->get($publicId, $expectedVersion);
        if ($user->status !== UserStatus::Active) {
            throw new ApplicationFailure(FailureKind::Forbidden, 'identity.inactive', 'Inactive users cannot change credentials.');
        }
        $hash = $this->credentials->getHash($publicId, $expectedVersion);
        if (! $this->hasher->verify($currentPassword, $hash)) {
            throw new ApplicationFailure(FailureKind::Forbidden, 'identity.current_password_invalid', 'The current password is invalid.');
        }
        if ($this->hasher->verify($newPassword, $hash)) {
            throw new ApplicationFailure(FailureKind::Validation, 'identity.password_reused', 'The new password must differ from the current password.');
        }
        $newHash = $this->hasher->hash($newPassword);
        try {
            $updated = $this->credentials->replace($publicId, $newHash, $expectedVersion, $this->clock->now());
        } catch (InvalidIdentity $failure) {
            throw new ApplicationFailure(FailureKind::Validation, 'identity.invalid', $failure->getMessage());
        }

        return UserData::fromEntity($updated);
    }
}
