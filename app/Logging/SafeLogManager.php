<?php

namespace App\Logging;

use Illuminate\Log\Logger as FrameworkLogger;
use Illuminate\Log\LogManager;
use Monolog\Logger;

class SafeLogManager extends LogManager
{
    /** @param array<string,mixed>|null $config */
    protected function get($name, ?array $config = null)
    {
        return $this->protect(parent::get($name, $config));
    }

    protected function createEmergencyLogger()
    {
        return $this->protect(parent::createEmergencyLogger());
    }

    private function protect(mixed $logger): mixed
    {
        if (! $logger instanceof FrameworkLogger) {
            return $logger;
        }
        $monolog = $logger->getLogger();
        if (! $monolog instanceof Logger) {
            return $logger;
        }
        $processors = $monolog->getProcessors();
        foreach ($processors as $processor) {
            if ($processor instanceof RedactSensitiveLog) {
                return $logger;
            }
        }
        foreach ($processors as $processor) {
            $monolog->popProcessor();
        }
        $monolog->pushProcessor($this->app->make(RedactSensitiveLog::class));
        // Execute after interpolation and framework context processors, before handlers format.
        foreach (array_reverse($processors) as $processor) {
            $monolog->pushProcessor($processor);
        }

        return $logger;
    }
}
