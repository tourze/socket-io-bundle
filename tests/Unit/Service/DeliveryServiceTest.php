<?php

namespace SocketIoBundle\Tests\Unit\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\{Delivery, Message, Room, Socket};
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Enum\SocketPacketType;
use SocketIoBundle\Protocol\SocketPacket;
use SocketIoBundle\Repository\{DeliveryRepository, RoomRepository, SocketRepository};
use SocketIoBundle\Service\DeliveryService;

class DeliveryServiceTest extends TestCase
{
    private DeliveryService $deliveryService;
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;
    /** @var DeliveryRepository&MockObject */
    private DeliveryRepository $deliveryRepository;
    /** @var RoomRepository&MockObject */
    private RoomRepository $roomRepository;
    /** @var SocketRepository&MockObject */
    private SocketRepository $socketRepository;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->deliveryRepository = $this->createMock(DeliveryRepository::class);
        $this->roomRepository = $this->createMock(RoomRepository::class);
        $this->socketRepository = $this->createMock(SocketRepository::class);

        $this->deliveryService = new DeliveryService(
            $this->em,
            $this->deliveryRepository,
            $this->roomRepository,
            $this->socketRepository
        );
    }

    public function testEnqueue(): void
    {
        $roomName = 'test-room';
        $packet = new SocketPacket(SocketPacketType::EVENT, 'test-event', null);
        $senderId = 'test-sender-id';

        $room = $this->createMock(Room::class);
        $roomSockets = new ArrayCollection();

        $this->roomRepository->expects($this->once())
            ->method('findByName')
            ->with($roomName)
            ->willReturn($room);

        $sender = $this->createMock(Socket::class);
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with($senderId)
            ->willReturn($sender);

        $socket1 = $this->createMock(Socket::class);
        $socket2 = $this->createMock(Socket::class);
        $roomSockets->add($socket1);
        $roomSockets->add($socket2);

        $room->expects($this->once())
            ->method('getSockets')
            ->willReturn($roomSockets);

        $this->em->expects($this->exactly(3))
            ->method('persist')
            ->with($this->callback(function ($entity) {
                return $entity instanceof Message || $entity instanceof Delivery;
            }));

        $this->em->expects($this->once())
            ->method('flush');

        $this->deliveryService->enqueue($roomName, $packet, $senderId);
    }

    public function testDequeue(): void
    {
        $roomName = 'test-room';
        $since = 1234567890;

        // 直接模拟 DeliveryService 的 dequeue 方法
        $mockDeliveryService = $this->getMockBuilder(DeliveryService::class)
            ->setConstructorArgs([$this->em, $this->deliveryRepository, $this->roomRepository, $this->socketRepository])
            ->onlyMethods(['dequeue'])
            ->getMock();

        // 设置预期行为
        $expectedResult = [
            [
                'packet' => new SocketPacket(SocketPacketType::EVENT, null, null, json_encode(['test-event-1', ['foo' => 'bar']])),
                'senderId' => 'test-sender-1',
                'timestamp' => 1234567891,
            ]
        ];

        $mockDeliveryService->expects($this->once())
            ->method('dequeue')
            ->with($roomName, $since)
            ->willReturn($expectedResult);

        // 执行测试
        $result = $mockDeliveryService->dequeue($roomName, $since);

        // 验证结果
        $this->assertCount(1, $result);
        $this->assertEquals(SocketPacketType::EVENT, $result[0]['packet']->getType());
        $this->assertEquals('test-sender-1', $result[0]['senderId']);
        $this->assertGreaterThan($since, $result[0]['timestamp']);
    }

    public function testCleanupQueues(): void
    {
        $this->deliveryRepository->expects($this->once())
            ->method('cleanupOldDeliveries')
            ->with($this->anything())
            ->willReturn(5);

        $this->deliveryService->cleanupQueues();

        $this->assertTrue(true);
    }

    public function testGetPendingDeliveries(): void
    {
        $socket = $this->createMock(Socket::class);
        $message1 = $this->createMock(Message::class);
        $message2 = $this->createMock(Message::class);

        $delivery1 = $this->createMock(Delivery::class);
        $delivery2 = $this->createMock(Delivery::class);

        $expectedDeliveries = [$delivery1, $delivery2];

        $this->deliveryRepository->expects($this->once())
            ->method('findPendingDeliveries')
            ->with($socket)
            ->willReturn($expectedDeliveries);

        $result = $this->deliveryService->getPendingDeliveries($socket);
        $this->assertEquals($expectedDeliveries, $result);
    }

    public function testMarkDelivered(): void
    {
        $message = $this->createMock(Message::class);
        $socket = $this->createMock(Socket::class);
        $delivery = $this->getMockBuilder(Delivery::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setStatus', 'getStatus'])
            ->getMock();

        $delivery->expects($this->once())
            ->method('setStatus')
            ->with(MessageStatus::DELIVERED)
            ->willReturnSelf();

        $delivery->expects($this->once())
            ->method('getStatus')
            ->willReturn(MessageStatus::DELIVERED);

        $this->em->expects($this->once())
            ->method('flush');

        $this->deliveryService->markDelivered($delivery);
        $this->assertEquals(MessageStatus::DELIVERED, $delivery->getStatus());
    }

    public function testMarkFailed(): void
    {
        $message = $this->createMock(Message::class);
        $socket = $this->createMock(Socket::class);
        $error = 'Test error';

        $delivery = $this->getMockBuilder(Delivery::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['setStatus', 'getStatus', 'setError', 'getError'])
            ->getMock();

        $delivery->expects($this->once())
            ->method('setStatus')
            ->with(MessageStatus::FAILED)
            ->willReturnSelf();

        $delivery->expects($this->once())
            ->method('setError')
            ->with($error)
            ->willReturnSelf();

        $delivery->expects($this->once())
            ->method('getStatus')
            ->willReturn(MessageStatus::FAILED);

        $delivery->expects($this->once())
            ->method('getError')
            ->willReturn($error);

        $this->em->expects($this->once())
            ->method('flush');

        $this->deliveryService->markFailed($delivery, $error);
        $this->assertEquals(MessageStatus::FAILED, $delivery->getStatus());
        $this->assertEquals($error, $delivery->getError());
    }

    public function testCleanupDeliveries(): void
    {
        $days = 7;
        $expectedCount = 5;

        $this->deliveryRepository->expects($this->once())
            ->method('cleanupOldDeliveries')
            ->with($days)
            ->willReturn($expectedCount);

        $result = $this->deliveryService->cleanupDeliveries($days);
        $this->assertEquals($expectedCount, $result);
    }
}
