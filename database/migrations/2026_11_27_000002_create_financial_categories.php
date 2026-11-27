<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->string('name');
            $table->string('nature', 16);
            $table->string('status', 16);
            $table->uuid('parent_public_id')->nullable();
            $table->string('default_key', 40)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(6);
            $table->unique(['organization_id', 'public_id'], 'financial_categories_org_uuid_unique');
            $table->unique(['organization_id', 'public_id', 'nature'], 'financial_categories_org_uuid_nature_unique');
            $table->unique(['organization_id', 'default_key'], 'financial_categories_default_unique');
            $table->index(['organization_id', 'status', 'nature', 'id'], 'financial_categories_listing_index');
            $table->foreign(['organization_id', 'parent_public_id', 'nature'], 'financial_categories_parent_fk')->references(['organization_id', 'public_id', 'nature'])->on('financial_categories')->restrictOnDelete()->restrictOnUpdate();
        });
        DB::statement("ALTER TABLE financial_categories ADD CONSTRAINT financial_categories_values_valid CHECK(length(btrim(name)) BETWEEN 1 AND 255 AND name !~ '[[:cntrl:]]' AND nature IN ('income','cost','expense') AND status IN ('active','inactive') AND version > 0 AND updated_at >= created_at AND (parent_public_id IS NULL OR parent_public_id <> public_id))");
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_categories');
    }
};
