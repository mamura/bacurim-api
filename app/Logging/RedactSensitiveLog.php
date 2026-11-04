<?php

namespace App\Logging;

use Illuminate\Contracts\Container\Container;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

final readonly class RedactSensitiveLog implements ProcessorInterface
{
    public function __construct(private Container $container) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        $secrets = $this->requestSecrets();
        $this->collect($record->context, $secrets);
        $this->collect($record->extra, $secrets);
        $secrets = array_values(array_unique($secrets));
        usort($secrets, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $record->with(message: $this->text($record->message, $secrets), context: $this->fields($record->context, $secrets), extra: $this->fields($record->extra, $secrets));
    }

    private function sensitive(string $key): bool
    {
        $key = preg_replace('/[^a-z0-9]/', '', strtolower($key)) ?? '';

        return str_contains($key, 'password') || str_contains($key, 'session') || str_ends_with($key, 'token') ||
            in_array($key, ['authorization', 'cookie', 'setcookie', 'credentials', 'secret', 'appkey', 'apikey'], true);
    }

    /** @return list<string> */
    private function requestSecrets(): array
    {
        if (! $this->container->bound('request')) {
            return [];
        }
        $request = $this->container->make('request');
        $secrets = [];
        $this->collect($request->all(), $secrets);
        foreach ($request->cookies->all() as $key => $value) {
            if ($this->sensitive((string) $key)) {
                $this->collectStrings($value, $secrets);
            }
        }
        foreach (['Authorization', 'Cookie', 'X-XSRF-TOKEN', 'X-CSRF-TOKEN'] as $header) {
            $this->collectStrings($request->header($header), $secrets);
        }
        if ($request->hasSession()) {
            $this->collectStrings($request->session()->getId(), $secrets);
        }
        $secrets = array_values(array_unique($secrets));
        usort($secrets, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return $secrets;
    }

    /**
     * @param  array<array-key,mixed>  $values
     * @param  list<string>  $secrets
     */
    private function collect(array $values, array &$secrets, int $depth = 0): void
    {
        if ($depth > 8) {
            return;
        }
        foreach ($values as $key => $value) {
            if ($this->sensitive((string) $key)) {
                $this->collectStrings($value, $secrets);
            } elseif (is_array($value)) {
                $this->collect($value, $secrets, $depth + 1);
            }
        }
    }

    /** @param list<string> $secrets */
    private function collectStrings(mixed $value, array &$secrets, int $depth = 0): void
    {
        if ($depth > 8) {
            return;
        }
        if (is_string($value) && $value !== '') {
            $secrets[] = $value;
        } elseif (is_array($value)) {
            foreach ($value as $part) {
                $this->collectStrings($part, $secrets, $depth + 1);
            }
        }
    }

    /**
     * @param  array<array-key,mixed>  $fields
     * @param  list<string>  $secrets
     * @return array<array-key,mixed>
     */
    private function fields(array $fields, array $secrets, int $depth = 0): array
    {
        $clean = [];
        foreach ($fields as $key => $value) {
            $clean[$key] = $this->sensitive((string) $key) ? '[REDACTED]' : $this->value($value, $secrets, $depth + 1);
        }

        return $clean;
    }

    /** @param list<string> $secrets */
    private function value(mixed $value, array $secrets, int $depth): mixed
    {
        if ($depth > 8) {
            return '[REDACTED:depth]';
        }
        if (is_string($value)) {
            return $this->text($value, $secrets);
        }
        if (is_array($value)) {
            return $this->fields($value, $secrets, $depth);
        }
        if ($value instanceof Throwable) {
            $trace = [];
            foreach ($value->getTrace() as $frame) {
                $trace[] = array_intersect_key($frame, array_flip(['file', 'line', 'class', 'type', 'function']));
            }

            return ['class' => $value::class, 'message' => $this->text($value->getMessage(), $secrets), 'file' => $value->getFile(), 'line' => $value->getLine(), 'trace' => $trace, 'previous' => $this->value($value->getPrevious(), $secrets, $depth + 1)];
        }
        if (is_object($value)) {
            return '[object '.$value::class.']';
        }
        if (is_resource($value)) {
            return '[resource]';
        }

        return $value;
    }

    /** @param list<string> $secrets */
    private function text(string $text, array $secrets): string
    {
        $text = str_replace($secrets, '[REDACTED]', $text);
        $text = preg_replace('~\$(?:2[aby]\$\d{2}\$[./A-Za-z0-9]{53}|argon2(?:id|i)\$v=\d+\$m=\d+,t=\d+,p=\d+\$[A-Za-z0-9+/=]+\$[A-Za-z0-9+/=]+)~', '[REDACTED:hash]', $text) ?? '[REDACTED]';
        $text = preg_replace('~\bBearer\s+\S+~i', 'Bearer [REDACTED]', $text) ?? '[REDACTED]';

        return preg_replace('~\b(password|current_password|new_password|session[_-]?id|access[_-]?token|refresh[_-]?token|authorization|cookie|app[_-]?key)\b(["\']?\s*[:=]\s*)(?:"[^"]*"|\'[^\']*\'|[^\r\n]+)~i', '$1$2[REDACTED]', $text) ?? '[REDACTED]';
    }
}
