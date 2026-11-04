<?php

namespace App\Modules\IdentityAccess\Domain;

enum MembershipPermission: string
{
    case ReadFinancial = 'financial.read';
    case WriteFinancial = 'financial.write';
    case ReadMembers = 'members.read';
    case ManageMembers = 'members.manage';
    case ManageSettings = 'settings.manage';
    case ManageOwnership = 'ownership.manage';
}
