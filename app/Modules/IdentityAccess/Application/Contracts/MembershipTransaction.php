<?php

namespace App\Modules\IdentityAccess\Application\Contracts;

interface MembershipTransaction
{
    /**
     * @template T
     *
     * @param  callable():T  $operation
     * @return T
     */
    public function run(callable $operation): mixed;

    /** @param list<string> $publicIds */
    public function lockUsers(array $publicIds): void;
}
