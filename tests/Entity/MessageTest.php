<?php

namespace SocketIoBundle\Tests\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(Message::class)]
final class MessageTest extends AbstractEntityTestCase
{
    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();

        $this->message = new Message();
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(ArrayCollection::class, $this->message->getRooms());
        $this->assertInstanceOf(ArrayCollection::class, $this->message->getDeliveries());
        $this->assertCount(0, $this->message->getRooms());
        $this->assertCount(0, $this->message->getDeliveries());
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

        // 使用匿名类替代 Socket Mock
        $sender = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
            }
        };
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
        // 使用匿名类替代 Room Mock
        $room = new class extends Room {
            private int $addMessageCallCount = 0;

            private int $removeMessageCallCount = 0;

            public function __construct()
            {
                parent::__construct();
            }

            public function addMessage(Message $message): void
            {
                ++$this->addMessageCallCount;
            }

            public function removeMessage(Message $message): void
            {
                ++$this->removeMessageCallCount;
            }

            public function getAddMessageCallCount(): int
            {
                return $this->addMessageCallCount;
            }

            public function getRemoveMessageCallCount(): int
            {
                return $this->removeMessageCallCount;
            }
        };

        // 测试初始状态
        $this->assertCount(0, $this->message->getRooms());

        // 测试添加 Room
        $this->message->addRoom($room);
        $this->assertCount(1, $this->message->getRooms());
        $this->assertTrue($this->message->getRooms()->contains($room));
        $this->assertEquals(1, $room->getAddMessageCallCount());

        // 测试重复添加相同的 Room
        $this->message->addRoom($room);
        $this->assertCount(1, $this->message->getRooms());
        // addMessage 不应该再次被调用
        $this->assertEquals(1, $room->getAddMessageCallCount());

        // 测试移除 Room
        $this->message->removeRoom($room);
        $this->assertCount(0, $this->message->getRooms());
        $this->assertFalse($this->message->getRooms()->contains($room));
        $this->assertEquals(1, $room->getRemoveMessageCallCount());

        // 测试移除不存在的 Room
        $this->message->removeRoom($room);
        // removeMessage 不应该再次被调用
        $this->assertEquals(1, $room->getRemoveMessageCallCount());
    }

    public function testDeliveryOperations(): void
    {
        // 创建测试 Delivery 对象
        // 使用匿名类替代 Delivery Mock
        $delivery = new class extends Delivery {
            private int $setMessageCallCount = 0;

            public function __construct()
            {
            }

            public function setMessage(Message $message): void
            {
                ++$this->setMessageCallCount;
            }

            public function getSetMessageCallCount(): int
            {
                return $this->setMessageCallCount;
            }
        };

        // 测试初始状态
        $this->assertCount(0, $this->message->getDeliveries());

        // 测试添加 Delivery
        $this->message->addDelivery($delivery);
        $this->assertCount(1, $this->message->getDeliveries());
        $this->assertTrue($this->message->getDeliveries()->contains($delivery));
        $this->assertEquals(1, $delivery->getSetMessageCallCount());

        // 测试重复添加相同的 Delivery
        $this->message->addDelivery($delivery);
        $this->assertCount(1, $this->message->getDeliveries());
        // setMessage 不应该再次被调用
        $this->assertEquals(1, $delivery->getSetMessageCallCount());
    }

    public function testGetSetCreateTime(): void
    {
        $this->assertNull($this->message->getCreateTime());

        $now = new \DateTimeImmutable();
        $this->message->setCreateTime($now);
        $this->assertSame($now, $this->message->getCreateTime());
    }

    protected function createEntity(): object
    {
        return new Message();
    }

    /**
     * @return iterable<array{string, mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'event' => ['event', 'test-event'];
        yield 'data' => ['data', ['key' => 'value']];
        yield 'metadata' => ['metadata', ['meta' => 'data']];
    }
}
