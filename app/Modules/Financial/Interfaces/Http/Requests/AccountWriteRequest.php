<?php

namespace App\Modules\Financial\Interfaces\Http\Requests;

use App\Modules\Financial\Domain\AccountType;
use App\Modules\Financial\Domain\FinancialStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AccountWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $patch = $this->isMethod('PATCH');

        return ['name' => [$patch ? 'sometimes' : 'required', 'required', 'string', 'max:255'], 'type' => [$patch ? 'sometimes' : 'required', 'required', Rule::enum(AccountType::class)], 'status' => $patch ? ['sometimes', 'required', Rule::enum(FinancialStatus::class)] : ['prohibited'], 'version' => [$patch ? 'required' : 'prohibited', 'integer', 'min:1'], 'organization_id' => ['prohibited'], 'organization_public_id' => ['prohibited'], 'public_id' => ['prohibited'], 'balance' => ['prohibited'], 'currency' => ['prohibited']];
    }
}
