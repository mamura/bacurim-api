<?php

namespace Database\Factories;

use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<OrganizationRecord> */
class OrganizationFactory extends Factory
{
    protected $model = OrganizationRecord::class;

    public function definition(): array
    {
        $now = now();

        return ['public_id' => (string) Str::uuid(), 'name' => fake()->company(), 'legal_name' => null, 'document' => null, 'status' => 'active', 'version' => 1, 'created_at' => $now, 'updated_at' => $now];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => 'inactive']);
    }
}
