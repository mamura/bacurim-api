<?php

namespace App\Modules\IdentityAccess\Domain;

enum MembershipRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Member = 'member';
    case Viewer = 'viewer';
}
