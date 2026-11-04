<?php

namespace Database\Factories;

use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Domain\MembershipStatus;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\MembershipRecord;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<MembershipRecord> */
class MembershipFactory extends Factory
{
    protected $model = MembershipRecord::class;

    public function definition(): array
    {
        $now = now()->toImmutable();

        return ['public_id' => (string) Str::uuid(), 'organization_id' => OrganizationRecord::factory(), 'user_id' => UserRecord::factory(), 'role' => MembershipRole::Member, 'status' => MembershipStatus::Active, 'version' => 1, 'created_at' => $now, 'updated_at' => $now, 'revoked_at' => null];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => MembershipStatus::Revoked, 'revoked_at' => $attributes['updated_at']]);
    }
}
