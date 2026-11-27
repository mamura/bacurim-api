<?php

namespace App\Modules\Financial\Interfaces\Http\Requests;

use App\Modules\Financial\Domain\EconomicNature;
use Illuminate\Validation\Rule;

final class EconomicEventListRequest extends FinancialQueryRequest
{
    protected function filters(): array
    {
        return ['nature' => ['sometimes', 'required', Rule::enum(EconomicNature::class)], 'competence_from' => ['sometimes', 'required', 'regex:/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', 'date_format:Y-m-d'], 'competence_to' => ['sometimes', 'required', 'regex:/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', 'date_format:Y-m-d', Rule::when($this->filled('filter.competence_from'), 'after_or_equal:filter.competence_from')], 'category_public_id' => ['sometimes', 'required', 'uuid']];
    }

    protected function sorts(): array
    {
        return ['competence_date', 'amount', 'description', 'created_at'];
    }
}
