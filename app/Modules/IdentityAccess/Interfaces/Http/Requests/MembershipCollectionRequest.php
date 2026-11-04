<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class MembershipCollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [];
    }

    /** @return list<Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->query()) as $key) {
                $validator->errors()->add((string) $key, 'Query parameters are not supported for this endpoint.');
            }
        }];
    }
}
