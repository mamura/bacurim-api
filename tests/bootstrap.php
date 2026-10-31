<?php

require dirname(__DIR__).'/vendor/autoload.php';

// Laravel can read $_SERVER before $_ENV. Synchronize the forced PHPUnit
// settings across both sources so inherited host values cannot win.
$configuration = simplexml_load_file(dirname(__DIR__).'/phpunit.xml', options: LIBXML_NONET);
if ($configuration === false) {
    throw new RuntimeException('Unable to read the test environment configuration.');
}
foreach ($configuration->php->env as $entry) {
    if ((string) $entry['force'] === 'true') {
        $name = (string) $entry['name'];
        $value = (string) $entry['value'];
        putenv($name.'='.$value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

// PHPUnit sets these values before loading the bootstrap. Never load a
// development configuration cache into a test process.
if (($_ENV['APP_ENV'] ?? null) !== 'testing') {
    throw new RuntimeException('Tests require APP_ENV=testing.');
}

if (is_file(dirname(__DIR__).'/storage/framework/testing/config.php')) {
    throw new RuntimeException('Remove the testing configuration cache before running tests.');
}

// CI can expose the dedicated test service at a different address. Database
// and role names remain fixed by phpunit.xml and are checked before migrations.
foreach (['DB_HOST' => 'TEST_DB_HOST', 'DB_PORT' => 'TEST_DB_PORT'] as $target => $source) {
    $value = getenv($source);
    if ($value !== false && $value !== '') {
        putenv($target.'='.$value);
        $_ENV[$target] = $value;
        $_SERVER[$target] = $value;
    }
}
