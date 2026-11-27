<?php

namespace App\Modules\Financial\Infrastructure\Services;

use App\Modules\Financial\Application\Contracts\FinancialIds;
use Illuminate\Support\Str;

final class UuidFinancialIds implements FinancialIds
{
    public function generate(): string
    {
        return (string) Str::uuid();
    }
}
