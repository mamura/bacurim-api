<?php

require dirname(__DIR__).'/bootstrap.php';
use App\Modules\IdentityAccess\Application\Contracts\MembershipActor;
use App\Modules\IdentityAccess\Application\Contracts\Persistence\UserRepository;
use App\Modules\IdentityAccess\Application\Data\UserData;
use App\Modules\IdentityAccess\Application\UseCases\ManageOrganizationMembers;
use App\Modules\IdentityAccess\Application\UseCases\ResolveTenantContext;
use App\Modules\IdentityAccess\Domain\MembershipRole;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.pgsql');
if (! app()->environment('testing') || config('database.default') !== 'pgsql' || $config['database'] !== 'bacurim_test' || $config['username'] !== 'bacurim_test' || ! empty($config['url'])) {
    throw new RuntimeException('Concurrency workers require the dedicated test database.');
}
[$script,$actor,$org,$member,$operation,$tag] = $argv;
$user = app(UserRepository::class)->findByPublicId($actor);
if ($user === null) {
    throw new RuntimeException('Test actor missing.');
}
$identity = UserData::fromEntity($user);
app()->instance(MembershipActor::class, new readonly class($identity) implements MembershipActor
{
    public function __construct(private UserData $user) {}

    public function identity(): UserData
    {
        return $this->user;
    }
});
DB::select("select set_config('application_name', ?, false)", [$tag]);
$context = app(ResolveTenantContext::class)->execute($org, $identity);
echo "ready\n";
flush();
try {
    $service = app(ManageOrganizationMembers::class);
    if ($operation === 'demote') {
        $service->changeRole($context, $member, MembershipRole::Viewer, 1);
    } elseif ($operation === 'revoke') {
        $service->revoke($context, $member, 1);
    } elseif ($operation === 'add') {
        $service->add($context, $member, MembershipRole::Member);
    } else {
        throw new RuntimeException('Unknown test operation.');
    }
    echo json_encode(['result' => 'success'], JSON_THROW_ON_ERROR)."\n";
} catch (ApplicationFailure $failure) {
    echo json_encode(['result' => $failure->errorCode], JSON_THROW_ON_ERROR)."\n";
}
