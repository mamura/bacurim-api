<?php

namespace App\Modules\Financial\Interfaces\Http\Controllers;

use App\Modules\Financial\Application\Data\CategoryChanges;
use App\Modules\Financial\Application\UseCases\InitializeFinancialCategories;
use App\Modules\Financial\Application\UseCases\ManageFinancialCategories;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Domain\FinancialStatus;
use App\Modules\Financial\Interfaces\Http\Requests\CategoryListRequest;
use App\Modules\Financial\Interfaces\Http\Requests\CategoryWriteRequest;
use App\Modules\Financial\Interfaces\Http\Resources\FinancialCategoryResource;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

final class FinancialCategoriesController
{
    private function context(Request $request): TenantContext
    {
        $context = $request->attributes->get(TenantContext::class);

        return $context instanceof TenantContext ? $context : throw new \LogicException('Tenant context required.');
    }

    public function index(CategoryListRequest $request, ManageFinancialCategories $categories): AnonymousResourceCollection
    {
        $query = $request->financialQuery();
        $page = $categories->list($this->context($request), $query);
        $parameters = $request->validated();
        $parameters['filter'] = $query->filters;

        return FinancialCategoryResource::collection(new LengthAwarePaginator($page->items, $page->total, $query->perPage, $query->page, ['path' => $request->getPathInfo(), 'query' => $parameters]));
    }

    public function show(Request $request, string $organization, string $category, ManageFinancialCategories $categories): FinancialCategoryResource
    {
        return new FinancialCategoryResource($categories->read($this->context($request), $category));
    }

    public function store(CategoryWriteRequest $request, ManageFinancialCategories $categories): JsonResponse
    {
        $input = $request->validated();
        $created = $categories->create($this->context($request), $input['name'], EconomicNature::from($input['nature']), $input['parent_public_id'] ?? null);

        return (new FinancialCategoryResource($created))->response()->setStatusCode(201)->header('Location', route('financial.categories.show', ['organization' => $this->context($request)->organizationPublicId, 'category' => $created->identity->publicId], absolute: false));
    }

    public function update(CategoryWriteRequest $request, string $organization, string $category, ManageFinancialCategories $categories): FinancialCategoryResource
    {
        $i = $request->validated();

        return new FinancialCategoryResource($categories->update($this->context($request), $category, new CategoryChanges($i['name'] ?? null, isset($i['nature']) ? EconomicNature::from($i['nature']) : null, isset($i['status']) ? FinancialStatus::from($i['status']) : null, array_key_exists('parent_public_id', $i), $i['parent_public_id'] ?? null), (int) $i['version']));
    }

    public function initialize(Request $request, InitializeFinancialCategories $initializer): AnonymousResourceCollection
    {
        return FinancialCategoryResource::collection($initializer->execute($this->context($request)));
    }
}
