<?php

namespace tests\unit;

use app\model\Model;
use PHPUnit\Framework\TestCase;

class MaskTest extends TestCase
{
    public function testPhoneMask(): void
    {
        $this->assertSame('138****5678', Model::maskValue('13812345678'));
    }

    public function testEmailMask(): void
    {
        $this->assertSame('a***@example.com', Model::maskValue('admin@example.com'));
    }

    public function testIdCardStyleMask(): void
    {
        $masked = Model::maskValue('110101199001011234');
        $this->assertSame('110101********1234', $masked);
    }

    public function testShortValueMask(): void
    {
        $this->assertSame('****', Model::maskValue('abc'));
        $this->assertSame('z****', Model::maskValue('zhang'));
    }
}
