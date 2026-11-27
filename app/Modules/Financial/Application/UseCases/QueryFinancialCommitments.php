<?php

namespace App\Modules\Financial\Application\UseCases;

use App\Modules\Financial\Application\Contracts\Persistence\EconomicRecordRepository;
use App\Modules\Financial\Application\Contracts\Persistence\FinancialCommitmentRepository;
use App\Modules\Financial\Application\Data\FinancialPage;
use App\Modules\Financial\Application\Data\FinancialQuery;
use App\Modules\Financial\Application\FinancialOperation;
use App\Modules\Financial\Domain\Entities\FinancialCommitment;
use App\SharedKernel\Application\Tenancy\TenantContext;

final readonly class QueryFinancialCommitments
{
    public function __construct(private FinancialCommitmentRepository $commitments, private EconomicRecordRepository $events, private FinancialOperation $operation) {}

    public function read(TenantContext $context, string $id): FinancialCommitment
    {
        return $this->operation->run($context, false, fn () => $this->commitments->find($context, $id) ?? $this->operation->missing());
    }

    /** @return FinancialPage<FinancialCommitment> */
    public function list(TenantContext $context, FinancialQuery $query, ?string $eventPublicId = null): FinancialPage
    {
        return $this->operation->run($context, false, function () use ($context, $query, $eventPublicId) {
            if ($eventPublicId !== null) {
                $event = $this->events->find($context, $eventPublicId) ?? $this->operation->missing();
                $query = new FinancialQuery($query->page, $query->perPage, [...$query->filters, 'event_public_id' => $event->event->identity->publicId], $query->sort);
            }

            return $this->commitments->page($context, $query);
        });
    }
}
