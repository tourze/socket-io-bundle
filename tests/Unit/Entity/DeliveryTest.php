<?php

namespace SocketIoBundle\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;

class DeliveryTest extends TestCase
{
    private Delivery $delivery;
    private Socket $socket;
    private Message $message;

    protected function setUp(): void
    {
        $this->delivery = new Delivery();
        $this->socket = $this->createMock(Socket::class);
        $this->message = $this->createMock(Message::class);
    }

    public function testGetId(): void
    {
        $this->assertNull($this->delivery->getId());
    }

    public function testGetSetSocket(): void
    {
        $this->delivery->setSocket($this->socket);
        $this->assertSame($this->socket, $this->delivery->getSocket());
    }

    public function testGetSetMessage(): void
    {
        $this->delivery->setMessage($this->message);
        $this->assertSame($this->message, $this->delivery->getMessage());
    }

    public function testGetSetStatus(): void
    {
        // 默认状态应该是 PENDING
        $this->assertEquals(MessageStatus::PENDING, $this->delivery->getStatus());
        $this->assertTrue($this->delivery->isPending());
        $this->assertFalse($this->delivery->isDelivered());
        $this->assertFalse($this->delivery->isFailed());

        // 测试设置为 DELIVERED
        $this->delivery->setStatus(MessageStatus::DELIVERED);
        $this->assertEquals(MessageStatus::DELIVERED, $this->delivery->getStatus());
        $this->assertTrue($this->delivery->isDelivered());
        $this->assertFalse($this->delivery->isPending());
        $this->assertFalse($this->delivery->isFailed());

        // DELIVERED 状态应该自动设置 deliveredAt 时间
        $this->assertInstanceOf(\DateTime::class, $this->delivery->getDeliveredAt());

        // 测试设置为 FAILED
        $this->delivery->setStatus(MessageStatus::FAILED);
        $this->assertEquals(MessageStatus::FAILED, $this->delivery->getStatus());
        $this->assertTrue($this->delivery->isFailed());
        $this->assertFalse($this->delivery->isDelivered());
        $this->assertFalse($this->delivery->isPending());
    }

    public function testGetSetError(): void
    {
        $this->assertNull($this->delivery->getError());

        $error = 'Connection timeout';
        $this->delivery->setError($error);
        $this->assertEquals($error, $this->delivery->getError());

        $this->delivery->setError(null);
        $this->assertNull($this->delivery->getError());
    }

    public function testGetIncrementRetries(): void
    {
        $this->assertEquals(0, $this->delivery->getRetries());

        $this->delivery->incrementRetries();
        $this->assertEquals(1, $this->delivery->getRetries());

        $this->delivery->incrementRetries();
        $this->assertEquals(2, $this->delivery->getRetries());
    }

    public function testDeliveredAt(): void
    {
        // 初始状态下，deliveredAt 应该是 null
        $this->assertNull($this->delivery->getDeliveredAt());

        // 设置为 DELIVERED 应该自动设置 deliveredAt
        $this->delivery->setStatus(MessageStatus::DELIVERED);
        $this->assertInstanceOf(\DateTime::class, $this->delivery->getDeliveredAt());

        // 获取当前 deliveredAt 时间
        $firstDeliveryTime = $this->delivery->getDeliveredAt();

        // 将状态设置为 PENDING
        $this->delivery->setStatus(MessageStatus::PENDING);

        // 重新设置为 DELIVERED 应该更新 deliveredAt
        usleep(1000);
        $this->delivery->setStatus(MessageStatus::DELIVERED);
        $this->assertNotSame($firstDeliveryTime, $this->delivery->getDeliveredAt());
    }

    public function testGetSetCreateTime(): void
    {
        $this->assertNull($this->delivery->getCreateTime());

        $now = new \DateTime();
        $this->delivery->setCreateTime($now);
        $this->assertSame($now, $this->delivery->getCreateTime());
    }

    public function testGetSetUpdateTime(): void
    {
        $this->assertNull($this->delivery->getUpdateTime());

        $now = new \DateTime();
        $this->delivery->setUpdateTime($now);
        $this->assertSame($now, $this->delivery->getUpdateTime());
    }
}
