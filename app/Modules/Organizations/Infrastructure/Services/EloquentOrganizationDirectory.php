<?php

namespace App\Modules\Organizations\Infrastructure\Services;

use App\Modules\Organizations\Application\Contracts\PublicApi\OrganizationDirectory;
use App\Modules\Organizations\Application\Contracts\PublicApi\OrganizationSnapshot;
use App\Modules\Organizations\Application\Data\OrganizationDetails;
use App\Modules\Organizations\Application\UseCases\CreateOrganization;
use App\Modules\Organizations\Domain\OrganizationStatus;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class EloquentOrganizationDirectory implements OrganizationDirectory
{
    public function __construct(private CreateOrganization $create) {}

    public function findByPublicId(string $publicId, bool $lock = false): ?OrganizationSnapshot
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $publicId) !== 1) {
            return null;
        }
        $query = OrganizationRecord::query()->where('public_id', strtolower($publicId));
        if ($lock) {
            if (DB::transactionLevel() < 1) {
                throw new LogicException('Organization locks require a transaction.');
            }
            $query->lockForUpdate();
        }
        $record = $query->first();

        return $record === null ? null : $this->snapshot($record);
    }

    public function findById(int $id): ?OrganizationSnapshot
    {
        $record = OrganizationRecord::query()->find($id);

        return $record === null ? null : $this->snapshot($record);
    }

    public function createForRegistration(string $name, ?string $legalName, ?string $document): OrganizationSnapshot
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Registration requires a transaction.');
        }
        $created = $this->create->execute(new OrganizationDetails($name, $legalName, $document));

        return $this->findByPublicId($created->publicId) ?? throw new LogicException('Created organization missing.');
    }

    private function snapshot(OrganizationRecord $record): OrganizationSnapshot
    {
        return new OrganizationSnapshot($record->id, $record->public_id, $record->name, $record->status === OrganizationStatus::Active);
    }
}
