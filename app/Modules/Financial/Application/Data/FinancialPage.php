<?php

namespace App\Modules\Financial\Application\Data;

/** @template T */
final readonly class FinancialPage
{
    /** @param list<T> $items */
    public function __construct(public array $items, public int $total) {}
}
