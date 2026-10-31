<?php

namespace Tests\Feature;

use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Fixtures\Http\ExampleResource;
use Tests\TestCase;

class ApiExtendedContractTest extends TestCase
{
    public static function failureKinds(): array
    {
        return [
            'validation' => [FailureKind::Validation, 422],
            'conflict' => [FailureKind::Conflict, 409],
            'missing' => [FailureKind::NotFound, 404],
            'forbidden' => [FailureKind::Forbidden, 403],
        ];
    }

    #[DataProvider('failureKinds')]
    public function test_application_failure_mapping(FailureKind $kind, int $status): void
    {
        Route::get('/api/v1/_contract/known', fn () => throw new ApplicationFailure($kind, 'example.known_failure', 'A documented public failure.'));

        $this->get('/api/v1/_contract/known')->assertStatus($status)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', $status)->assertJsonPath('code', 'example.known_failure');
    }

    public static function nativeFailures(): array
    {
        return [
            'unauthenticated' => ['authentication', 401],
            'unauthorized' => ['authorization', 403],
            'model_missing' => ['model', 404],
            'bad_request' => ['400', 400],
            'unsupported_media' => ['415', 415],
            'custom_response' => ['response', 409],
            'internal_http_error' => ['503', 503],
        ];
    }

    #[DataProvider('nativeFailures')]
    public function test_native_failures_are_safe_problem_details(string $failure, int $status): void
    {
        Route::get('/api/v1/_contract/native', function () use ($failure) {
            throw match ($failure) {
                'authentication' => new AuthenticationException('internal secret'),
                'authorization' => new AuthorizationException('internal secret'),
                'model' => (new ModelNotFoundException)->setModel('InternalSecretModel', ['secret-id']),
                'response' => new HttpResponseException(response()->json(['secret' => 'private'], 409)),
                default => new HttpException((int) $failure, 'internal secret'),
            };
        });

        $this->get('/api/v1/_contract/native', ['Accept' => 'text/html'])->assertStatus($status)
            ->assertHeader('Content-Type', 'application/problem+json')
            ->assertJsonPath('status', $status)->assertDontSee('secret')->assertDontSee('private');
    }

    public function test_malformed_json_is_rejected_before_the_handler(): void
    {
        Route::middleware('api')->post('/api/v1/_contract/json', fn () => response()->noContent());

        $this->call('POST', '/api/v1/_contract/json', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{"name":')
            ->assertBadRequest()->assertJsonPath('status', 400)
            ->assertHeader('Content-Type', 'application/problem+json');
        $this->postJson('/api/v1/_contract/json', ['name' => 'Valid'])->assertNoContent();
    }

    public function test_get_put_and_patch_use_the_resource_envelope(): void
    {
        Route::match(['GET', 'PUT', 'PATCH'], '/api/v1/_contract/resources/1', fn () => new ExampleResource(['id' => '1', 'name' => 'Example']));

        foreach (['GET', 'PUT', 'PATCH'] as $method) {
            $this->json($method, '/api/v1/_contract/resources/1')->assertOk()
                ->assertExactJson(['data' => ['id' => '1', 'name' => 'Example']]);
        }
    }

    public function test_production_errors_do_not_expose_exception_data(): void
    {
        config(['app.debug' => false, 'app.env' => 'production']);
        Route::get('/api/v1/_contract/production', fn () => throw new RuntimeException('sensitive database details'));

        $this->getJson('/api/v1/_contract/production')->assertStatus(500)
            ->assertJsonMissingPath('trace')->assertJsonMissingPath('exception')
            ->assertJsonMissingPath('file')->assertDontSee('sensitive database details');
    }

    public function test_authentication_challenge_header_is_preserved(): void
    {
        Route::get('/api/v1/_contract/challenge', fn () => throw new HttpException(401, 'private', headers: ['WWW-Authenticate' => 'Bearer']));

        $this->getJson('/api/v1/_contract/challenge')->assertUnauthorized()->assertHeader('WWW-Authenticate', 'Bearer');
    }
}
