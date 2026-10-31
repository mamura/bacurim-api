<?php

namespace Tests\Architecture;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\ArchitectureInspector;

class ModuleBoundariesTest extends TestCase
{
    public function test_production_code_respects_declared_boundaries(): void
    {
        $root = dirname(__DIR__, 2).'/app';
        $sources = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $sources[$file->getPathname()] = file_get_contents($file->getPathname());
            }
        }

        $this->assertSame([], (new ArchitectureInspector)->inspect($sources));
    }

    public static function forbiddenSources(): array
    {
        return [
            'domain_to_infrastructure' => ['namespace App\\Modules\\Financial\\Domain; use App\\Modules\\Financial\\Infrastructure\\Repository; class Entity { public Repository $repository; }'],
            'application_to_controller' => ['namespace App\\Modules\\Financial\\Application; class UseCase { public \\App\\Modules\\Financial\\Interfaces\\Http\\Controller $controller; }'],
            'orm_in_domain' => ['namespace App\\Modules\\Financial\\Domain; use Illuminate\\Database\\Eloquent\\Model; class Entity extends Model {}'],
            'helper_in_application' => ['namespace App\\Modules\\Financial\\Application; function operation() { return app("db"); }'],
            'private_module_repository' => ['namespace App\\Modules\\Reporting\\Infrastructure; use App\\Modules\\Financial\\Infrastructure\\Repository as R; class Report { public R $repository; }'],
            'wrong_public_direction' => ['namespace App\\Modules\\Financial\\Application; use App\\Modules\\Reporting\\Application\\Contracts\\PublicApi\\Report; class UseCase { public Report $report; }'],
            'grouped_private_import' => ['namespace App\\Modules\\Reporting\\Application; use App\\Modules\\Financial\\Infrastructure\\{Repository}; class Report {}'],
            'kernel_to_module' => ['namespace App\\SharedKernel\\Application; use App\\Modules\\Financial\\Domain\\Entity; class Context { public Entity $entity; }'],
            'domain_to_kernel_application' => ['namespace App\\Modules\\Financial\\Domain; use App\\SharedKernel\\Application\\Context; class Entity { public Context $context; }'],
            'production_to_tests' => ['namespace App\\Http; use Tests\\TestCase; class Adapter extends TestCase {}'],
        ];
    }

    #[DataProvider('forbiddenSources')]
    public function test_known_violations_are_detected(string $source): void
    {
        $this->assertNotEmpty((new ArchitectureInspector)->inspect(['example.php' => '<?php '.$source]));
    }

    public function test_allowed_public_contract_and_provider_composition_pass(): void
    {
        $sources = [
            'consumer.php' => '<?php namespace App\\Modules\\Reporting\\Application; use App\\Modules\\Financial\\Application\\Contracts\\PublicApi\\ReadFacts; class Report { public ReadFacts $facts; }',
            'provider.php' => '<?php namespace App\\Modules\\Financial\\Infrastructure\\Providers; use App\\Modules\\Financial\\Interfaces\\Http\\Controller; class Provider { public Controller $controller; }',
        ];

        $this->assertSame([], (new ArchitectureInspector)->inspect($sources));
    }

    public function test_circular_module_dependencies_are_detected(): void
    {
        $sources = [
            'financial.php' => '<?php namespace App\\Modules\\Financial\\Application; use App\\Modules\\Reporting\\Application\\Contracts\\PublicApi\\Report; class UseCase { public Report $report; }',
            'reporting.php' => '<?php namespace App\\Modules\\Reporting\\Application; use App\\Modules\\Financial\\Application\\Contracts\\PublicApi\\ReadFacts; class Report { public ReadFacts $facts; }',
        ];

        $this->assertStringContainsString('Circular module dependencies', implode("\n", (new ArchitectureInspector)->inspect($sources)));
    }
}
