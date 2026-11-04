<?php

namespace App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models;

use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Domain\MembershipStatus;
use Carbon\CarbonImmutable;
use Database\Factories\MembershipFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property int $organization_id
 * @property int $user_id
 * @property MembershipRole $role
 * @property MembershipStatus $status
 * @property int $version
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 * @property CarbonImmutable|null $revoked_at
 */
final class MembershipRecord extends Model
{
    /** @use HasFactory<MembershipFactory> */
    use HasFactory;

    protected $table = 'organization_memberships';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['organization_id' => 'integer', 'user_id' => 'integer', 'version' => 'integer', 'role' => MembershipRole::class, 'status' => MembershipStatus::class, 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.uP';
    }

    protected static function newFactory(): MembershipFactory
    {
        return MembershipFactory::new();
    }
}
