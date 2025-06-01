<?php

namespace SocketIoBundle\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Enum\EnginePacketType;

class EnginePacketTypeTest extends TestCase
{
    public function test_enum_values_are_correct(): void
    {
        $this->assertSame(0, EnginePacketType::OPEN->value);
        $this->assertSame(1, EnginePacketType::CLOSE->value);
        $this->assertSame(2, EnginePacketType::PING->value);
        $this->assertSame(3, EnginePacketType::PONG->value);
        $this->assertSame(4, EnginePacketType::MESSAGE->value);
        $this->assertSame(5, EnginePacketType::UPGRADE->value);
        $this->assertSame(6, EnginePacketType::NOOP->value);
    }

    public function test_labels_are_correct(): void
    {
        $this->assertSame('Open', EnginePacketType::OPEN->label());
        $this->assertSame('Close', EnginePacketType::CLOSE->label());
        $this->assertSame('Ping', EnginePacketType::PING->label());
        $this->assertSame('Pong', EnginePacketType::PONG->label());
        $this->assertSame('Message', EnginePacketType::MESSAGE->label());
        $this->assertSame('Upgrade', EnginePacketType::UPGRADE->label());
        $this->assertSame('Noop', EnginePacketType::NOOP->label());
    }

    public function test_all_enum_cases_exist(): void
    {
        $expectedCases = ['OPEN', 'CLOSE', 'PING', 'PONG', 'MESSAGE', 'UPGRADE', 'NOOP'];
        $actualCases = array_map(fn(EnginePacketType $case) => $case->name, EnginePacketType::cases());
        
        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(7, EnginePacketType::cases());
    }

    public function test_enum_values_are_sequential(): void
    {
        $values = array_map(fn(EnginePacketType $case) => $case->value, EnginePacketType::cases());
        $expectedValues = [0, 1, 2, 3, 4, 5, 6];
        
        $this->assertSame($expectedValues, $values);
    }

    public function test_can_instantiate_from_value(): void
    {
        $this->assertSame(EnginePacketType::OPEN, EnginePacketType::from(0));
        $this->assertSame(EnginePacketType::CLOSE, EnginePacketType::from(1));
        $this->assertSame(EnginePacketType::PING, EnginePacketType::from(2));
        $this->assertSame(EnginePacketType::PONG, EnginePacketType::from(3));
        $this->assertSame(EnginePacketType::MESSAGE, EnginePacketType::from(4));
        $this->assertSame(EnginePacketType::UPGRADE, EnginePacketType::from(5));
        $this->assertSame(EnginePacketType::NOOP, EnginePacketType::from(6));
    }

    public function test_invalid_value_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        EnginePacketType::from(999);
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(EnginePacketType::tryFrom(999));
        $this->assertNull(EnginePacketType::tryFrom(-1));
    }

    public function test_try_from_returns_correct_enum_for_valid_value(): void
    {
        $this->assertSame(EnginePacketType::OPEN, EnginePacketType::tryFrom(0));
        $this->assertSame(EnginePacketType::MESSAGE, EnginePacketType::tryFrom(4));
    }
}
