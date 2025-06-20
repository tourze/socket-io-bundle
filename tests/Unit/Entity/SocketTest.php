<?php

namespace SocketIoBundle\Tests\Unit\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;

class SocketTest extends TestCase
{
    private Socket $socket;
    private string $sessionId = 'test-session-id';
    private string $socketId = 'test-socket-id';

    protected function setUp(): void
    {
        $this->socket = new Socket($this->sessionId, $this->socketId);
    }

    public function testConstructor(): void
    {
        $this->assertEquals($this->sessionId, $this->socket->getSessionId());
        $this->assertEquals($this->socketId, $this->socket->getSocketId());
        $this->assertEquals('/', $this->socket->getNamespace());
        $this->assertInstanceOf(ArrayCollection::class, $this->socket->getRooms());
        $this->assertInstanceOf(ArrayCollection::class, $this->socket->getDeliveries());
        $this->assertEmpty($this->socket->getRooms());
        $this->assertEmpty($this->socket->getDeliveries());
        $this->assertTrue($this->socket->isConnected());
        $this->assertEquals(0, $this->socket->getPollCount());
        $this->assertEquals('polling', $this->socket->getTransport());
        $this->assertInstanceOf(\DateTime::class, $this->socket->getLastPingTime());
        $this->assertInstanceOf(\DateTime::class, $this->socket->getLastActiveTime());
        $this->assertNull($this->socket->getLastDeliverTime());
    }

    public function testGetId(): void
    {
        $this->assertNull($this->socket->getId());
    }

    public function testGetSetNamespace(): void
    {
        $newNamespace = '/test-namespace';
        $this->socket->setNamespace($newNamespace);
        $this->assertEquals($newNamespace, $this->socket->getNamespace());
    }

    public function testGetSetClientId(): void
    {
        $this->assertNull($this->socket->getClientId());

        $clientId = 'client-123';
        $this->socket->setClientId($clientId);
        $this->assertEquals($clientId, $this->socket->getClientId());

        $this->socket->setClientId(null);
        $this->assertNull($this->socket->getClientId());
    }

    public function testGetSetHandshake(): void
    {
        $this->assertNull($this->socket->getHandshake());

        $handshake = ['key' => 'value', 'nested' => ['data' => true]];
        $this->socket->setHandshake($handshake);
        $this->assertEquals($handshake, $this->socket->getHandshake());

        $this->socket->setHandshake(null);
        $this->assertNull($this->socket->getHandshake());
    }

    public function testGetSetLastPingTime(): void
    {
        $now = new \DateTime();
        $this->socket->setLastPingTime($now);
        $this->assertSame($now, $this->socket->getLastPingTime());

        $this->socket->setLastPingTime(null);
        $this->assertNull($this->socket->getLastPingTime());
    }

    public function testUpdatePingTime(): void
    {
        $oldPingTime = $this->socket->getLastPingTime();
        $oldActiveTime = $this->socket->getLastActiveTime();

        // 等待一小段时间确保时间戳不同
        usleep(1000);

        $this->socket->updatePingTime();

        $this->assertNotEquals($oldPingTime, $this->socket->getLastPingTime());
        $this->assertNotEquals($oldActiveTime, $this->socket->getLastActiveTime());
    }

    public function testGetSetConnectedStatus(): void
    {
        $this->assertTrue($this->socket->isConnected());

        $this->socket->setConnected(false);
        $this->assertFalse($this->socket->isConnected());

        $this->socket->setConnected(true);
        $this->assertTrue($this->socket->isConnected());
    }

    public function testGetSetTransport(): void
    {
        $this->assertEquals('polling', $this->socket->getTransport());

        $newTransport = 'websocket';
        $this->socket->setTransport($newTransport);
        $this->assertEquals($newTransport, $this->socket->getTransport());
    }

    public function testRoomOperations(): void
    {
        // 创建测试 Room 对象
        $room1 = new Room('room-1', '/');
        $room2 = new Room('room-2', '/test');

        // 测试初始状态
        $this->assertEmpty($this->socket->getRooms());
        $this->assertFalse($this->socket->isInRoom($room1));
        $this->assertFalse($this->socket->isInRoomByName('room-1'));

        // 测试加入房间
        $this->socket->joinRoom($room1);
        $this->assertCount(1, $this->socket->getRooms());
        $this->assertTrue($this->socket->isInRoom($room1));
        $this->assertTrue($this->socket->isInRoomByName('room-1'));
        $this->assertFalse($this->socket->isInRoom($room2));

        // 测试重复加入同一房间
        $this->socket->joinRoom($room1);
        $this->assertCount(1, $this->socket->getRooms());

        // 测试加入多个房间
        $this->socket->joinRoom($room2);
        $this->assertCount(2, $this->socket->getRooms());
        $this->assertTrue($this->socket->isInRoom($room2));
        $this->assertTrue($this->socket->isInRoomByName('room-2', '/test'));

        // 测试离开房间
        $this->socket->leaveRoom($room1);
        $this->assertCount(1, $this->socket->getRooms());
        $this->assertFalse($this->socket->isInRoom($room1));
        $this->assertTrue($this->socket->isInRoom($room2));

        // 测试离开所有房间
        $this->socket->leaveAllRooms();
        $this->assertEmpty($this->socket->getRooms());
        $this->assertFalse($this->socket->isInRoom($room1));
        $this->assertFalse($this->socket->isInRoom($room2));

        // 测试离开不存在的房间
        $this->socket->leaveRoom($room1);  // 不应抛出异常
    }

    public function testDeliveryOperations(): void
    {
        // 创建模拟 Delivery 对象
        $delivery = $this->createMock(Delivery::class);

        $delivery->expects($this->once())
            ->method('setSocket')
            ->with($this->socket);

        // 测试初始状态
        $this->assertEmpty($this->socket->getDeliveries());

        // 测试添加 Delivery
        $this->socket->addDelivery($delivery);
        $this->assertCount(1, $this->socket->getDeliveries());
        $this->assertTrue($this->socket->getDeliveries()->contains($delivery));
    }

    public function testPollCount(): void
    {
        $this->assertEquals(0, $this->socket->getPollCount());

        $this->socket->incrementPollCount();
        $this->assertEquals(1, $this->socket->getPollCount());

        $this->socket->incrementPollCount();
        $this->assertEquals(2, $this->socket->getPollCount());

        $this->socket->resetPollCount();
        $this->assertEquals(0, $this->socket->getPollCount());
    }

    public function testDeliverTime(): void
    {
        $this->assertNull($this->socket->getLastDeliverTime());

        $now = new \DateTime();
        $this->socket->setLastDeliverTime($now);
        $this->assertSame($now, $this->socket->getLastDeliverTime());

        $oldDeliverTime = $this->socket->getLastDeliverTime();
        usleep(1000);

        $this->socket->updateDeliverTime();
        $this->assertNotSame($oldDeliverTime, $this->socket->getLastDeliverTime());
    }

    public function testGetSetCreateTime(): void
    {
        $this->assertNull($this->socket->getCreateTime());

        $now = new \DateTimeImmutable();
        $this->socket->setCreateTime($now);
        $this->assertSame($now, $this->socket->getCreateTime());
    }

    public function testGetSetUpdateTime(): void
    {
        $this->assertNull($this->socket->getUpdateTime());

        $now = new \DateTimeImmutable();
        $this->socket->setUpdateTime($now);
        $this->assertSame($now, $this->socket->getUpdateTime());
    }
}
