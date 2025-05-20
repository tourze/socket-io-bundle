<?php

namespace SocketIoBundle\Tests\Unit\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;

class MessageTest extends TestCase
{
    private Message $message;

    protected function setUp(): void
    {
        $this->message = new Message();
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(ArrayCollection::class, $this->message->getRooms());
        $this->assertInstanceOf(ArrayCollection::class, $this->message->getDeliveries());
        $this->assertEmpty($this->message->getRooms());
        $this->assertEmpty($this->message->getDeliveries());
        $this->assertNull($this->message->getSender());
        $this->assertNull($this->message->getMetadata());
        $this->assertEquals([], $this->message->getData());
        $this->assertNull($this->message->getId());
    }

    public function testGetId(): void
    {
        $this->assertNull($this->message->getId());
    }

    public function testGetSetEvent(): void
    {
        $event = 'test-event';
        $this->message->setEvent($event);
        $this->assertEquals($event, $this->message->getEvent());
    }

    public function testGetSetData(): void
    {
        $this->assertEquals([], $this->message->getData());
        
        $data = ['key' => 'value', 'nested' => ['data' => true]];
        $this->message->setData($data);
        $this->assertEquals($data, $this->message->getData());
        
        $this->message->setData([]);
        $this->assertEquals([], $this->message->getData());
    }

    public function testGetSetSender(): void
    {
        $this->assertNull($this->message->getSender());
        
        $sender = $this->createMock(Socket::class);
        $this->message->setSender($sender);
        $this->assertSame($sender, $this->message->getSender());
        
        $this->message->setSender(null);
        $this->assertNull($this->message->getSender());
    }

    public function testGetSetMetadata(): void
    {
        $this->assertNull($this->message->getMetadata());
        
        $metadata = ['key' => 'value', 'nested' => ['data' => true]];
        $this->message->setMetadata($metadata);
        $this->assertEquals($metadata, $this->message->getMetadata());
        
        $this->message->setMetadata(null);
        $this->assertNull($this->message->getMetadata());
    }

    public function testRoomOperations(): void
    {
        // 创建测试 Room 对象
        $room = $this->createMock(Room::class);
        
        // 测试初始状态
        $this->assertEmpty($this->message->getRooms());
        
        // 测试添加 Room
        $room->expects($this->once())
            ->method('addMessage')
            ->with($this->message);
        
        $this->message->addRoom($room);
        $this->assertCount(1, $this->message->getRooms());
        $this->assertTrue($this->message->getRooms()->contains($room));
        
        // 测试重复添加相同的 Room
        $room->expects($this->never())
            ->method('addMessage');
        
        $this->message->addRoom($room);
        $this->assertCount(1, $this->message->getRooms());
        
        // 测试移除 Room
        $room->expects($this->once())
            ->method('removeMessage')
            ->with($this->message);
        
        $this->message->removeRoom($room);
        $this->assertCount(0, $this->message->getRooms());
        $this->assertFalse($this->message->getRooms()->contains($room));
        
        // 测试移除不存在的 Room
        $room->expects($this->never())
            ->method('removeMessage');
        
        $this->message->removeRoom($room);
    }

    public function testDeliveryOperations(): void
    {
        // 创建测试 Delivery 对象
        $delivery = $this->createMock(Delivery::class);
        
        // 测试初始状态
        $this->assertEmpty($this->message->getDeliveries());
        
        // 测试添加 Delivery
        $delivery->expects($this->once())
            ->method('setMessage')
            ->with($this->message);
        
        $this->message->addDelivery($delivery);
        $this->assertCount(1, $this->message->getDeliveries());
        $this->assertTrue($this->message->getDeliveries()->contains($delivery));
        
        // 测试重复添加相同的 Delivery
        $delivery->expects($this->never())
            ->method('setMessage');
        
        $this->message->addDelivery($delivery);
        $this->assertCount(1, $this->message->getDeliveries());
    }

    public function testGetSetCreateTime(): void
    {
        $this->assertNull($this->message->getCreateTime());
        
        $now = new \DateTime();
        $this->message->setCreateTime($now);
        $this->assertSame($now, $this->message->getCreateTime());
    }
} 