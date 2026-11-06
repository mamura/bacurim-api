<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migration is transactional on PostgreSQL; freeze writers during assessment.
        DB::statement('LOCK TABLE users, organization_memberships IN SHARE ROW EXCLUSIVE MODE');
        $incompatible = DB::table('organization_memberships')->select('user_id')
            ->where('status', 'active')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($incompatible) {
            throw new RuntimeException('Profile migration requires explicit legacy reconciliation. Run identity:assess-memberships; no memberships were changed.');
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->string('profile', 16)->default('standard');
        });
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_profile_valid CHECK (profile IN ('standard','consultant'))");
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('profile'));
    }
};
