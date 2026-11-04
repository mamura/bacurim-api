<?php

namespace App\Modules\IdentityAccess\Application\Contracts\PublicApi;

/** Public vocabulary for the existing membership permissions; not a second role matrix. */
enum TenantCapability: string
{
    case ReadFinancial = 'financial.read';
    case WriteFinancial = 'financial.write';
    case ReadMembers = 'members.read';
    case ManageMembers = 'members.manage';
    case ManageSettings = 'settings.manage';
    case ManageOwnership = 'ownership.manage';
}
