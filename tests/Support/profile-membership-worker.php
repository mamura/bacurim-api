<?php

require dirname(__DIR__).'/bootstrap.php';

use App\Modules\IdentityAccess\Application\Contracts\MembershipActor;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\UseCases\ManageOrganizationMembers;
use App\Modules\IdentityAccess\Application\UseCases\RegisterOrganizationWithOwner;
use App\Modules\IdentityAccess\Application\UseCases\ResolveTenantContext;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.pgsql');
if (! app()->environment('testing') || config('database.default') !== 'pgsql' || $config['database'] !== 'bacurim_test' || $config['username'] !== 'bacurim_test' || ! empty($config['url'])) {
    throw new RuntimeException('Dedicated test database required.');
}
[$script, $actor, $org, $subject, $operation, $tag, $organizationName] = $argv;
$identity = UserData::fromEntity(app(UserRepository::class)->findByPublicId($actor));
app()->instance(MembershipActor::class, new readonly class($identity) implements MembershipActor
{
    public function __construct(private UserData $user) {}

    public function identity(): UserData
    {
        return $this->user;
    }
});
DB::select("select set_config('application_name', ?, false)", [$tag]);
$context = $operation === 'register' ? null : app(ResolveTenantContext::class)->execute($org, $identity);
echo "ready\n";
flush();
try {
    if ($operation === 'register') {
        app(RegisterOrganizationWithOwner::class)->execute($organizationName);
    } elseif ($operation === 'add') {
        app(ManageOrganizationMembers::class)->add($context, $subject, MembershipRole::Member);
    } elseif ($operation === 'activate') {
        app(ManageOrganizationMembers::class)->activate($context, $subject, 1);
    } else {
        throw new RuntimeException('Unknown operation.');
    }
    $result = 'success';
} catch (ApplicationFailure $failure) {
    $result = $failure->errorCode;
}
echo json_encode(['result' => $result], JSON_THROW_ON_ERROR)."\n";
