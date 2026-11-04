<?php

namespace Tests\Support\Tenancy;

use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Exceptions\FailureKind;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Executable example only: never registered as a production repository. */
final class ScopedProbeRepository
{
    public function create(TenantContext $context, string $label, ?string $parentPublicId = null): string
    {
        if ($parentPublicId !== null && $this->find($context, $parentPublicId) === null) {
            $this->notFound();
        }
        $id = (string) Str::uuid();
        DB::table('tenant_isolation_probes')->insert(['public_id' => $id, 'organization_id' => $context->organizationId, 'label' => $label, 'parent_public_id' => $parentPublicId]);

        return $id;
    }

    /** @return object{public_id:string,label:string}|null */
    public function find(TenantContext $context, string $publicId): ?object
    {
        if (! Str::isUuid($publicId)) {
            return null;
        }

        return $this->query($context)->where('public_id', $publicId)->first(['public_id', 'label']);
    }

    public function rename(TenantContext $context, string $publicId, string $label): void
    {
        if (! Str::isUuid($publicId)) {
            $this->notFound();
        }
        if ($this->query($context)->where('public_id', $publicId)->update(['label' => $label]) !== 1) {
            $this->notFound();
        }
    }

    public function archive(TenantContext $context, string $publicId): void
    {
        if (! Str::isUuid($publicId)) {
            $this->notFound();
        }
        if ($this->query($context)->where('public_id', $publicId)->update(['deleted_at' => now()]) !== 1) {
            $this->notFound();
        }
    }

    /** @return list<string> */
    public function labels(TenantContext $context): array
    {
        return $this->query($context)->orderBy('public_id')->pluck('label')->all();
    }

    private function query(TenantContext $context): Builder
    {
        return DB::table('tenant_isolation_probes')->where('organization_id', $context->organizationId)->whereNull('deleted_at');
    }

    private function notFound(): never
    {
        throw new ApplicationFailure(FailureKind::NotFound, 'probe.not_found', 'Resource not found.');
    }
}
