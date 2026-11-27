<?php

namespace App\Modules\Financial\Interfaces\Http\Requests;

use App\Modules\Financial\Domain\EconomicNature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class EconomicEventWriteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $patch = $this->isMethod('PATCH');
        $money = ['required', 'string', 'regex:/^(0|[1-9][0-9]{0,15})(?:\.[0-9]{1,2})?$/D'];

        return ['description' => [$patch ? 'sometimes' : 'required', 'required', 'string', 'max:255'], 'nature' => $patch ? ['prohibited'] : ['required', Rule::enum(EconomicNature::class)], 'amount' => [$patch ? 'sometimes' : 'required', ...$money], 'currency' => $patch ? ['prohibited'] : ['sometimes', 'required', Rule::in(['BRL'])], 'competence_date' => [$patch ? 'sometimes' : 'required', 'required', 'regex:/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', 'date_format:Y-m-d'], 'category_public_id' => [$patch ? 'sometimes' : 'required', 'required', 'uuid'], 'commitment' => [$patch ? 'sometimes' : 'required', 'required', 'array:expected_amount,due_date'], 'commitment.expected_amount' => ['sometimes', ...$money], 'commitment.due_date' => [$patch ? 'sometimes' : 'required', 'required', 'regex:/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2}$/D', 'date_format:Y-m-d'], 'version' => [$patch ? 'required' : 'prohibited', 'integer', 'min:1'], 'organization_id' => ['prohibited'], 'organization_public_id' => ['prohibited'], 'public_id' => ['prohibited'], 'created_by' => ['prohibited'], 'origin' => ['prohibited'], 'locked_at' => ['prohibited'], 'status' => ['prohibited'], 'account_public_id' => ['prohibited'], 'commitments' => ['prohibited']];
    }
}
