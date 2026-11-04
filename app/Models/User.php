<?php

namespace App\Models;

use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;

/** Compatibility alias for the skeleton; domain identity lives in IdentityAccess. */
class User extends UserRecord {}
