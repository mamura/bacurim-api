<?php

require dirname(__DIR__).'/bootstrap.php';
use App\Modules\Financial\Application\UseCases\InitializeFinancialCategories;
use App\SharedKernel\Application\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$config = config('database.connections.pgsql');
if (! app()->environment('testing') || config('database.default') !== 'pgsql' || $config['database'] !== 'bacurim_test' || $config['username'] !== 'bacurim_test' || ! empty($config['url'])) {
    throw new RuntimeException('Dedicated test database required.');
}
$input = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$tag = $argv[2];
$context = new TenantContext(...$input);
DB::select("select set_config('application_name', ?, false)", [$tag]);
echo "ready\n";
flush();
$result = app(InitializeFinancialCategories::class)->execute($context);
echo json_encode(array_map(fn ($c) => $c->identity->publicId, $result), JSON_THROW_ON_ERROR)."\n";
