<?php

namespace SocketIoBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use SocketIoBundle\Enum\SocketPacketType;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(SocketPacketType::class)]
final class SocketPacketTypeTest extends AbstractEnumTestCase
{
    #[TestWith([SocketPacketType::CONNECT, 0, 'Connect'])]
    #[TestWith([SocketPacketType::DISCONNECT, 1, 'Disconnect'])]
    #[TestWith([SocketPacketType::EVENT, 2, 'Event'])]
    #[TestWith([SocketPacketType::ACK, 3, 'Acknowledgement'])]
    #[TestWith([SocketPacketType::ERROR, 4, 'Error'])]
    #[TestWith([SocketPacketType::BINARY_EVENT, 5, 'Binary Event'])]
    #[TestWith([SocketPacketType::BINARY_ACK, 6, 'Binary Acknowledgement'])]
    public function testValueAndLabelMapping(SocketPacketType $enum, int $expectedValue, string $expectedLabel): void
    {
        $this->assertSame($expectedValue, $enum->value);
        $this->assertSame($expectedLabel, $enum->getLabel());
    }

    public function testIsBinaryReturnsTrueForBinaryTypes(): void
    {
        $this->assertTrue(SocketPacketType::BINARY_EVENT->isBinary());
        $this->assertTrue(SocketPacketType::BINARY_ACK->isBinary());
    }

    public function testIsBinaryReturnsFalseForNonBinaryTypes(): void
    {
        $this->assertFalse(SocketPacketType::CONNECT->isBinary());
        $this->assertFalse(SocketPacketType::DISCONNECT->isBinary());
        $this->assertFalse(SocketPacketType::EVENT->isBinary());
        $this->assertFalse(SocketPacketType::ACK->isBinary());
        $this->assertFalse(SocketPacketType::ERROR->isBinary());
    }

    public function testAllEnumCasesExist(): void
    {
        $expectedCases = ['CONNECT', 'DISCONNECT', 'EVENT', 'ACK', 'ERROR', 'BINARY_EVENT', 'BINARY_ACK'];
        $actualCases = array_map(fn (SocketPacketType $case) => $case->name, SocketPacketType::cases());

        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(7, SocketPacketType::cases());
    }

    public function testEnumValuesAreSequential(): void
    {
        $values = array_map(fn (SocketPacketType $case) => $case->value, SocketPacketType::cases());
        $expectedValues = [0, 1, 2, 3, 4, 5, 6];

        $this->assertSame($expectedValues, $values);
    }

    public function testCanInstantiateFromValue(): void
    {
        $this->assertSame(SocketPacketType::CONNECT, SocketPacketType::from(0));
        $this->assertSame(SocketPacketType::DISCONNECT, SocketPacketType::from(1));
        $this->assertSame(SocketPacketType::EVENT, SocketPacketType::from(2));
        $this->assertSame(SocketPacketType::ACK, SocketPacketType::from(3));
        $this->assertSame(SocketPacketType::ERROR, SocketPacketType::from(4));
        $this->assertSame(SocketPacketType::BINARY_EVENT, SocketPacketType::from(5));
        $this->assertSame(SocketPacketType::BINARY_ACK, SocketPacketType::from(6));
    }

    public function testFromWithNegativeValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        SocketPacketType::from(-1);
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(SocketPacketType::tryFrom(999));
        $this->assertNull(SocketPacketType::tryFrom(-1));
        $this->assertNull(SocketPacketType::tryFrom(100));
    }

    public function testTryFromReturnsCorrectEnumForValidValue(): void
    {
        $this->assertSame(SocketPacketType::CONNECT, SocketPacketType::tryFrom(0));
        $this->assertSame(SocketPacketType::BINARY_EVENT, SocketPacketType::tryFrom(5));
    }

    public function testBinaryTypesOnlyIncludeExpectedCases(): void
    {
        $binaryTypes = array_filter(
            SocketPacketType::cases(),
            fn (SocketPacketType $type) => $type->isBinary()
        );

        $this->assertCount(2, $binaryTypes);
        $this->assertContains(SocketPacketType::BINARY_EVENT, $binaryTypes);
        $this->assertContains(SocketPacketType::BINARY_ACK, $binaryTypes);
    }

    public function testNonBinaryTypesIncludeExpectedCases(): void
    {
        $nonBinaryTypes = array_filter(
            SocketPacketType::cases(),
            fn (SocketPacketType $type) => !$type->isBinary()
        );

        $this->assertCount(5, $nonBinaryTypes);
        $this->assertContains(SocketPacketType::CONNECT, $nonBinaryTypes);
        $this->assertContains(SocketPacketType::DISCONNECT, $nonBinaryTypes);
        $this->assertContains(SocketPacketType::EVENT, $nonBinaryTypes);
        $this->assertContains(SocketPacketType::ACK, $nonBinaryTypes);
        $this->assertContains(SocketPacketType::ERROR, $nonBinaryTypes);
    }

    public function testToArray(): void
    {
        $array = SocketPacketType::CONNECT->toArray();

        $this->assertIsArray($array);
        $this->assertCount(2, $array);
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertEquals(0, $array['value']);
        $this->assertEquals('Connect', $array['label']);
    }

    public function testValueUniqueness(): void
    {
        $values = array_map(fn (SocketPacketType $case) => $case->value, SocketPacketType::cases());
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues, 'All enum values must be unique');
    }

    public function testLabelUniqueness(): void
    {
        $labels = array_map(fn (SocketPacketType $case) => $case->getLabel(), SocketPacketType::cases());
        $uniqueLabels = array_unique($labels);

        $this->assertCount(count($labels), $uniqueLabels, 'All enum labels must be unique');
    }
}
