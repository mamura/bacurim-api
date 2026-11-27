<?php

namespace App\Modules\Financial\Domain\Entities;

use App\Modules\Financial\Domain\AccountType;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\Modules\Financial\Domain\ValueObjects\FinancialText;
use DateTimeImmutable;

final readonly class FinancialAccount
{
    public string $name;

    public function __construct(public FinancialIdentity $identity, string $name, public AccountType $type, public FinancialStatus $status = FinancialStatus::Active)
    {
        $this->name = FinancialText::name($name);
    }

    public function revise(string $name, AccountType $type, FinancialStatus $status, DateTimeImmutable $now): self
    {
        return new self($this->identity->revised($now), $name, $type, $status);
    }
}
