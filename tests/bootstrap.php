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
