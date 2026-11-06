<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Requests;

use App\Modules\IdentityAccess\Domain\MembershipRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return ['profile' => ['prohibited'], 'version' => ['required', 'integer', 'min:1'],
            'role' => ['required_without:status', Rule::prohibitedIf($this->exists('status')), Rule::enum(MembershipRole::class)],
            'status' => ['required_without:role', Rule::prohibitedIf($this->exists('role')), Rule::in(['active'])]];
    }
}
