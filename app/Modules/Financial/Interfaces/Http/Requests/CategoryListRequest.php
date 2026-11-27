<?php

namespace App\Modules\Financial\Interfaces\Http\Requests;

use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Domain\EconomicNature;
use Illuminate\Validation\Rule;

final class CategoryListRequest extends FinancialQueryRequest
{
    protected function filters(): array
    {
        return ['status' => ['sometimes', 'required', Rule::in(['active', 'inactive', 'all'])], 'nature' => ['sometimes', 'required', Rule::enum(EconomicNature::class)]];
    }

    protected function sorts(): array
    {
        return ['name', 'nature', 'status', 'created_at'];
    }

    public function financialQuery(): FinancialQuery
    {
        $query = parent::financialQuery();

        return new FinancialQuery($query->page, $query->perPage, ['status' => 'active', ...$query->filters], $query->sort);
    }
}
