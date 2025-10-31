<?php

namespace SocketIoBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use SocketIoBundle\Enum\EnginePacketType;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(EnginePacketType::class)]
final class EnginePacketTypeTest extends AbstractEnumTestCase
{
    #[TestWith([EnginePacketType::OPEN, 0, 'Open'])]
    #[TestWith([EnginePacketType::CLOSE, 1, 'Close'])]
    #[TestWith([EnginePacketType::PING, 2, 'Ping'])]
    #[TestWith([EnginePacketType::PONG, 3, 'Pong'])]
    #[TestWith([EnginePacketType::MESSAGE, 4, 'Message'])]
    #[TestWith([EnginePacketType::UPGRADE, 5, 'Upgrade'])]
    #[TestWith([EnginePacketType::NOOP, 6, 'Noop'])]
    public function testValueAndLabelMapping(EnginePacketType $enum, int $expectedValue, string $expectedLabel): void
    {
        $this->assertSame($expectedValue, $enum->value);
        $this->assertSame($expectedLabel, $enum->getLabel());
    }

    public function testAllEnumCasesExist(): void
    {
        $expectedCases = ['OPEN', 'CLOSE', 'PING', 'PONG', 'MESSAGE', 'UPGRADE', 'NOOP'];
        $actualCases = array_map(fn (EnginePacketType $case) => $case->name, EnginePacketType::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(7, EnginePacketType::cases());
    }

    public function testEnumValuesAreSequential(): void
    {
        $values = array_map(fn (EnginePacketType $case) => $case->value, EnginePacketType::cases());
        $expectedValues = [0, 1, 2, 3, 4, 5, 6];

        $this->assertSame($expectedValues, $values);
    }

    public function testCanInstantiateFromValue(): void
    {
        $this->assertSame(EnginePacketType::OPEN, EnginePacketType::from(0));
        $this->assertSame(EnginePacketType::CLOSE, EnginePacketType::from(1));
        $this->assertSame(EnginePacketType::PING, EnginePacketType::from(2));
        $this->assertSame(EnginePacketType::PONG, EnginePacketType::from(3));
        $this->assertSame(EnginePacketType::MESSAGE, EnginePacketType::from(4));
        $this->assertSame(EnginePacketType::UPGRADE, EnginePacketType::from(5));
        $this->assertSame(EnginePacketType::NOOP, EnginePacketType::from(6));
    }

    public function testFromWithNegativeValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        EnginePacketType::from(-1);
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(EnginePacketType::tryFrom(999));
        $this->assertNull(EnginePacketType::tryFrom(-1));
        $this->assertNull(EnginePacketType::tryFrom(100));
    }

    public function testTryFromReturnsCorrectEnumForValidValue(): void
    {
        $this->assertSame(EnginePacketType::OPEN, EnginePacketType::tryFrom(0));
        $this->assertSame(EnginePacketType::MESSAGE, EnginePacketType::tryFrom(4));
    }

    public function testToArray(): void
    {
        $array = EnginePacketType::OPEN->toArray();

        $this->assertIsArray($array);
        $this->assertCount(2, $array);
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertEquals(0, $array['value']);
        $this->assertEquals('Open', $array['label']);
    }

    public function testValueUniqueness(): void
    {
        $values = array_map(fn (EnginePacketType $case) => $case->value, EnginePacketType::cases());
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues, 'All enum values must be unique');
    }

    public function testLabelUniqueness(): void
    {
        $labels = array_map(fn (EnginePacketType $case) => $case->getLabel(), EnginePacketType::cases());
        $uniqueLabels = array_unique($labels);

        $this->assertCount(count($labels), $uniqueLabels, 'All enum labels must be unique');
    }
}
