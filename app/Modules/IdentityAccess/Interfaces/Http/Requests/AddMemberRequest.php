<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Requests;

use App\Modules\IdentityAccess\Domain\MembershipRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['user_public_id' => ['required', 'string', 'uuid'], 'role' => ['required', Rule::enum(MembershipRole::class)]];
    }
}
