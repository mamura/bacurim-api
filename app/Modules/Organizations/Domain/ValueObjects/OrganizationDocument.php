<?php

namespace App\Modules\Organizations\Domain\ValueObjects;

use App\Modules\Organizations\Domain\Exceptions\InvalidOrganization;

final readonly class OrganizationDocument
{
    public string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(str_replace(['.', '/', '-', ' '], '', trim($value)));
        if (preg_match('/^(?:[0-9]{11}|[A-Z0-9]{12}[0-9]{2})$/D', $value) !== 1 || preg_match('/^(.)\1+$/D', $value) === 1) {
            throw new InvalidOrganization('The organization document must be a valid CPF or CNPJ.');
        }
        $baseLength = strlen($value) - 2;
        $base = substr($value, 0, $baseLength);
        for ($digit = 0; $digit < 2; $digit++) {
            $sum = 0;
            $length = strlen($base);
            for ($index = 0; $index < $length; $index++) {
                $weight = $baseLength === 9 ? $length + 1 - $index : (($length - 1 - $index) % 8) + 2;
                $sum += (ord($base[$index]) - 48) * $weight;
            }
            $remainder = $sum % 11;
            $base .= (string) ($remainder < 2 ? 0 : 11 - $remainder);
        }
        if ($base !== $value) {
            throw new InvalidOrganization('The organization document has invalid check digits.');
        }
        $this->value = $value;
    }
}
