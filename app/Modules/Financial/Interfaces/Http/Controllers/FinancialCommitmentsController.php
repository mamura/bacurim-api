<?php

namespace App\Modules\Financial\Interfaces\Http\Controllers;

use App\Modules\Financial\Application\UseCases\QueryFinancialCommitments;
use App\Modules\Financial\Interfaces\Http\Requests\FinancialCommitmentListRequest;
use App\Modules\Financial\Interfaces\Http\Resources\FinancialCommitmentResource;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

final class FinancialCommitmentsController
{
    private function context(Request $request): TenantContext
    {
        $context = $request->attributes->get(TenantContext::class);

        return $context instanceof TenantContext ? $context : throw new \LogicException('Tenant context required.');
    }

    public function index(FinancialCommitmentListRequest $request, QueryFinancialCommitments $commitments): AnonymousResourceCollection
    {
        return $this->listing($request, $commitments);
    }

    public function forEvent(FinancialCommitmentListRequest $request, string $organization, string $event, QueryFinancialCommitments $commitments): AnonymousResourceCollection
    {
        return $this->listing($request, $commitments, $event);
    }

    private function listing(FinancialCommitmentListRequest $request, QueryFinancialCommitments $commitments, ?string $event = null): AnonymousResourceCollection
    {
        $query = $request->financialQuery();
        $page = $commitments->list($this->context($request), $query, $event);

        return FinancialCommitmentResource::collection(new LengthAwarePaginator($page->items, $page->total, $query->perPage, $query->page, ['path' => $request->getPathInfo(), 'query' => $request->validated()]));
    }

    public function show(Request $request, string $organization, string $commitment, QueryFinancialCommitments $commitments): FinancialCommitmentResource
    {
        return new FinancialCommitmentResource($commitments->read($this->context($request), $commitment));
    }
}
