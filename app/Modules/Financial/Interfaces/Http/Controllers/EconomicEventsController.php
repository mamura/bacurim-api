<?php

namespace App\Modules\Financial\Interfaces\Http\Controllers;

use App\Modules\Financial\Application\Data\CreateEconomicEventInput;
use App\Modules\Financial\Application\Data\EconomicEventChanges;
use App\Modules\Financial\Application\UseCases\ManageEconomicEvents;
use App\Modules\Financial\Domain\EconomicNature;
use App\Modules\Financial\Interfaces\Http\Requests\EconomicEventListRequest;
use App\Modules\Financial\Interfaces\Http\Requests\EconomicEventWriteRequest;
use App\Modules\Financial\Interfaces\Http\Resources\EconomicEventResource;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

final class EconomicEventsController
{
    private function context(Request $request): TenantContext
    {
        $context = $request->attributes->get(TenantContext::class);

        return $context instanceof TenantContext ? $context : throw new \LogicException('Tenant context required.');
    }

    public function index(EconomicEventListRequest $request, ManageEconomicEvents $events): AnonymousResourceCollection
    {
        $query = $request->financialQuery();
        $page = $events->list($this->context($request), $query);

        return EconomicEventResource::collection(new LengthAwarePaginator($page->items, $page->total, $query->perPage, $query->page, ['path' => $request->getPathInfo(), 'query' => $request->validated()]));
    }

    public function show(Request $request, string $organization, string $event, ManageEconomicEvents $events): EconomicEventResource
    {
        return new EconomicEventResource($events->read($this->context($request), $event));
    }

    public function store(EconomicEventWriteRequest $request, ManageEconomicEvents $events): JsonResponse
    {
        $i = $request->validated();
        $created = $events->create($this->context($request), new CreateEconomicEventInput($i['description'], EconomicNature::from($i['nature']), $i['amount'], $i['competence_date'], $i['category_public_id'], $i['commitment']['due_date'], $i['commitment']['expected_amount'] ?? null, $i['currency'] ?? 'BRL'));

        return (new EconomicEventResource($created))->response()->setStatusCode(201)->header('Location', route('financial.events.show', ['organization' => $this->context($request)->organizationPublicId, 'event' => $created->event->identity->publicId], absolute: false));
    }

    public function update(EconomicEventWriteRequest $request, string $organization, string $event, ManageEconomicEvents $events): EconomicEventResource
    {
        $i = $request->validated();
        $updated = $events->update($this->context($request), $event, new EconomicEventChanges($i['description'] ?? null, $i['amount'] ?? null, $i['competence_date'] ?? null, $i['category_public_id'] ?? null, $i['commitment']['due_date'] ?? null, $i['commitment']['expected_amount'] ?? null), (int) $i['version']);

        return new EconomicEventResource($updated);
    }
}
