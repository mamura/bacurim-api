<?php

namespace App\Modules\Financial\Interfaces\Http\Requests;

use App\Modules\Financial\Application\Data\FinancialQuery;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

abstract class FinancialQueryRequest extends FormRequest
{
    /** @return array<string,mixed> */
    abstract protected function filters(): array;

    /** @return list<string> */
    abstract protected function sorts(): array;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $filters = $this->filters();
        $rules = ['page' => ['sometimes', 'integer', 'min:1', 'max:1000000'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'], 'filter' => ['sometimes', 'array:'.implode(',', array_keys($filters))], 'sort' => ['sometimes', 'string', function (string $attribute, mixed $value, Closure $fail): void {
            foreach (explode(',', (string) $value) as $field) {
                if (! in_array(ltrim($field, '-'), $this->sorts(), true) || str_starts_with($field, '--')) {
                    $fail('Unsupported sorting field.');
                    break;
                }
            }
        }]];
        foreach ($filters as $name => $validation) {
            $rules['filter.'.$name] = $validation;
        }

        return $rules;
    }

    /** @return list<Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach (array_keys($this->query()) as $key) {
                if (! in_array($key, ['page', 'per_page', 'filter', 'sort'], true)) {
                    $validator->errors()->add((string) $key, 'Unsupported query parameter.');
                }
            }
        }];
    }

    public function financialQuery(): FinancialQuery
    {
        $input = $this->validated();

        return new FinancialQuery((int) ($input['page'] ?? 1), (int) ($input['per_page'] ?? 20), $input['filter'] ?? [], $input['sort'] ?? 'created_at');
    }
}
