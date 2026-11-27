<?php

namespace App\Modules\Financial\Interfaces\Http\Resources;

use App\Modules\Financial\Domain\Entities\FinancialAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FinancialAccountResource extends JsonResource
{
    public function __construct(private readonly FinancialAccount $account)
    {
        parent::__construct($account);
    }

    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $a = $this->account;

        return ['public_id' => $a->identity->publicId, 'name' => $a->name, 'type' => $a->type->value, 'status' => $a->status->value, 'version' => $a->identity->version, 'created_at' => $a->identity->createdAt->format('Y-m-d\TH:i:s.u\Z'), 'updated_at' => $a->identity->updatedAt->format('Y-m-d\TH:i:s.u\Z')];
    }
}
