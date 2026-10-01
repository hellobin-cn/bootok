<?php

namespace tests\unit;

use app\enum\Code;
use PHPUnit\Framework\TestCase;

class CodeTest extends TestCase
{
    public function testContractCodes(): void
    {
        $this->assertSame('0', Code::SUCCESS->value);
        $this->assertSame('-1', Code::FAIL->value);
        $this->assertSame('4001', Code::UNAUTHORIZED->value);
        $this->assertSame('4003', Code::FORBIDDEN->value);
        $this->assertSame('4004', Code::NOT_FOUND->value);
        $this->assertSame('4005', Code::PARAM_VALIDATE->value);
    }

    public function testDescriptionsNonEmpty(): void
    {
        foreach (Code::cases() as $case) {
            $this->assertNotSame('', $case->getDescription(), "{$case->name} 缺少描述");
        }
    }
}
