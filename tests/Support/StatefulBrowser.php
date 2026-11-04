<?php

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use SensitiveParameter;

trait StatefulBrowser
{
    /** @var array<string, string> */
    protected array $browserCookies = [];

    protected function configureBrowser(): void
    {
        config(['session.driver' => 'database', 'session.connection' => null, 'session.domain' => '.mamura.test', 'sanctum.stateful' => ['bacurim.mamura.test', 'api-bacurim.mamura.test'], 'cors.allowed_origins' => ['http://bacurim.mamura.test', 'http://api-bacurim.mamura.test']]);
    }

    /** @param array<string, mixed> $data @param array<string, string> $headers */
    protected function browserRequest(string $method, string $uri, #[SensitiveParameter] array $data = [], array $headers = [], bool $csrf = true): TestResponse
    {
        app('auth')->shouldUse('web');
        app('auth')->forgetGuards();
        app()->forgetInstance('auth.driver');
        app('session')->forgetDrivers();
        app()->forgetInstance('session.store');
        $server = ['HTTP_HOST' => 'api-bacurim.mamura.test', 'HTTP_ORIGIN' => 'http://bacurim.mamura.test', 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
        if ($csrf && isset($this->browserCookies['XSRF-TOKEN'])) {
            $server['HTTP_X_XSRF_TOKEN'] = $this->browserCookies['XSRF-TOKEN'];
        }
        $response = $this->call($method, $uri, [], $this->browserCookies, [], array_replace($server, $headers), json_encode($data, JSON_THROW_ON_ERROR));
        foreach ($response->headers->getCookies() as $cookie) {
            $this->browserCookies[$cookie->getName()] = $cookie->getValue();
        }

        return $response;
    }
}
