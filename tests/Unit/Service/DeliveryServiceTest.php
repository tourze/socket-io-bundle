<?php

namespace SocketIoBundle\Tests\Unit\Service;

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

        $room = new Room($roomName);
        $this->roomRepository->expects($this->once())
            ->method('findByName')
            ->with($roomName)
            ->willReturn($room);

        $sender = new Socket('test-session-id', 'test-socket-id');
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with($senderId)
            ->willReturn($sender);

        $socket1 = new Socket('session-1', 'socket-1');
        $socket2 = new Socket('session-2', 'socket-2');
        $room->addSocket($socket1);
        $room->addSocket($socket2);

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
        $since = microtime(true) - 1;

        $messages = [
            [
                'packet' => new SocketPacket(SocketPacketType::EVENT, 'test-event-1', null),
                'senderId' => 'test-sender-1',
                'timestamp' => microtime(true)
            ],
            [
                'packet' => new SocketPacket(SocketPacketType::EVENT, 'test-event-2', null),
                'senderId' => 'test-sender-2',
                'timestamp' => microtime(true) - 2
            ]
        ];

        $room = new Room($roomName);
        $this->roomRepository->expects($this->exactly(2))
            ->method('findByName')
            ->with($roomName)
            ->willReturn($room);

        $sender = new Socket('test-session-id', 'test-socket-id');
        $this->socketRepository->expects($this->exactly(2))
            ->method('findBySessionId')
            ->willReturn($sender);

        $this->em->expects($this->exactly(4))
            ->method('persist')
            ->with($this->callback(function ($entity) {
                return $entity instanceof Message || $entity instanceof Delivery;
            }));

        $this->em->expects($this->exactly(2))
            ->method('flush');

        $this->deliveryService->enqueue($roomName, $messages[0]['packet'], $messages[0]['senderId']);
        $this->deliveryService->enqueue($roomName, $messages[1]['packet'], $messages[1]['senderId']);

        $result = $this->deliveryService->dequeue($roomName, $since);
        $this->assertCount(1, $result);
        $this->assertEquals($messages[0]['packet'], $result[0]['packet']);
    }

    public function testCleanupQueues(): void
    {
        $roomName = 'test-room';
        $oldMessage = [
            'packet' => new SocketPacket(SocketPacketType::EVENT, 'test-event', null),
            'senderId' => 'test-sender',
            'timestamp' => microtime(true) - 301 // 超过5分钟
        ];

        $room = new Room($roomName);
        $this->roomRepository->expects($this->once())
            ->method('findByName')
            ->with($roomName)
            ->willReturn($room);

        $sender = new Socket('test-session-id', 'test-socket-id');
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->willReturn($sender);

        $this->em->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(function ($entity) {
                return $entity instanceof Message || $entity instanceof Delivery;
            }));

        $this->em->expects($this->once())
            ->method('flush');

        $this->deliveryService->enqueue($roomName, $oldMessage['packet'], $oldMessage['senderId']);
        $this->deliveryService->cleanupQueues();

        $result = $this->deliveryService->dequeue($roomName);
        $this->assertEmpty($result);
    }

    public function testGetPendingDeliveries(): void
    {
        $socket = new Socket('test-session-id', 'test-socket-id');
        $expectedDeliveries = [
            new Delivery(new Message(), $socket),
            new Delivery(new Message(), $socket)
        ];

        $this->deliveryRepository->expects($this->once())
            ->method('findPendingDeliveries')
            ->with($socket)
            ->willReturn($expectedDeliveries);

        $result = $this->deliveryService->getPendingDeliveries($socket);
        $this->assertEquals($expectedDeliveries, $result);
    }

    public function testMarkDelivered(): void
    {
        $delivery = new Delivery(new Message(), new Socket('test-session-id', 'test-socket-id'));

        $this->em->expects($this->once())
            ->method('flush');

        $this->deliveryService->markDelivered($delivery);
        $this->assertEquals(MessageStatus::DELIVERED, $delivery->getStatus());
    }

    public function testMarkFailed(): void
    {
        $delivery = new Delivery(new Message(), new Socket('test-session-id', 'test-socket-id'));
        $error = 'Test error';

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
