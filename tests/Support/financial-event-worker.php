<?php

require dirname(__DIR__).'/bootstrap.php';
use App\Modules\Financial\Application\Data\EconomicEventChanges;
use App\Modules\Financial\Application\UseCases\ManageEconomicEvents;
use App\SharedKernel\Application\Exceptions\ApplicationFailure;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.pgsql');
if (! app()->environment('testing') || config('database.default') !== 'pgsql' || $config['database'] !== 'bacurim_test' || $config['username'] !== 'bacurim_test' || ! empty($config['url'])) {
    throw new RuntimeException('Dedicated test database required.');
}
$context = new TenantContext(...json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR));
[$script,$payload,$eventId,$amount,$tag] = $argv;
DB::select("select set_config('application_name', ?, false)", [$tag]);
echo "ready\n";
flush();
try {
    $event = app(ManageEconomicEvents::class)->update($context, $eventId, new EconomicEventChanges(amount: $amount), 1);
    $result = 'success';
} catch (ApplicationFailure $failure) {
    $result = $failure->errorCode;
}
echo json_encode(['result' => $result], JSON_THROW_ON_ERROR)."\n";
