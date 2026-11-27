<?php

namespace App\Modules\Financial\Infrastructure\Persistence;

use App\Modules\Financial\Domain\ValueObjects\FinancialIdentity;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

final class FinancialStorage
{
    /**
     * @template T
     *
     * @param  callable():T  $operation
     * @return T
     */
    public function guard(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $failure) {
            if ($failure->getCode() === '23505') {
                throw new ApplicationFailure(FailureKind::Conflict, 'financial.identity_conflict', 'A financial identifier already exists.');
            }
            throw new FinancialPersistenceFailure;
        }
    }

    /** @param array<string,mixed> $row */
    public function identity(array $row): FinancialIdentity
    {
        return new FinancialIdentity((int) $row['id'], (string) $row['public_id'], (int) $row['organization_id'], (int) $row['version'], new DateTimeImmutable((string) $row['created_at']), new DateTimeImmutable((string) $row['updated_at']));
    }

    public function assertScope(TenantContext $context, FinancialIdentity $identity): void
    {
        if ($identity->organizationId !== $context->organizationId) {
            throw new LogicException('Financial ownership cannot be reassigned.');
        }
    }

    /** @param array<string,mixed>|null $row */
    public function assertVersion(?array $row, FinancialIdentity $identity): void
    {
        if ($row === null) {
            throw new ApplicationFailure(FailureKind::NotFound, 'financial.not_found', 'Financial resource not found.');
        }
        if ((int) $row['version'] !== $identity->version) {
            throw new ApplicationFailure(FailureKind::Conflict, 'financial.concurrent_update', 'The financial resource was changed by another operation.');
        }
        if ((int) $row['id'] !== $identity->id || (string) $row['public_id'] !== $identity->publicId || new DateTimeImmutable((string) $row['created_at']) != $identity->createdAt) {
            throw new LogicException('Financial identity cannot be reassigned.');
        }
    }

    /**
     * @param  array<string,mixed>|null  $before
     * @param  array<string,mixed>  $after
     */
    public function audit(TenantContext $context, string $type, string $id, ?array $before, array $after, DateTimeImmutable $now): void
    {
        DB::table('financial_revisions')->insert(['organization_id' => $context->organizationId, 'resource_type' => $type, 'resource_public_id' => $id, 'actor_public_id' => $context->actorPublicId, 'before' => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR), 'after' => json_encode($after, JSON_THROW_ON_ERROR), 'occurred_at' => $now->format('Y-m-d H:i:s.uP')]);
    }
}
