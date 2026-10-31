<?php

require dirname(__DIR__).'/vendor/autoload.php';

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
