<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class CiGateProbeTest extends TestCase
{
    public function test_quality_gate_rejects_a_failing_test(): void
    {
        $this->fail('Intentional CI validation probe for issue #37; remove before merge.');
    }
}
