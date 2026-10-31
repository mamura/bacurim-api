<?php

namespace Tests\Support;

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;

final class ArchitectureInspector
{
    private const MODULES = ['IdentityAccess', 'Organizations', 'Financial', 'DataImport', 'Reporting', 'Forecasting'];

    private const EDGES = [
        'IdentityAccess' => ['Organizations'],
        'Organizations' => [],
        'Financial' => [],
        'DataImport' => ['Financial'],
        'Reporting' => ['Financial'],
        'Forecasting' => ['Financial'],
    ];

    /** @param array<string, string> $sources @return list<string> */
    public function inspect(array $sources): array
    {
        $errors = [];
        $graph = [];
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $finder = new NodeFinder;

        foreach ($sources as $file => $source) {
            $nodes = $parser->parse($source) ?? [];
            $namespace = $finder->findFirstInstanceOf($nodes, Node\Stmt\Namespace_::class)?->name?->toString() ?? '';
            $origin = $this->module($namespace);
            if (str_starts_with($namespace, 'App\\Modules\\') && $origin === null) {
                $errors[] = $file.': module class is outside the declared layers';
            }
            $kernel = str_starts_with($namespace, 'App\\SharedKernel\\');
            $pure = $kernel || ($origin !== null && in_array($origin[1], ['Domain', 'Application'], true));

            if ($origin !== null && ! in_array($origin[0], self::MODULES, true)) {
                $errors[] = $file.': unknown module '.$origin[0];
            }

            $traverser = new NodeTraverser;
            $traverser->addVisitor(new NameResolver);
            $nodes = $traverser->traverse($nodes);
            $references = [];
            foreach ($finder->findInstanceOf($nodes, Node\Name::class) as $name) {
                $references[] = $name->toString();
            }
            // Unused grouped imports also express a source dependency.
            foreach ($finder->findInstanceOf($nodes, Node\Stmt\GroupUse::class) as $group) {
                foreach ($group->uses as $use) {
                    $references[] = $group->prefix->toString().'\\'.$use->name->toString();
                }
            }

            foreach (array_unique($references) as $reference) {
                if (str_starts_with($reference, 'Tests\\')) {
                    $errors[] = $file.': production code depends on tests';
                }
                if ($pure && (str_starts_with($reference, 'Illuminate\\') || str_starts_with($reference, 'Symfony\\')
                    || in_array($reference, ['PDO', 'Redis'], true))) {
                    $errors[] = $file.': framework or infrastructure dependency in an inner layer: '.$reference;
                }
                if ($kernel && str_starts_with($reference, 'App\\') && ! str_starts_with($reference, 'App\\SharedKernel\\')) {
                    $errors[] = $file.': SharedKernel depends on application infrastructure or a module';
                }
                if (str_starts_with($namespace, 'App\\SharedKernel\\Domain')
                    && str_starts_with($reference, 'App\\SharedKernel\\Application\\')) {
                    $errors[] = $file.': SharedKernel Domain depends on Application';
                }
                $target = $this->module($reference);
                if ($kernel && $target !== null) {
                    $errors[] = $file.': SharedKernel depends on a module';
                }
                if ($origin === null) {
                    continue;
                }
                if ($pure && str_starts_with($reference, 'App\\') && $target === null
                    && ! str_starts_with($reference, 'App\\SharedKernel\\')) {
                    $errors[] = $file.': inner layer depends on application infrastructure: '.$reference;
                }
                if ($origin[1] === 'Domain' && str_starts_with($reference, 'App\\SharedKernel\\Application\\')) {
                    $errors[] = $file.': Domain depends on SharedKernel Application';
                }
                if ($target === null) {
                    continue;
                }
                if ($origin[0] !== $target[0]) {
                    $graph[$origin[0]][$target[0]] = true;
                    if (! in_array($target[0], self::EDGES[$origin[0]] ?? [], true)
                        || $origin[1] === 'Domain'
                        || ! str_starts_with($reference, 'App\\Modules\\'.$target[0].'\\Application\\Contracts\\PublicApi\\')) {
                        $errors[] = $file.': forbidden cross-module dependency: '.$reference;
                    }

                    continue;
                }
                $allowed = match ($origin[1]) {
                    'Domain' => ['Domain'],
                    'Application' => ['Application', 'Domain'],
                    'Interfaces' => ['Interfaces', 'Application', 'Domain'],
                    'Infrastructure' => str_contains($namespace, '\\Infrastructure\\Providers')
                        ? ['Infrastructure', 'Application', 'Domain', 'Interfaces']
                        : ['Infrastructure', 'Application', 'Domain'],
                };
                if (! in_array($target[1], $allowed, true)) {
                    $errors[] = $file.': forbidden layer dependency: '.$reference;
                }
            }

            if ($pure) {
                foreach ($finder->findInstanceOf($nodes, Node\Expr\FuncCall::class) as $call) {
                    if ($call->name instanceof Node\Name && in_array($call->name->getLast(), ['app', 'resolve', 'env', 'config', 'auth', 'request', 'response', 'db'], true)) {
                        $errors[] = $file.': framework helper in an inner layer';
                    }
                }
            }
        }

        foreach (array_keys($graph) as $module) {
            if ($this->hasCycle($module, $graph, [])) {
                $errors[] = 'Circular module dependencies involving '.$module;
            }
        }

        return array_values(array_unique($errors));
    }

    /** @return array{string, string}|null */
    private function module(string $name): ?array
    {
        if (preg_match('/^App\\\\Modules\\\\([^\\\\]+)\\\\(Domain|Application|Infrastructure|Interfaces)(?:\\\\|$)/', $name, $matches)) {
            return [$matches[1], $matches[2]];
        }

        return null;
    }

    private function hasCycle(string $module, array $graph, array $visiting): bool
    {
        if (isset($visiting[$module])) {
            return true;
        }
        $visiting[$module] = true;
        foreach (array_keys($graph[$module] ?? []) as $target) {
            if ($this->hasCycle($target, $graph, $visiting)) {
                return true;
            }
        }

        return false;
    }
}
