<?php

namespace SocketIoBundle\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Enum\SocketPacketType;

class SocketPacketTypeTest extends TestCase
{
    public function test_enum_values_are_correct(): void
    {
        $this->assertSame(0, SocketPacketType::CONNECT->value);
        $this->assertSame(1, SocketPacketType::DISCONNECT->value);
        $this->assertSame(2, SocketPacketType::EVENT->value);
        $this->assertSame(3, SocketPacketType::ACK->value);
        $this->assertSame(4, SocketPacketType::ERROR->value);
        $this->assertSame(5, SocketPacketType::BINARY_EVENT->value);
        $this->assertSame(6, SocketPacketType::BINARY_ACK->value);
    }

    public function test_labels_are_correct(): void
    {
        $this->assertSame('Connect', SocketPacketType::CONNECT->label());
        $this->assertSame('Disconnect', SocketPacketType::DISCONNECT->label());
        $this->assertSame('Event', SocketPacketType::EVENT->label());
        $this->assertSame('Acknowledgement', SocketPacketType::ACK->label());
        $this->assertSame('Error', SocketPacketType::ERROR->label());
        $this->assertSame('Binary Event', SocketPacketType::BINARY_EVENT->label());
        $this->assertSame('Binary Acknowledgement', SocketPacketType::BINARY_ACK->label());
    }

    public function test_is_binary_returns_true_for_binary_types(): void
    {
        $this->assertTrue(SocketPacketType::BINARY_EVENT->isBinary());
        $this->assertTrue(SocketPacketType::BINARY_ACK->isBinary());
    }

    public function test_is_binary_returns_false_for_non_binary_types(): void
    {
        $this->assertFalse(SocketPacketType::CONNECT->isBinary());
        $this->assertFalse(SocketPacketType::DISCONNECT->isBinary());
        $this->assertFalse(SocketPacketType::EVENT->isBinary());
        $this->assertFalse(SocketPacketType::ACK->isBinary());
        $this->assertFalse(SocketPacketType::ERROR->isBinary());
    }

    public function test_all_enum_cases_exist(): void
    {
        $expectedCases = ['CONNECT', 'DISCONNECT', 'EVENT', 'ACK', 'ERROR', 'BINARY_EVENT', 'BINARY_ACK'];
        $actualCases = array_map(fn(SocketPacketType $case) => $case->name, SocketPacketType::cases());
        
        $this->assertSame($expectedCases, $actualCases);
        $this->assertCount(7, SocketPacketType::cases());
    }

    public function test_enum_values_are_sequential(): void
    {
        $values = array_map(fn(SocketPacketType $case) => $case->value, SocketPacketType::cases());
        $expectedValues = [0, 1, 2, 3, 4, 5, 6];
        
        $this->assertSame($expectedValues, $values);
    }

    public function test_can_instantiate_from_value(): void
    {
        $this->assertSame(SocketPacketType::CONNECT, SocketPacketType::from(0));
        $this->assertSame(SocketPacketType::DISCONNECT, SocketPacketType::from(1));
        $this->assertSame(SocketPacketType::EVENT, SocketPacketType::from(2));
        $this->assertSame(SocketPacketType::ACK, SocketPacketType::from(3));
        $this->assertSame(SocketPacketType::ERROR, SocketPacketType::from(4));
        $this->assertSame(SocketPacketType::BINARY_EVENT, SocketPacketType::from(5));
        $this->assertSame(SocketPacketType::BINARY_ACK, SocketPacketType::from(6));
    }

    public function test_invalid_value_throws_exception(): void
    {
        $this->expectException(\ValueError::class);
        SocketPacketType::from(999);
    }

    public function test_try_from_returns_null_for_invalid_value(): void
    {
        $this->assertNull(SocketPacketType::tryFrom(999));
        $this->assertNull(SocketPacketType::tryFrom(-1));
    }

    public function test_try_from_returns_correct_enum_for_valid_value(): void
    {
        $this->assertSame(SocketPacketType::CONNECT, SocketPacketType::tryFrom(0));
        $this->assertSame(SocketPacketType::BINARY_EVENT, SocketPacketType::tryFrom(5));
    }

    public function test_binary_types_only_include_expected_cases(): void
    {
        $binaryTypes = array_filter(
            SocketPacketType::cases(), 
            fn(SocketPacketType $type) => $type->isBinary()
        );
        
        $this->assertCount(2, $binaryTypes);
        $this->assertContains(SocketPacketType::BINARY_EVENT, $binaryTypes);
        $this->assertContains(SocketPacketType::BINARY_ACK, $binaryTypes);
    }

    public function test_non_binary_types_include_expected_cases(): void
    {
        $nonBinaryTypes = array_filter(
            SocketPacketType::cases(),
            fn(SocketPacketType $type) => !$type->isBinary()
        );

        $this->assertCount(5, $nonBinaryTypes);
        $this->assertContains(SocketPacketType::CONNECT, $nonBinaryTypes);
        $this->assertContains(SocketPacketType::DISCONNECT, $nonBinaryTypes);
        $this->assertContains(SocketPacketType::EVENT, $nonBinaryTypes);
        $this->assertContains(SocketPacketType::ACK, $nonBinaryTypes);
        $this->assertContains(SocketPacketType::ERROR, $nonBinaryTypes);
    }
}
