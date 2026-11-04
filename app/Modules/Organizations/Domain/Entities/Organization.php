<?php

namespace App\Modules\Organizations\Domain\Entities;

use App\Modules\Organizations\Domain\Exceptions\InvalidOrganization;
use App\Modules\Organizations\Domain\OrganizationStatus;
use App\Modules\Organizations\Domain\ValueObjects\OrganizationDocument;
use DateTimeImmutable;

final readonly class Organization
{
    public string $publicId;

    public string $name;

    public ?string $legalName;

    public function __construct(
        public ?int $id,
        string $publicId,
        string $name,
        ?string $legalName,
        public ?OrganizationDocument $document,
        public OrganizationStatus $status,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
        if ($id !== null && $id < 1) {
            throw new InvalidOrganization('The internal identifier must be positive.');
        }
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $publicId) !== 1) {
            throw new InvalidOrganization('The public identifier must be a UUID.');
        }
        if ($updatedAt < $createdAt) {
            throw new InvalidOrganization('Update time cannot precede creation.');
        }
        $this->publicId = strtolower($publicId);
        $this->name = self::normalizeName($name);
        $this->legalName = $legalName === null || trim($legalName) === '' ? null : self::normalizeName($legalName);
    }

    public static function create(string $publicId, string $name, ?string $legalName, ?OrganizationDocument $document, DateTimeImmutable $now): self
    {
        return new self(null, $publicId, $name, $legalName, $document, OrganizationStatus::Active, $now, $now);
    }

    public function updateDetails(string $name, ?string $legalName, ?OrganizationDocument $document, DateTimeImmutable $now): self
    {
        $this->validateTime($now);

        return new self($this->id, $this->publicId, $name, $legalName, $document, $this->status, $this->createdAt, $now);
    }

    public function changeStatus(OrganizationStatus $status, DateTimeImmutable $now): self
    {
        $this->validateTime($now);

        return new self($this->id, $this->publicId, $this->name, $this->legalName, $this->document, $status, $this->createdAt, $now);
    }

    private function validateTime(DateTimeImmutable $now): void
    {
        if ($now < $this->updatedAt) {
            throw new InvalidOrganization('Update time cannot move backwards.');
        }
    }

    private static function normalizeName(string $name): string
    {
        $name = trim($name);
        if (! mb_check_encoding($name, 'UTF-8') || mb_strlen($name) < 1 || mb_strlen($name) > 255 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            throw new InvalidOrganization('Organization names must contain 1 to 255 characters without control characters.');
        }

        return $name;
    }
}
