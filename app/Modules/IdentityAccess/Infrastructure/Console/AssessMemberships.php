<?php

namespace App\Modules\IdentityAccess\Infrastructure\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class AssessMemberships extends Command
{
    protected $signature = 'identity:assess-memberships';

    protected $description = 'Read-only assessment of active memberships before profile rollout';

    public function handle(): int
    {
        $hasProfile = Schema::hasColumn('users', 'profile');
        $rows = DB::table('users')->select('id', 'public_id')
            ->when($hasProfile, fn ($query) => $query->addSelect('profile'))
            ->orderBy('id')->get();
        $buckets = ['zero' => 0, 'one' => 0, 'multiple' => 0];
        $incompatible = 0;
        foreach ($rows as $user) {
            $count = DB::table('organization_memberships')->where('user_id', $user->id)->where('status', 'active')->count();
            $buckets[$count === 0 ? 'zero' : ($count === 1 ? 'one' : 'multiple')]++;
            if ($count > 1 && (! $hasProfile || $user->profile === 'standard')) {
                $incompatible++;
                $this->warn($user->public_id.' active_memberships='.$count.' requires_explicit_reconciliation');
            }
        }
        $this->line(json_encode($buckets, JSON_THROW_ON_ERROR));
        $this->line('incompatible='.$incompatible);

        return $incompatible === 0 ? self::SUCCESS : self::FAILURE;
    }
}
