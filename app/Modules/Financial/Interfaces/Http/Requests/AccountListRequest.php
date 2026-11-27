<?php

namespace App\Modules\Financial\Interfaces\Http\Requests;

use App\Modules\Financial\Domain\AccountType;
use App\Modules\Financial\Domain\FinancialStatus;
use Illuminate\Validation\Rule;

final class AccountListRequest extends FinancialQueryRequest
{
    protected function filters(): array
    {
        return ['status' => ['sometimes', 'required', Rule::enum(FinancialStatus::class)], 'type' => ['sometimes', 'required', Rule::enum(AccountType::class)]];
    }

    protected function sorts(): array
    {
        return ['name', 'type', 'status', 'created_at'];
    }
}
