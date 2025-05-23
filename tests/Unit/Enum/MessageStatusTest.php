<?php

namespace SocketIoBundle\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Enum\MessageStatus;

class MessageStatusTest extends TestCase
{
    public function testEnumValues(): void
    {
        // 测试枚举值是否正确定义
        $this->assertEquals(0, MessageStatus::PENDING->value);
        $this->assertEquals(1, MessageStatus::DELIVERED->value);
        $this->assertEquals(2, MessageStatus::FAILED->value);
    }

    public function testLabels(): void
    {
        // 测试 label 方法是否返回正确的标签
        $this->assertEquals('Pending', MessageStatus::PENDING->label());
        $this->assertEquals('Delivered', MessageStatus::DELIVERED->label());
        $this->assertEquals('Failed', MessageStatus::FAILED->label());
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
}
