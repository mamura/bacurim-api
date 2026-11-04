<?php

namespace Tests\Support\Tenancy;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

final class ProbeSchema
{
    public static function create(): void
    {
        Schema::create('tenant_isolation_probes', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('label');
            $table->uuid('parent_public_id')->nullable();
            $table->timestampTz('deleted_at', 6)->nullable();
            $table->unique(['organization_id', 'public_id']);
            $table->foreign(['organization_id', 'parent_public_id'])->references(['organization_id', 'public_id'])->on('tenant_isolation_probes')->restrictOnDelete();
        });
    }
}
