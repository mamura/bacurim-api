<?php

namespace Tests\Support;

use App\Modules\IdentityAccess\Application\Contracts\MembershipActor;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;

trait MembershipTestActor
{
    protected function useMembershipActor(UserRecord $record): void
    {
        $user = app(UserRepository::class)->findByPublicId($record->public_id);
        $identity = UserData::fromEntity($user);
        app()->instance(MembershipActor::class, new readonly class($identity) implements MembershipActor
        {
            public function __construct(private UserData $user) {}

            public function identity(): UserData
            {
                return $this->user;
            }
        });
    }
}
