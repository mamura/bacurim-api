<?php

namespace App\Modules\IdentityAccess\Interfaces\Http\Controllers;

use App\Modules\IdentityAccess\Application\UseCases\LoginUser;
use App\Modules\IdentityAccess\Application\UseCases\LogoutUser;
use App\Modules\IdentityAccess\Interfaces\Http\Requests\LoginRequest;
use App\Modules\IdentityAccess\Interfaces\Http\Resources\AuthenticatedUserResource;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticationController
{
    public function login(LoginRequest $request, LoginUser $login): AuthenticatedUserResource
    {
        $input = $request->validated();

        return new AuthenticatedUserResource($login->execute($input['email'], $input['password']));
    }

    public function logout(LogoutUser $logout): Response
    {
        $logout->execute();

        return response()->noContent();
    }
}
