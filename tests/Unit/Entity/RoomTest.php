<?php

namespace SocketIoBundle\Tests\Unit\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;

class RoomTest extends TestCase
{
    private Room $room;
    private string $roomName = 'test-room';
    private string $namespace = '/test';

    protected function setUp(): void
    {
        $this->room = new Room($this->roomName, $this->namespace);
    }

    public function testConstructor(): void
    {
        // 测试构造函数是否正确设置属性
        $this->assertEquals($this->roomName, $this->room->getName());
        $this->assertEquals($this->namespace, $this->room->getNamespace());
        $this->assertInstanceOf(ArrayCollection::class, $this->room->getSockets());
        $this->assertInstanceOf(ArrayCollection::class, $this->room->getMessages());
        $this->assertEmpty($this->room->getSockets());
        $this->assertEmpty($this->room->getMessages());
    }

    public function testGetId(): void
    {
        // 测试 ID 是否是默认值
        $this->assertEquals(null, $this->room->getId());
    }

    public function testGetSetName(): void
    {
        // 测试 getName 和 setName 方法
        $newName = 'new-room-name';
        $this->room->setName($newName);
        $this->assertEquals($newName, $this->room->getName());
    }

    public function testGetSetNamespace(): void
    {
        // 测试 getNamespace 和 setNamespace 方法
        $newNamespace = '/new-namespace';
        $this->room->setNamespace($newNamespace);
        $this->assertEquals($newNamespace, $this->room->getNamespace());
    }

    public function testGetSetMetadata(): void
    {
        // 测试 getMetadata 和 setMetadata 方法
        $this->assertNull($this->room->getMetadata());

        $metadata = ['key' => 'value', 'nested' => ['data' => true]];
        $this->room->setMetadata($metadata);
        $this->assertEquals($metadata, $this->room->getMetadata());

        $this->room->setMetadata(null);
        $this->assertNull($this->room->getMetadata());
    }

    public function testSocketOperations(): void
    {
        // 创建测试 Socket 对象
        $socket = $this->createMock(Socket::class);

        // 测试初始状态
        $this->assertEmpty($this->room->getSockets());

        // 测试添加 Socket
        $this->room->addSocket($socket);
        $this->assertCount(1, $this->room->getSockets());
        $this->assertTrue($this->room->getSockets()->contains($socket));

        // 测试重复添加相同的 Socket
        $this->room->addSocket($socket);
        $this->assertCount(1, $this->room->getSockets()); // 不应该增加

        // 测试移除 Socket
        $this->room->removeSocket($socket);
        $this->assertCount(0, $this->room->getSockets());
        $this->assertFalse($this->room->getSockets()->contains($socket));

        // 测试移除不存在的 Socket
        $this->room->removeSocket($socket); // 不应抛出异常
    }

    public function testMessageOperations(): void
    {
        // 创建测试 Message 对象
        $message = $this->createMock(Message::class);

        // 测试初始状态
        $this->assertEmpty($this->room->getMessages());

        // 测试添加 Message
        $message->expects($this->once())
            ->method('addRoom')
            ->with($this->room);

        $this->room->addMessage($message);
        $this->assertCount(1, $this->room->getMessages());
        $this->assertTrue($this->room->getMessages()->contains($message));

        // 测试重复添加相同的 Message
        $message->expects($this->never())
            ->method('addRoom'); // 第二次不会调用

        $this->room->addMessage($message);
        $this->assertCount(1, $this->room->getMessages()); // 不应该增加

        // 测试移除 Message
        $message->expects($this->once())
            ->method('removeRoom')
            ->with($this->room);

        $this->room->removeMessage($message);
        $this->assertCount(0, $this->room->getMessages());
        $this->assertFalse($this->room->getMessages()->contains($message));

        // 测试移除不存在的 Message
        $message->expects($this->never())
            ->method('removeRoom');

        $this->room->removeMessage($message); // 不应抛出异常
    }

    public function testCreateTime(): void
    {
        // 测试 getCreateTime 和 setCreateTime 方法
        $this->assertNull($this->room->getCreateTime());

        $now = new \DateTime();
        $this->room->setCreateTime($now);
        $this->assertSame($now, $this->room->getCreateTime());
    }

    public function testUpdateTime(): void
    {
        // 测试 getUpdateTime 和 setUpdateTime 方法
        $this->assertNull($this->room->getUpdateTime());

        $now = new \DateTime();
        $this->room->setUpdateTime($now);
        $this->assertSame($now, $this->room->getUpdateTime());
    }
}
