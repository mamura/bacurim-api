<?php

namespace App\Modules\IdentityAccess\Infrastructure\Services;

use App\Modules\IdentityAccess\Application\Contracts\MembershipUsers;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Domain\Entities\User;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;

final readonly class EloquentMembershipUsers implements MembershipUsers
{
    public function __construct(private UserRepository $users) {}

    public function findById(int $id): ?User
    {
        $record = UserRecord::query()->find($id);

        return $record === null ? null : $this->users->findByPublicId($record->public_id);
    }
}
