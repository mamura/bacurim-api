<?php

namespace App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models;

use App\Modules\Organizations\Domain\OrganizationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $legal_name
 * @property string|null $document
 * @property OrganizationStatus $status
 * @property int $version
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class OrganizationRecord extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected $table = 'organizations';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => OrganizationStatus::class, 'version' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\\TH:i:s.uP');
    }

    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.uP';
    }

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }
}
