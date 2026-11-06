<?php

namespace App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserCredentials;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Security\PasswordHash;
use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\IdentityPersistenceFailure;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use SensitiveParameter;

final class EloquentUserRepository implements UserCredentials, UserRepository
{
    public function create(User $user, #[SensitiveParameter] PasswordHash $password): User
    {
        if ($user->id !== null) {
            throw new LogicException('Only new identities can be created.');
        }

        return $this->guard(function () use ($user, $password): User {
            return DB::transaction(function () use ($user, $password): User {
                $record = new UserRecord;
                $record->forceFill($this->attributes($user) + ['public_id' => $user->publicId, 'created_at' => $user->createdAt, 'password' => $password->encoded(), 'version' => 1]);
                $record->save();

                return $this->toDomain($record->refresh());
            });
        });
    }

    public function findByPublicId(string $publicId): ?User
    {
        if (! $this->validId($publicId)) {
            return null;
        }

        return $this->guard(function () use ($publicId): ?User {
            $record = UserRecord::query()->where('public_id', strtolower($publicId))->first();

            return $record === null ? null : $this->toDomain($record);
        });
    }

    public function findByEmail(EmailAddress $email): ?User
    {
        return $this->guard(function () use ($email): ?User {
            $record = UserRecord::query()->where('email', $email->value)->first();

            return $record === null ? null : $this->toDomain($record);
        });
    }

    public function update(User $user): User
    {
        if ($user->id === null) {
            throw new LogicException('Only persisted identities can be updated.');
        }

        return $this->guard(function () use ($user): User {
            return DB::transaction(function () use ($user): User {
                $record = $this->requireRecord($user->publicId, $user->version, true);
                if ($record->id !== $user->id) {
                    throw new LogicException('Identity cannot be reassigned.');
                }
                $record->forceFill($this->attributes($user) + ['version' => $user->version + 1]);
                $record->save();

                return $this->toDomain($record->refresh());
            });
        });
    }

    public function getHash(string $publicId, int $expectedVersion): PasswordHash
    {
        return $this->guard(fn (): PasswordHash => new PasswordHash($this->requireRecord($publicId, $expectedVersion)->password));
    }

    public function replace(string $publicId, #[SensitiveParameter] PasswordHash $hash, int $expectedVersion, DateTimeImmutable $now): User
    {
        return $this->guard(function () use ($publicId, $hash, $expectedVersion, $now): User {
            return DB::transaction(function () use ($publicId, $hash, $expectedVersion, $now): User {
                $record = $this->requireRecord($publicId, $expectedVersion, true);
                $entity = $this->toDomain($record);
                $entity->changeStatus($entity->status, $now);
                $record->forceFill(['password' => $hash->encoded(), 'remember_token' => null, 'version' => $expectedVersion + 1, 'updated_at' => $now]);
                $record->save();

                return $this->toDomain($record->refresh());
            });
        });
    }

    private function validId(string $publicId): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $publicId) === 1;
    }

    private function requireRecord(string $publicId, int $version, bool $lock = false): UserRecord
    {
        if (! $this->validId($publicId)) {
            throw new ApplicationFailure(FailureKind::NotFound, 'identity.not_found', 'User not found.');
        }
        $query = UserRecord::query()->where('public_id', strtolower($publicId));
        if ($lock) {
            $query->lockForUpdate();
        }
        $record = $query->first();
        if ($record === null) {
            throw new ApplicationFailure(FailureKind::NotFound, 'identity.not_found', 'User not found.');
        }
        if ($record->version !== $version) {
            throw new ApplicationFailure(FailureKind::Conflict, 'identity.concurrent_update', 'The user was changed by another operation.');
        }

        return $record;
    }

    /** @return array<string, mixed> */
    private function attributes(User $user): array
    {
        return ['profile' => $user->profile->value, 'name' => $user->name, 'email' => $user->email->value, 'status' => $user->status->value, 'email_verified_at' => $user->emailVerifiedAt, 'updated_at' => $user->updatedAt];
    }

    private function toDomain(UserRecord $record): User
    {
        return new User($record->id, $record->public_id, $record->name, new EmailAddress($record->email), $record->status, $record->email_verified_at?->toDateTimeImmutable(), $record->created_at->toDateTimeImmutable(), $record->updated_at->toDateTimeImmutable(), $record->version, $record->profile);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    private function guard(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $failure) {
            if ($failure->getCode() === '23505') {
                if (str_contains($failure->getMessage(), 'users_email_unique')) {
                    throw new ApplicationFailure(FailureKind::Conflict, 'identity.email_conflict', 'The email address is already registered.');
                }
                if (str_contains($failure->getMessage(), 'users_public_id_unique')) {
                    throw new ApplicationFailure(FailureKind::Conflict, 'identity.public_id_conflict', 'The user identifier is already registered.');
                }
            }
            // Never attach QueryException: its SQL bindings may contain credentials.
            throw new IdentityPersistenceFailure;
        }
    }
}
