<?php

namespace Tests\Integration\IdentityAccess;

use App\Modules\IdentityAccess\Application\Contracts\PasswordHasher;
use App\Modules\IdentityAccess\Application\Security\PasswordHash;
use Tests\TestCase;

class PasswordHasherTest extends TestCase
{
    public function test_argon_hashes_are_salted_verifiable_and_meet_cost_floor(): void
    {
        $hasher = $this->app->make(PasswordHasher::class);
        $plain = 'a long testing passphrase';
        $hash = $hasher->hash($plain);
        $other = $hasher->hash($plain);
        $this->assertNotSame($plain, $hash->encoded());
        $this->assertNotSame($hash->encoded(), $other->encoded());
        $this->assertTrue($hasher->verify($plain, $hash));
        $this->assertFalse($hasher->verify('a different passphrase', $hash));
        $this->assertFalse($hasher->needsRehash($hash));
        $info = password_get_info($hash->encoded());
        $this->assertSame('argon2id', $info['algoName']);
        $this->assertGreaterThanOrEqual(19456, $info['options']['memory_cost']);
        $this->assertGreaterThanOrEqual(2, $info['options']['time_cost']);
        $this->assertSame(1, $info['options']['threads']);
        $this->assertStringNotContainsString($hash->encoded(), json_encode($hash));
        ob_start();
        var_dump($hash);
        $debug = ob_get_clean();
        $this->assertStringNotContainsString($hash->encoded(), $debug);
        $this->expectException(\LogicException::class);
        serialize($hash);
    }

    public function test_legacy_bcrypt_can_be_verified_but_requires_rehash(): void
    {
        $hasher = $this->app->make(PasswordHasher::class);
        $legacy = new PasswordHash(password_hash('legacy-pass', PASSWORD_BCRYPT, ['cost' => 4]));
        $this->assertTrue($hasher->verify('legacy-pass', $legacy));
        $this->assertTrue($hasher->needsRehash($legacy));
        $long = str_repeat('a', 72);
        $hash = new PasswordHash(password_hash($long, PASSWORD_BCRYPT, ['cost' => 4]));
        $this->assertFalse($hasher->verify($long.'suffix', $hash));
    }

    public function test_unicode_passwords_are_not_truncated_at_bcrypt_byte_limit(): void
    {
        $hasher = $this->app->make(PasswordHasher::class);
        $plain = str_repeat('á', 64);
        $hash = $hasher->hash($plain);
        $this->assertTrue($hasher->verify($plain, $hash));
        $this->assertFalse($hasher->verify(str_repeat('á', 63).'b', $hash));
    }

    public function test_null_bytes_cannot_exploit_legacy_bcrypt_prefix_equivalence(): void
    {
        $hasher = $this->app->make(PasswordHasher::class);
        $legacy = new PasswordHash(password_hash('legacy-pass', PASSWORD_BCRYPT, ['cost' => 4]));
        $this->assertFalse($hasher->verify("legacy-pass\0suffix", $legacy));
        $this->assertTrue($hasher->verify('legacy-pass', $legacy));
    }
}
