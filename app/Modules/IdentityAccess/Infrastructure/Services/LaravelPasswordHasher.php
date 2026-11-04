<?php

namespace App\Modules\IdentityAccess\Infrastructure\Services;

use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Application\Security\PasswordHash;
use App\Modules\IdentityAccess\Application\Security\PasswordPolicy;
use Illuminate\Support\Facades\Hash;
use SensitiveParameter;

final readonly class LaravelPasswordHasher implements PasswordHasher
{
    public function __construct(private PasswordPolicy $policy) {}

    public function hash(#[SensitiveParameter] string $password): PasswordHash
    {
        $this->policy->validate($password);

        return new PasswordHash(Hash::driver('argon2id')->make($password));
    }

    public function verify(#[SensitiveParameter] string $password, PasswordHash $hash): bool
    {
        if (str_contains($password, "\0") || strlen($password) > 512 || ! mb_check_encoding($password, 'UTF-8')) {
            return false;
        }
        $algorithm = password_get_info($hash->encoded())['algoName'];
        if ($algorithm === 'bcrypt' && strlen($password) > 72) {
            return false;
        }

        return Hash::driver($algorithm === 'bcrypt' ? 'bcrypt' : 'argon2id')->check($password, $hash->encoded());
    }

    public function needsRehash(PasswordHash $hash): bool
    {
        return Hash::driver('argon2id')->needsRehash($hash->encoded());
    }
}
