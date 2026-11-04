<?php

namespace Tests\Support\Tenancy;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ProbeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['public_id' => $this->resource->public_id, 'label' => $this->resource->label];
    }
}
