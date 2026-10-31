<?php

namespace Tests\Fixtures\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExampleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource['id'],
            'name' => $this->resource['name'],
        ];
    }
}
