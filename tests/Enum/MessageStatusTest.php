<?php

namespace SocketIoBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use SocketIoBundle\Enum\MessageStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(MessageStatus::class)]
final class MessageStatusTest extends AbstractEnumTestCase
{
    #[TestWith([MessageStatus::PENDING, 0, 'Pending'])]
    #[TestWith([MessageStatus::DELIVERED, 1, 'Delivered'])]
    #[TestWith([MessageStatus::FAILED, 2, 'Failed'])]
    public function testValueAndLabelMapping(MessageStatus $enum, int $expectedValue, string $expectedLabel): void
    {
        $this->assertSame($expectedValue, $enum->value);
        $this->assertSame($expectedLabel, $enum->getLabel());
    }

    public function testIsDelivered(): void
    {
        // 测试 isDelivered 方法
        $this->assertTrue(MessageStatus::DELIVERED->isDelivered());
        $this->assertFalse(MessageStatus::PENDING->isDelivered());
        $this->assertFalse(MessageStatus::FAILED->isDelivered());
    }

    public function testIsFailed(): void
    {
        // 测试 isFailed 方法
        $this->assertTrue(MessageStatus::FAILED->isFailed());
        $this->assertFalse(MessageStatus::PENDING->isFailed());
        $this->assertFalse(MessageStatus::DELIVERED->isFailed());
    }

    public function testIsPending(): void
    {
        // 测试 isPending 方法
        $this->assertTrue(MessageStatus::PENDING->isPending());
        $this->assertFalse(MessageStatus::DELIVERED->isPending());
        $this->assertFalse(MessageStatus::FAILED->isPending());
    }

    public function testToArray(): void
    {
        $array = MessageStatus::PENDING->toArray();

        $this->assertIsArray($array);
        $this->assertCount(2, $array);
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertEquals(0, $array['value']);
        $this->assertEquals('Pending', $array['label']);
    }

    public function testFromWithNegativeValueThrowsException(): void
    {
        $this->expectException(\ValueError::class);
        MessageStatus::from(-1);
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(MessageStatus::tryFrom(999));
        $this->assertNull(MessageStatus::tryFrom(-1));
        $this->assertNull(MessageStatus::tryFrom(100));
    }

    public function testTryFromReturnsCorrectEnumForValidValue(): void
    {
        $this->assertSame(MessageStatus::PENDING, MessageStatus::tryFrom(0));
        $this->assertSame(MessageStatus::DELIVERED, MessageStatus::tryFrom(1));
        $this->assertSame(MessageStatus::FAILED, MessageStatus::tryFrom(2));
    }

    public function testValueUniqueness(): void
    {
        $values = array_map(fn (MessageStatus $case) => $case->value, MessageStatus::cases());
        $uniqueValues = array_unique($values);

        $this->assertCount(count($values), $uniqueValues, 'All enum values must be unique');
    }

    public function testLabelUniqueness(): void
    {
        $labels = array_map(fn (MessageStatus $case) => $case->getLabel(), MessageStatus::cases());
        $uniqueLabels = array_unique($labels);

        $this->assertCount(count($labels), $uniqueLabels, 'All enum labels must be unique');
    }
}
