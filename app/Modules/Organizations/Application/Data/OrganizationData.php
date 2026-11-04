<?php

namespace App\Modules\Organizations\Application\Data;

use App\Modules\Organizations\Domain\Entities\Organization;
use App\Modules\Organizations\Domain\OrganizationStatus;
use DateTimeImmutable;

final readonly class OrganizationData
{
    public function __construct(
        public string $publicId,
        public string $name,
        public ?string $legalName,
        public ?string $document,
        public OrganizationStatus $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
        public int $version,
    ) {}

    public static function fromEntity(Organization $entity): self
    {
        return new self($entity->publicId, $entity->name, $entity->legalName, $entity->document?->value, $entity->status, $entity->createdAt, $entity->updatedAt, $entity->version);
    }
}
