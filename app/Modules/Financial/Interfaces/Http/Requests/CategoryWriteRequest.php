<?php

namespace App\Modules\Financial\Interfaces\Http\Requests;

use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\FinancialStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CategoryWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $patch = $this->isMethod('PATCH');

        return ['name' => [$patch ? 'sometimes' : 'required', 'required', 'string', 'max:255'], 'nature' => [$patch ? 'sometimes' : 'required', 'required', Rule::enum(EconomicNature::class)], 'status' => $patch ? ['sometimes', 'required', Rule::enum(FinancialStatus::class)] : ['prohibited'], 'parent_public_id' => ['sometimes', 'nullable', 'uuid'], 'version' => [$patch ? 'required' : 'prohibited', 'integer', 'min:1'], 'organization_id' => ['prohibited'], 'organization_public_id' => ['prohibited'], 'public_id' => ['prohibited'], 'default_key' => ['prohibited']];
    }
}
