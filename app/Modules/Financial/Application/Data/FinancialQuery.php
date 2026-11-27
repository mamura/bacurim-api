<?php

namespace App\Modules\Financial\Application\Data;

use App\Modules\Financial\Domain\Exceptions\InvalidFinancialData;

final readonly class FinancialQuery
{
    /** @param array<string,string> $filters */
    public function __construct(public int $page = 1, public int $perPage = 20, public array $filters = [], public string $sort = 'created_at')
    {
        if ($page < 1 || $page > 1000000 || $perPage < 1 || $perPage > 100) {
            throw new InvalidFinancialData('Pagination is outside supported bounds.');
        }
    }
}
