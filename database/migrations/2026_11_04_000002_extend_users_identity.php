<?php

use App\Modules\IdentityAccess\Domain\ValueObjects\EmailAddress;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $seen = [];
        foreach (DB::table('users')->orderBy('id')->cursor() as $user) {
            $email = (new EmailAddress($user->email))->value;
            if (isset($seen[$email])) {
                throw new RuntimeException('Existing users have colliding normalized emails; reconcile before migrating.');
            }
            $seen[$email] = true;
            if ($user->created_at === null || $user->updated_at === null || $user->updated_at < $user->created_at
                || ($user->email_verified_at !== null && ($user->email_verified_at < $user->created_at || $user->email_verified_at > $user->updated_at))) {
                throw new RuntimeException('Existing user timestamps require reconciliation before migrating.');
            }
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
            $table->string('status', 16)->default('active');
            $table->unsignedInteger('version')->default(1);
        });
        foreach (DB::table('users')->orderBy('id')->cursor() as $user) {
            DB::table('users')->where('id', $user->id)->update(['public_id' => (string) Str::uuid(), 'email' => (new EmailAddress($user->email))->value]);
        }
        DB::statement('ALTER TABLE users ALTER COLUMN public_id SET NOT NULL');
        foreach (['created_at', 'updated_at', 'email_verified_at'] as $column) {
            DB::statement("ALTER TABLE users ALTER COLUMN {$column} TYPE timestamp(6) with time zone USING {$column} AT TIME ZONE 'UTC'");
        }
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_name_valid CHECK (name = btrim(name) AND char_length(name) BETWEEN 1 AND 255 AND name !~ '[[:cntrl:]]')");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_email_canonical CHECK (email = lower(btrim(email)) AND char_length(email) BETWEEN 3 AND 254 AND email ~ '^[!-~]+@[!-~]+$')");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_valid CHECK (status IN ('active', 'inactive'))");
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_version_valid CHECK (version > 0)');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_dates_valid CHECK (created_at IS NOT NULL AND updated_at IS NOT NULL AND updated_at >= created_at AND (email_verified_at IS NULL OR email_verified_at BETWEEN created_at AND updated_at))');
        DB::statement('ALTER TABLE users ADD CONSTRAINT users_password_hash CHECK (password LIKE \'$2a$%\' OR password LIKE \'$2b$%\' OR password LIKE \'$2y$%\' OR password LIKE \'$argon2id$%\')');
    }

    public function down(): void
    {
        foreach (['name_valid', 'email_canonical', 'status_valid', 'version_valid', 'dates_valid', 'password_hash'] as $constraint) {
            DB::statement("ALTER TABLE users DROP CONSTRAINT users_{$constraint}");
        }
        foreach (['created_at', 'updated_at', 'email_verified_at'] as $column) {
            DB::statement("ALTER TABLE users ALTER COLUMN {$column} TYPE timestamp(0) without time zone USING {$column} AT TIME ZONE 'UTC'");
        }
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['public_id']);
            $table->dropColumn(['public_id', 'status', 'version']);
        });
    }
};
