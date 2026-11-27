<?php

namespace App\Modules\Financial\Domain\ValueObjects;

use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;

final readonly class Money
{
    private const MAX = 999999999999999999;

    public int $minor;

    public string $amount;

    public function __construct(mixed $amount, public string $currency = 'BRL')
    {
        if (! is_string($amount) || $currency !== 'BRL' || ! preg_match('/^(0|[1-9][0-9]{0,15})(?:\.([0-9]{1,2}))?$/D', $amount, $parts)) {
            throw new InvalidFinancialData('Money requires BRL and a non-negative decimal string with at most two decimal places.');
        }
        $this->minor = ((int) $parts[1]) * 100 + (int) str_pad($parts[2] ?? '', 2, '0');
        $this->amount = intdiv($this->minor, 100).'.'.str_pad((string) ($this->minor % 100), 2, '0', STR_PAD_LEFT);
    }

    public function plus(self $other): self
    {
        if ($this->minor > self::MAX - $other->minor) {
            throw new InvalidFinancialData('Money exceeds the supported precision.');
        }

        return self::fromMinor($this->minor + $other->minor);
    }

    public function minus(self $other): self
    {
        if ($other->minor > $this->minor) {
            throw new InvalidFinancialData('Money cannot become negative.');
        }

        return self::fromMinor($this->minor - $other->minor);
    }

    private static function fromMinor(int $minor): self
    {
        return new self(intdiv($minor, 100).'.'.str_pad((string) ($minor % 100), 2, '0', STR_PAD_LEFT));
    }

    public function requirePositive(): void
    {
        if ($this->minor === 0) {
            throw new InvalidFinancialData('The amount must be greater than zero.');
        }
    }
}
