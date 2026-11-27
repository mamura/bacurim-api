<?php

namespace App\Modules\Financial\Interfaces\Http\Controllers;

use App\Modules\Financial\Application\Data\AccountChanges;
use App\Modules\Financial\Application\UseCases\ManageFinancialAccounts;
use App\Modules\Financial\Domain\AccountType;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Interfaces\Http\Requests\AccountListRequest;
use App\Modules\Financial\Interfaces\Http\Requests\AccountWriteRequest;
use App\Modules\Financial\Interfaces\Http\Resources\FinancialAccountResource;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

final class FinancialAccountsController
{
    private function context(Request $request): TenantContext
    {
        $context = $request->attributes->get(TenantContext::class);

        return $context instanceof TenantContext ? $context : throw new \LogicException('Tenant context required.');
    }

    public function index(AccountListRequest $request, ManageFinancialAccounts $accounts): AnonymousResourceCollection
    {
        $query = $request->financialQuery();
        $page = $accounts->list($this->context($request), $query);

        return FinancialAccountResource::collection(new LengthAwarePaginator($page->items, $page->total, $query->perPage, $query->page, ['path' => $request->getPathInfo(), 'query' => $request->validated()]));
    }

    public function show(Request $request, string $organization, string $account, ManageFinancialAccounts $accounts): FinancialAccountResource
    {
        return new FinancialAccountResource($accounts->read($this->context($request), $account));
    }

    public function store(AccountWriteRequest $request, ManageFinancialAccounts $accounts): JsonResponse
    {
        $input = $request->validated();
        $created = $accounts->create($this->context($request), $input['name'], AccountType::from($input['type']));

        return (new FinancialAccountResource($created))->response()->setStatusCode(201)->header('Location', route('financial.accounts.show', ['organization' => $this->context($request)->organizationPublicId, 'account' => $created->identity->publicId], absolute: false));
    }

    public function update(AccountWriteRequest $request, string $organization, string $account, ManageFinancialAccounts $accounts): FinancialAccountResource
    {
        $input = $request->validated();

        return new FinancialAccountResource($accounts->update($this->context($request), $account, new AccountChanges($input['name'] ?? null, isset($input['type']) ? AccountType::from($input['type']) : null, isset($input['status']) ? FinancialStatus::from($input['status']) : null), (int) $input['version']));
    }
}
