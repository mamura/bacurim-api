<?php

namespace Tests\Feature;

use DateTimeImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Route;
use Tests\Fixtures\Http\ExampleResource;
use Tests\Fixtures\Http\SerializationResource;
use Tests\TestCase;

class ApiSuccessContractTest extends TestCase
{
    public function test_resource_and_creation_use_a_data_envelope(): void
    {
        Route::post('/api/v1/_contract/resources', fn () => (new ExampleResource(['id' => '1', 'name' => 'Example']))
            ->response()->setStatusCode(201)->header('Location', '/api/v1/_contract/resources/1'));

        $this->postJson('/api/v1/_contract/resources')
            ->assertCreated()
            ->assertHeader('Location', '/api/v1/_contract/resources/1')
            ->assertExactJson(['data' => ['id' => '1', 'name' => 'Example']]);
    }

    public function test_collection_and_empty_collection_preserve_arrays(): void
    {
        Route::get('/api/v1/_contract/resources', fn () => ExampleResource::collection([['id' => '1', 'name' => 'Example']]));
        Route::get('/api/v1/_contract/empty', fn () => ExampleResource::collection([]));

        $this->getJson('/api/v1/_contract/resources')->assertExactJson(['data' => [['id' => '1', 'name' => 'Example']]]);
        $this->getJson('/api/v1/_contract/empty')->assertExactJson(['data' => []]);
    }

    public function test_paginated_collection_has_native_links_and_metadata(): void
    {
        Route::get('/api/v1/_contract/pages', fn () => ExampleResource::collection(
            new LengthAwarePaginator([['id' => '1', 'name' => 'Example']], 3, 2, 1, [
                'path' => '/api/v1/_contract/pages',
            ]),
        ));

        $this->getJson('/api/v1/_contract/pages')
            ->assertOk()
            ->assertJsonPath('data.0.id', '1')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonStructure(['links' => ['first', 'last', 'prev', 'next'], 'meta' => ['current_page', 'from', 'to', 'path', 'links']]);
    }

    public function test_deletion_has_no_response_body(): void
    {
        Route::delete('/api/v1/_contract/resources/1', fn () => response()->noContent());

        $this->deleteJson('/api/v1/_contract/resources/1')->assertNoContent();
    }

    public function test_serialization_preserves_precision_and_converts_instants_to_utc(): void
    {
        Route::get('/api/v1/_contract/serialization', fn () => new SerializationResource([
            'id' => '9007199254740993',
            'occurred_at' => new DateTimeImmutable('2026-10-31T09:00:00-03:00'),
            'date' => '2026-10-31',
            'amount' => '9007199254740993.01',
            'currency' => 'BRL',
        ]));

        $this->getJson('/api/v1/_contract/serialization')->assertOk()->assertExactJson([
            'data' => [
                'id' => '9007199254740993',
                'occurred_at' => '2026-10-31T12:00:00Z',
                'date' => '2026-10-31',
                'amount' => '9007199254740993.01',
                'currency' => 'BRL',
            ],
        ]);
    }
}
