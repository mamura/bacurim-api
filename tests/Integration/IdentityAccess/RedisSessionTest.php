<?php

namespace Tests\Integration\IdentityAccess;

use Illuminate\Session\CacheBasedSessionHandler;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class RedisSessionTest extends TestCase
{
    public function test_isolated_redis_persists_encrypted_sessions_and_expires_them(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame(15, (int) config('database.redis.auth_testing.database'));
        config(['session.driver' => 'redis', 'session.store' => 'auth_sessions', 'session.connection' => 'auth_testing', 'session.encrypt' => true, 'session.lifetime' => 120]);
        Session::forgetDrivers();
        $session = Session::driver();
        $session->start();
        $id = $session->getId();
        try {
            $session->put('probe', 'test-only-sensitive-session-value');
            $session->save();
            $handler = $session->getHandler();
            $this->assertInstanceOf(CacheBasedSessionHandler::class, $handler);
            $this->assertStringNotContainsString('test-only-sensitive-session-value', $handler->read($id));
            Session::forgetDrivers();
            $next = Session::driver();
            $next->setId($id);
            $next->start();
            $this->assertSame('test-only-sensitive-session-value', $next->get('probe'));
            $store = $handler->getCache()->getStore();
            $key = $store->getPrefix().$id;
            $redis = Redis::connection('auth_testing');
            $ttl = $redis->ttl($key);
            $this->assertGreaterThan(7100, $ttl);
            $this->assertLessThanOrEqual(7200, $ttl);
            $redis->expire($key, 0);
            Session::forgetDrivers();
            $expired = Session::driver();
            $expired->setId($id);
            $expired->start();
            $this->assertNull($expired->get('probe'));
        } finally {
            $session->getHandler()->destroy($id);
        }
    }
}
