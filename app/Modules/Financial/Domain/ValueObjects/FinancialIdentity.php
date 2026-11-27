<?php

namespace App\Modules\Financial\Domain\ValueObjects;

use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;
use DateTimeImmutable;

final readonly class FinancialIdentity
{
    public string $publicId;

    public function __construct(public ?int $id, string $publicId, public int $organizationId, public int $version, public DateTimeImmutable $createdAt, public DateTimeImmutable $updatedAt)
    {
        $this->publicId = FinancialText::uuid($publicId);
        if ($organizationId < 1 || ($id !== null && $id < 1) || $version < 0 || (($id === null) !== ($version === 0)) || $updatedAt < $createdAt) {
            throw new InvalidFinancialData('Invalid financial identity or timestamps.');
        }
    }

    public function revised(DateTimeImmutable $now): self
    {
        if ($now < $this->updatedAt) {
            throw new InvalidFinancialData('Update time cannot move backwards.');
        }

        return new self($this->id, $this->publicId, $this->organizationId, $this->version, $this->createdAt, $now);
    }
}
