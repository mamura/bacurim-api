<?php

namespace App\Modules\Financial\Interfaces\Http\Requests;

use App\Modules\Financial\Domain\CommitmentNature;
use Illuminate\Validation\Rule;

final class FinancialCommitmentListRequest extends FinancialQueryRequest
{
    protected function filters(): array
    {
        return ['nature' => ['sometimes', 'required', Rule::enum(CommitmentNature::class)], 'due_from' => ['sometimes', 'required', 'regex:/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', 'date_format:Y-m-d'], 'due_to' => ['sometimes', 'required', 'regex:/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', 'date_format:Y-m-d', Rule::when($this->filled('filter.due_from'), 'after_or_equal:filter.due_from')], 'event_public_id' => ['sometimes', 'required', 'uuid']];
    }

    protected function sorts(): array
    {
        return ['due_date', 'expected_amount', 'created_at'];
    }
}
