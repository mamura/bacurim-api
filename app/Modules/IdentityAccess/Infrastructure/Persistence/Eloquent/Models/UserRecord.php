<?php

namespace App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models;

use App\Modules\IdentityAccess\Application\Security\PasswordHash;
use App\Modules\IdentityAccess\Domain\UserProfile;
use App\Modules\IdentityAccess\Domain\UserStatus;
use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property UserProfile $profile
 * @property UserStatus $status
 * @property int $version
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
class UserRecord extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    protected $table = 'users';

    public $timestamps = false;

    protected $fillable = ['name', 'email'];

    protected $hidden = ['id', 'password', 'remember_token', 'version'];

    protected function casts(): array
    {
        return ['profile' => UserProfile::class, 'status' => UserStatus::class, 'version' => 'integer', 'email_verified_at' => 'immutable_datetime', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    /** @return Attribute<string, string> */
    protected function email(): Attribute
    {
        return Attribute::make(set: fn (string $value): string => (new EmailAddress($value))->value);
    }

    /** @return Attribute<string, string> */
    protected function password(): Attribute
    {
        return Attribute::make(set: fn (#[\SensitiveParameter] string $value): string => (new PasswordHash($value))->encoded());
    }

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.uP';
    }
}
