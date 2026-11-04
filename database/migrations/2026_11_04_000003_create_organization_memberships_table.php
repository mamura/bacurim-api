<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_memberships', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('organization_id')->constrained('organizations')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('role', 16);
            $table->string('status', 16);
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz(6);
            $table->timestampTz('revoked_at', 6)->nullable();
            $table->unique(['organization_id', 'user_id'], 'memberships_organization_user_unique');
            $table->index(['user_id', 'status']);
            $table->index(['organization_id', 'status', 'role']);
        });
        DB::statement("ALTER TABLE organization_memberships ADD CONSTRAINT memberships_role_valid CHECK (role IN ('owner','admin','member','viewer'))");
        DB::statement("ALTER TABLE organization_memberships ADD CONSTRAINT memberships_status_valid CHECK (status IN ('active','revoked'))");
        DB::statement('ALTER TABLE organization_memberships ADD CONSTRAINT memberships_version_valid CHECK (version > 0)');
        DB::statement("ALTER TABLE organization_memberships ADD CONSTRAINT memberships_dates_valid CHECK (created_at IS NOT NULL AND updated_at IS NOT NULL AND updated_at >= created_at AND ((status = 'active' AND revoked_at IS NULL) OR (status = 'revoked' AND revoked_at IS NOT NULL AND revoked_at = updated_at)))");
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_memberships');
    }
};
