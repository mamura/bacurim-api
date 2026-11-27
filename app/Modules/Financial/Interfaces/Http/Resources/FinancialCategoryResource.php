<?php

namespace App\Modules\Financial\Interfaces\Http\Resources;

use App\Modules\Financial\Domain\Entities\FinancialCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class FinancialCategoryResource extends JsonResource
{
    public function __construct(private readonly FinancialCategory $category)
    {
        parent::__construct($category);
    }

    /** @return array<string,mixed> */
    public function toArray(Request $request): array
    {
        $c = $this->category;

        return ['public_id' => $c->identity->publicId, 'name' => $c->name, 'nature' => $c->nature->value, 'status' => $c->status->value, 'parent_public_id' => $c->parentPublicId, 'is_default' => $c->defaultKey !== null, 'version' => $c->identity->version, 'created_at' => $c->identity->createdAt->format('Y-m-d\TH:i:s.u\Z'), 'updated_at' => $c->identity->updatedAt->format('Y-m-d\TH:i:s.u\Z')];
    }
}
