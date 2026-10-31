<?php

namespace Tests\Fixtures\Http;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SerializationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->resource['id'],
            'occurred_at' => CarbonImmutable::instance($this->resource['occurred_at'])->utc()->toIso8601ZuluString(),
            'date' => $this->resource['date'],
            'amount' => $this->resource['amount'],
            'currency' => $this->resource['currency'],
        ];
    }
}
