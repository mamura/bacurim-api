<?php

namespace App\Modules\Financial\Infrastructure\Persistence;

final class FinancialPersistenceFailure extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Financial persistence failed.');
    }
}
