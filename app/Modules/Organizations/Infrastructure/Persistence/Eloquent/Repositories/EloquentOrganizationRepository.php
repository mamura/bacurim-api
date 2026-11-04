<?php

namespace App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Repositories;

use App\Modules\Organizations\Application\Contracts\Persistence\OrganizationRepository;
use App\Modules\Organizations\Domain\Entities\Organization;
use App\Modules\Organizations\Domain\ValueObjects\OrganizationDocument;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class EloquentOrganizationRepository implements OrganizationRepository
{
    public function create(Organization $organization): Organization
    {
        if ($organization->id !== null || $organization->version !== 0) {
            throw new LogicException('Only new organizations can be created.');
        }
        try {
            return DB::transaction(function () use ($organization): Organization {
                $record = new OrganizationRecord;
                $record->forceFill($this->attributes($organization) + [
                    'public_id' => $organization->publicId,
                    'created_at' => $organization->createdAt,
                    'version' => 1,
                ]);
                $record->save();

                return $this->toDomain($record->refresh());
            });
        } catch (UniqueConstraintViolationException) {
            throw new ApplicationFailure(FailureKind::Conflict, 'organizations.public_id_conflict', 'The organization identifier is already registered.');
        }
    }

    public function findByPublicId(string $publicId): ?Organization
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $publicId) !== 1) {
            return null;
        }
        $record = OrganizationRecord::query()->where('public_id', strtolower($publicId))->first();

        return $record === null ? null : $this->toDomain($record);
    }

    public function update(Organization $organization): Organization
    {
        if ($organization->id === null || $organization->version < 1) {
            throw new LogicException('Only persisted organizations can be updated.');
        }

        return DB::transaction(function () use ($organization): Organization {
            $record = OrganizationRecord::query()->whereKey($organization->id)->where('public_id', $organization->publicId)->lockForUpdate()->first();
            if ($record === null) {
                throw new ApplicationFailure(FailureKind::NotFound, 'organizations.not_found', 'Organization not found.');
            }
            if ($record->version !== $organization->version) {
                throw new ApplicationFailure(FailureKind::Conflict, 'organizations.concurrent_update', 'The organization was changed by another operation.');
            }
            $record->forceFill($this->attributes($organization) + ['version' => $organization->version + 1]);
            $record->save();

            return $this->toDomain($record->refresh());
        });
    }

    /** @return array<string, mixed> */
    private function attributes(Organization $organization): array
    {
        return [
            'name' => $organization->name,
            'legal_name' => $organization->legalName,
            'document' => $organization->document?->value,
            'status' => $organization->status->value,
            'updated_at' => $organization->updatedAt,
        ];
    }

    private function toDomain(OrganizationRecord $record): Organization
    {
        return new Organization(
            $record->id, $record->public_id, $record->name, $record->legal_name,
            $record->document === null ? null : new OrganizationDocument($record->document),
            $record->status, $record->created_at->toDateTimeImmutable(), $record->updated_at->toDateTimeImmutable(), $record->version,
        );
    }
}
