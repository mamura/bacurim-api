<?php

namespace App\Modules\IdentityAccess\Application\UseCases;

use App\Modules\IdentityAccess\Application\MembershipAccess;

final readonly class ListUserOrganizations
{
    public function __construct(private MembershipAccess $access) {}

    /** @return list<array{public_id:string,name:string,role:string,membership_public_id:string}> */
    public function execute(): array
    {
        return $this->access->transaction->run(function (): array {
            $actor = $this->access->actor();
            $result = [];
            foreach ($this->access->memberships->forUser($actor->id ?? 0) as $member) {
                $org = $this->access->organizations->findById($member->organizationId);
                if ($org !== null && $member->isEffective(true, $org->active)) {
                    $result[] = ['public_id' => $org->publicId, 'name' => $org->name, 'role' => $member->role->value, 'membership_public_id' => $member->publicId];
                }
            }

            return $result;
        });
    }
}
