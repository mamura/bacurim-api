<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RevokeMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['version' => ['required', 'integer', 'min:1']];
    }
}
