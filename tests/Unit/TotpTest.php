<?php

namespace Tests\Unit;

use App\Support\Totp;
use PHPUnit\Framework\TestCase;

class TotpTest extends TestCase
{
    public function test_it_matches_the_rfc_6238_sha1_test_vector(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        $this->assertSame('287082', Totp::getCode($secret, 59));
        $this->assertTrue(Totp::verify($secret, '287082', timestamp: 59));
    }
}
