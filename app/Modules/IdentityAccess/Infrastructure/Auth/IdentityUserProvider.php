<?php

namespace App\Modules\IdentityAccess\Infrastructure\Auth;

use App\Modules\IdentityAccess\Application\Contracts\IdentityClock;
use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserCredentials;
use App\Modules\IdentityAccess\Application\Security\PasswordHash;
use App\Modules\IdentityAccess\Domain\Exceptions\InvalidIdentity;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Hashing\Hasher;
use SensitiveParameter;

final class IdentityUserProvider extends EloquentUserProvider
{
    public function __construct(Hasher $hasher, string $model, private PasswordHasher $passwords, private UserCredentials $credentials, private IdentityClock $clock)
    {
        parent::__construct($hasher, $model);
    }

    /** @param array<string, mixed> $credentials */
    public function retrieveByCredentials(#[SensitiveParameter] array $credentials)
    {
        if (! is_string($credentials['email'] ?? null)) {
            return null;
        }
        try {
            $credentials['email'] = (new EmailAddress($credentials['email']))->value;
        } catch (InvalidIdentity) {
            return null;
        }

        return parent::retrieveByCredentials($credentials);
    }

    /** @param array<string, mixed> $credentials */
    public function validateCredentials(Authenticatable $user, #[SensitiveParameter] array $credentials): bool
    {
        if (! $user instanceof UserRecord || ! is_string($credentials['password'] ?? null)) {
            return false;
        }
        $valid = $this->passwords->verify($credentials['password'], new PasswordHash($user->getAuthPassword()));

        return $valid && $user->status === UserStatus::Active;
    }

    /** @param array<string, mixed> $credentials */
    public function rehashPasswordIfRequired(Authenticatable $user, #[SensitiveParameter] array $credentials, bool $force = false): void
    {
        if (! $user instanceof UserRecord || ! is_string($credentials['password'] ?? null)) {
            return;
        }
        if ($force || $this->passwords->needsRehash(new PasswordHash($user->getAuthPassword()))) {
            // Rehash is not a new password enrollment: legacy passwords keep their existing validity.
            $this->credentials->replace($user->public_id, new PasswordHash($this->hasher->make($credentials['password'])), $user->version, $this->clock->now());
            $user->refresh();
        }
    }
}
