<?php

namespace App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\IdentityAccess\Application\Contracts\Persistence\MembershipRepository;
use App\Modules\IdentityAccess\Domain\Entities\OrganizationMembership;
use App\Modules\IdentityAccess\Domain\MembershipStatus;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\IdentityPersistenceFailure;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentMembershipRepository implements MembershipRepository
{
    public function create(OrganizationMembership $membership): OrganizationMembership
    {
        if ($membership->id !== null) {
            throw new LogicException('Only new memberships can be created.');
        }

        return $this->guard(fn (): OrganizationMembership => DB::transaction(function () use ($membership): OrganizationMembership {
            $record = new MembershipRecord;
            $record->forceFill($this->attributes($membership) + ['public_id' => $membership->publicId, 'organization_id' => $membership->organizationId, 'user_id' => $membership->userId, 'created_at' => $membership->createdAt, 'version' => 1]);
            $record->save();

            return $this->toDomain($record->refresh());
        }));
    }

    public function update(OrganizationMembership $membership): OrganizationMembership
    {
        if ($membership->id === null) {
            throw new LogicException('Only persisted memberships can be updated.');
        }

        return $this->guard(fn (): OrganizationMembership => DB::transaction(function () use ($membership): OrganizationMembership {
            $record = MembershipRecord::query()->where('public_id', $membership->publicId)->lockForUpdate()->first();
            if ($record === null) {
                throw new ApplicationFailure(FailureKind::NotFound, 'memberships.not_found', 'Membership not found.');
            }
            if ($record->version !== $membership->version) {
                throw new ApplicationFailure(FailureKind::Conflict, 'memberships.concurrent_update', 'The membership was changed by another operation.');
            }
            if ($record->id !== $membership->id || $record->organization_id !== $membership->organizationId || $record->user_id !== $membership->userId || $record->created_at->toDateTimeImmutable() != $membership->createdAt) {
                throw new LogicException('Membership identity cannot be reassigned.');
            }
            $record->forceFill($this->attributes($membership) + ['version' => $membership->version + 1]);
            $record->save();

            return $this->toDomain($record->refresh());
        }));
    }

    public function findByPublicId(string $publicId): ?OrganizationMembership
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $publicId) !== 1) {
            return null;
        }

        return $this->guard(function () use ($publicId): ?OrganizationMembership {
            $record = MembershipRecord::query()->where('public_id', strtolower($publicId))->first();

            return $record === null ? null : $this->toDomain($record);
        });
    }

    public function findForUser(int $organizationId, int $userId): ?OrganizationMembership
    {
        return $this->guard(function () use ($organizationId, $userId): ?OrganizationMembership {
            $record = MembershipRecord::query()->where('organization_id', $organizationId)->where('user_id', $userId)->first();

            return $record === null ? null : $this->toDomain($record);
        });
    }

    public function findActive(int $organizationId, int $userId): ?OrganizationMembership
    {
        $membership = $this->findForUser($organizationId, $userId);

        return $membership?->status === MembershipStatus::Active ? $membership : null;
    }

    public function forOrganization(int $organizationId): array
    {
        return $this->guard(fn (): array => MembershipRecord::query()->where('organization_id', $organizationId)->orderBy('id')->get()->map(fn (MembershipRecord $record): OrganizationMembership => $this->toDomain($record))->all());
    }

    public function forUser(int $userId): array
    {
        return $this->guard(fn (): array => MembershipRecord::query()->where('user_id', $userId)->orderBy('id')->get()->map(fn (MembershipRecord $record): OrganizationMembership => $this->toDomain($record))->all());
    }

    /** @return array<string,mixed> */
    private function attributes(OrganizationMembership $membership): array
    {
        return ['role' => $membership->role, 'status' => $membership->status, 'updated_at' => $membership->updatedAt, 'revoked_at' => $membership->revokedAt];
    }

    private function toDomain(MembershipRecord $record): OrganizationMembership
    {
        return new OrganizationMembership($record->id, $record->public_id, $record->organization_id, $record->user_id, $record->role, $record->status, $record->created_at->toDateTimeImmutable(), $record->updated_at->toDateTimeImmutable(), $record->revoked_at?->toDateTimeImmutable(), $record->version);
    }

    /**
     * @template T
     *
     * @param  callable():T  $operation
     * @return T
     */
    private function guard(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $failure) {
            if ($failure->getCode() === '23505') {
                throw new ApplicationFailure(FailureKind::Conflict, 'memberships.duplicate', 'A membership or its identifier already exists.');
            }
            throw new IdentityPersistenceFailure;
        }
    }
}
