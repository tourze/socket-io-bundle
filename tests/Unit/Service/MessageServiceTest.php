<?php

namespace SocketIoBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\{Delivery, Message, Room, Socket};
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Repository\{DeliveryRepository, MessageRepository, SocketRepository};
use SocketIoBundle\Service\{MessageService, RoomService};

class MessageServiceTest extends TestCase
{
    private MessageService $messageService;
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;
    /** @var MessageRepository&MockObject */
    private MessageRepository $messageRepository;
    /** @var DeliveryRepository&MockObject */
    private DeliveryRepository $deliveryRepository;
    /** @var RoomService&MockObject */
    private RoomService $roomService;
    /** @var SocketRepository&MockObject */
    private SocketRepository $socketRepository;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->deliveryRepository = $this->createMock(DeliveryRepository::class);
        $this->roomService = $this->createMock(RoomService::class);
        $this->socketRepository = $this->createMock(SocketRepository::class);

        $this->messageService = new MessageService(
            $this->em,
            $this->messageRepository,
            $this->deliveryRepository,
            $this->roomService,
            $this->socketRepository
        );
    }

    public function testBroadcast(): void
    {
        $event = 'test-event';
        $data = ['test' => 'data'];
        $sender = new Socket('test-session-id', 'test-socket-id');
        $activeSockets = [
            new Socket('session-1', 'socket-1'),
            new Socket('session-2', 'socket-2')
        ];

        $this->socketRepository->expects($this->once())
            ->method('findActiveConnections')
            ->willReturn($activeSockets);

        $this->em->expects($this->exactly(3))
            ->method('persist')
            ->with($this->callback(function ($entity) {
                return $entity instanceof Message || $entity instanceof Delivery;
            }));

        $this->em->expects($this->exactly(3))
            ->method('flush');

        $result = $this->messageService->broadcast($event, $data, $sender);
        $this->assertEquals(2, $result);
    }

    public function testSendToRooms(): void
    {
        $rooms = ['room-1', 'room-2'];
        $event = 'test-event';
        $data = ['test' => 'data'];
        $sender = new Socket('test-session-id', 'test-socket-id');
        $room1 = new Room('room-1');
        $room2 = new Room('room-2');
        $socket1 = new Socket('session-1', 'socket-1');
        $socket2 = new Socket('session-2', 'socket-2');

        $room1->addSocket($socket1);
        $room2->addSocket($socket2);

        $this->roomService->expects($this->exactly(2))
            ->method('findOrCreateRoom')
            ->willReturnOnConsecutiveCalls($room1, $room2);

        $this->em->expects($this->exactly(3))
            ->method('persist')
            ->with($this->callback(function ($entity) {
                return $entity instanceof Message || $entity instanceof Delivery;
            }));

        $this->em->expects($this->exactly(4))
            ->method('flush');

        $this->messageService->sendToRooms($rooms, $event, $data, $sender);
    }

    public function testSendToSocket(): void
    {
        $socket = new Socket('test-session-id', 'test-socket-id');
        $event = 'test-event';
        $data = ['test' => 'data'];
        $sender = new Socket('sender-session-id', 'sender-socket-id');

        $this->em->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(function ($entity) {
                return $entity instanceof Message || $entity instanceof Delivery;
            }));

        $this->em->expects($this->exactly(2))
            ->method('flush');

        $this->messageService->sendToSocket($socket, $event, $data, $sender);
    }

    public function testSendToDisconnectedSocket(): void
    {
        $socket = new Socket('test-session-id', 'test-socket-id');
        $socket->setConnected(false);
        $event = 'test-event';
        $data = ['test' => 'data'];

        $this->em->expects($this->exactly(1))
            ->method('persist')
            ->with($this->isInstanceOf(Message::class));

        $this->em->expects($this->exactly(1))
            ->method('flush');

        $this->messageService->sendToSocket($socket, $event, $data);
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

        $result = $this->messageService->getPendingDeliveries($socket);
        $this->assertEquals($expectedDeliveries, $result);
    }

    public function testMarkDelivered(): void
    {
        $delivery = new Delivery(new Message(), new Socket('test-session-id', 'test-socket-id'));

        $this->em->expects($this->once())
            ->method('flush');

        $this->messageService->markDelivered($delivery);
        $this->assertEquals(MessageStatus::DELIVERED, $delivery->getStatus());
    }

    public function testMarkFailed(): void
    {
        $delivery = new Delivery(new Message(), new Socket('test-session-id', 'test-socket-id'));
        $error = 'Test error';

        $this->em->expects($this->once())
            ->method('flush');

        $this->messageService->markFailed($delivery, $error);
        $this->assertEquals(MessageStatus::FAILED, $delivery->getStatus());
        $this->assertEquals($error, $delivery->getError());
    }

    public function testGetMessageHistory(): void
    {
        $room = new Room('test-room');
        $limit = 10;
        $before = 100;
        $expectedMessages = [
            new Message(),
            new Message()
        ];

        $this->messageRepository->expects($this->once())
            ->method('findRoomMessages')
            ->with($room, $limit, $before)
            ->willReturn($expectedMessages);

        $result = $this->messageService->getMessageHistory($room, $limit, $before);
        $this->assertEquals($expectedMessages, $result);
    }

    public function testCleanupOldMessages(): void
    {
        $days = 30;

        $this->messageRepository->expects($this->once())
            ->method('cleanupOldMessages')
            ->with($days);

        $this->messageService->cleanupOldMessages($days);
    }

    public function testCleanupOldDeliveries(): void
    {
        $days = 7;

        $this->deliveryRepository->expects($this->once())
            ->method('cleanupOldDeliveries')
            ->with($days);

        $this->messageService->cleanupOldDeliveries($days);
    }
}
