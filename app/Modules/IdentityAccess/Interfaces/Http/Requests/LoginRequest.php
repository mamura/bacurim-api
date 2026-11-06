<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['profile' => ['prohibited'], 'email' => ['required', 'string', 'max:254'], 'password' => ['required', 'string', 'max:128']];
    }
}
