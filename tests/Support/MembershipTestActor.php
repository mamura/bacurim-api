<?php

namespace Tests\Support;

use App\Modules\IdentityAccess\Application\Contracts\MembershipActor;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\MembershipRepository;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use App\Modules\Organizations\Infrastructure\Persistence\Eloquent\Models\OrganizationRecord;
use App\SharedKernel\Application\Tenancy\TenantContext;

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

    protected function context(OrganizationRecord $organization): TenantContext
    {
        $identity = app(MembershipActor::class)->identity();
        $user = app(UserRepository::class)->findByPublicId($identity->publicId);
        $member = app(MembershipRepository::class)->findForUser($organization->id, $user->id);
        if ($member === null) {
            throw new \LogicException('Test actor requires a membership.');
        }

        return new TenantContext($organization->id, $organization->public_id, $identity->publicId, $identity->version, $member->publicId, $member->version);
    }
}
