<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('type', 16);
            $table->string('status', 16);
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(6);
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'public_id']);
            $table->index(['organization_id', 'status', 'id']);
        });
        DB::statement("ALTER TABLE financial_accounts ADD CONSTRAINT financial_accounts_values_valid CHECK (length(btrim(name)) BETWEEN 1 AND 255 AND name !~ '[[:cntrl:]]' AND type IN ('bank','digital','cash','other') AND status IN ('active','inactive') AND version > 0 AND updated_at >= created_at)");
        Schema::create('financial_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('resource_type', 32);
            $table->uuid('resource_public_id');
            $table->uuid('actor_public_id');
            $table->jsonb('before')->nullable();
            $table->jsonb('after');
            $table->timestampTz('occurred_at', 6);
            $table->index(['organization_id', 'resource_type', 'resource_public_id', 'id'], 'financial_revisions_resource_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_revisions');
        Schema::dropIfExists('financial_accounts');
    }
};
