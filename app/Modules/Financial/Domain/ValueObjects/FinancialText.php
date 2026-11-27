<?php

namespace App\Modules\Financial\Domain\ValueObjects;

use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;

final class FinancialText
{
    public static function name(string $value): string
    {
        $value = trim($value);
        if (! mb_check_encoding($value, 'UTF-8') || mb_strlen($value) < 1 || mb_strlen($value) > 255 || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
            throw new InvalidFinancialData('Text must contain 1 to 255 characters without controls.');
        }

        return $value;
    }

    public static function uuid(string $value): string
    {
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $value)) {
            throw new InvalidFinancialData('A public UUID is required.');
        }

        return strtolower($value);
    }
}
