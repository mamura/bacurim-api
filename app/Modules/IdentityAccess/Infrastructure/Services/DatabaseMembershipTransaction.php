<?php

namespace App\Modules\IdentityAccess\Infrastructure\Services;

use App\Modules\IdentityAccess\Application\Contracts\MembershipTransaction;
use App\Modules\IdentityAccess\Infrastructure\Persistence\Eloquent\Models\UserRecord;
use Illuminate\Support\Facades\DB;
use LogicException;

final class DatabaseMembershipTransaction implements MembershipTransaction
{
    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public function run(callable $operation): mixed
    {
        return DB::transaction(fn () => $operation(), 3);
    }

    public function lockUsers(array $publicIds): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('User locks require a transaction.');
        }
        $publicIds = array_values(array_filter($publicIds, static fn (string $id): bool => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/Di', $id) === 1));
        UserRecord::query()->whereIn('public_id', $publicIds)->orderBy('id')->lockForUpdate()->get(['id']);
    }
}
