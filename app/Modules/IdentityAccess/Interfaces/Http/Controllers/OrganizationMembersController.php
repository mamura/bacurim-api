<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Controllers;

use App\Modules\IdentityAccess\Application\UseCases\ListUserOrganizations;
use App\Modules\IdentityAccess\Application\UseCases\ManageOrganizationMembers;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\Modules\IdentityAccess\Interfaces\Http\Requests\AddMemberRequest;
use App\Modules\IdentityAccess\Interfaces\Http\Requests\MembershipCollectionRequest;
use App\Modules\IdentityAccess\Interfaces\Http\Requests\RevokeMemberRequest;
use App\Modules\IdentityAccess\Interfaces\Http\Requests\UpdateMemberRequest;
use App\Modules\IdentityAccess\Interfaces\Http\Resources\MembershipResource;
use App\Modules\IdentityAccess\Interfaces\Http\Resources\UserOrganizationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class OrganizationMembersController
{
    public function index(MembershipCollectionRequest $request, string $organization, ManageOrganizationMembers $members): AnonymousResourceCollection
    {
        return MembershipResource::collection($members->list($organization));
    }

    public function store(AddMemberRequest $request, string $organization, ManageOrganizationMembers $members): JsonResponse
    {
        $input = $request->validated();

        $created = $members->add($organization, $input['user_public_id'], MembershipRole::from($input['role']));

        return (new MembershipResource($created))->response()->setStatusCode(201)->header('Location', route('members.update', ['organization' => $created->organizationPublicId, 'membership' => $created->publicId], absolute: false));

    }

    public function update(UpdateMemberRequest $request, string $organization, string $membership, ManageOrganizationMembers $members): MembershipResource
    {
        $input = $request->validated();
        $result = isset($input['role']) ? $members->changeRole($organization, $membership, MembershipRole::from($input['role']), (int) $input['version']) : $members->activate($organization, $membership, (int) $input['version']);

        return new MembershipResource($result);
    }

    public function destroy(RevokeMemberRequest $request, string $organization, string $membership, ManageOrganizationMembers $members): Response
    {
        $members->revoke($organization, $membership, (int) $request->validated('version'));

        return response()->noContent();
    }

    public function organizations(MembershipCollectionRequest $request, ListUserOrganizations $organizations): AnonymousResourceCollection
    {
        return UserOrganizationResource::collection($organizations->execute());
    }
}
