<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('document', 14)->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(6);
        });
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_name_valid CHECK (name = btrim(name) AND char_length(name) > 0 AND name !~ '[[:cntrl:]]')");
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_legal_name_valid CHECK (legal_name IS NULL OR (legal_name = btrim(legal_name) AND char_length(legal_name) > 0 AND legal_name !~ '[[:cntrl:]]'))");
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_document_valid CHECK (document IS NULL OR document ~ '^([0-9]{11}|[A-Z0-9]{12}[0-9]{2})$')");
        DB::statement("ALTER TABLE organizations ADD CONSTRAINT organizations_status_valid CHECK (status IN ('active', 'inactive'))");
        DB::statement('ALTER TABLE organizations ADD CONSTRAINT organizations_dates_valid CHECK (created_at IS NOT NULL AND updated_at IS NOT NULL AND updated_at >= created_at)');
        DB::statement('ALTER TABLE organizations ADD CONSTRAINT organizations_version_valid CHECK (version > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};
