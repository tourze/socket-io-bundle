<?php

namespace SocketIoBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ReflectionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Repository\DeliveryRepository;
use SocketIoBundle\Repository\MessageRepository;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\MessageService;
use SocketIoBundle\Service\RoomService;

/**
 * @internal
 */
#[CoversClass(MessageService::class)]
final class MessageServiceTest extends TestCase
{
    private MessageService $messageService;

    /** @var MockObject&EntityManagerInterface */
    private EntityManagerInterface $em;

    /** @var MockObject&MessageRepository */
    private MessageRepository $messageRepository;

    /** @var MockObject&DeliveryRepository */
    private DeliveryRepository $deliveryRepository;

    /** @var MockObject&RoomService */
    private RoomService $roomService;

    /** @var MockObject&SocketRepository */
    private SocketRepository $socketRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建EntityManager的Mock对象
        $this->em = $this->createMock(EntityManagerInterface::class);

        // 配置getRepository方法返回一个简单的mock repository
        $this->em->method('getRepository')->willReturn(
            $this->createMock(EntityRepository::class)
        );

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
        $sender = new Socket();
        $sender->setSessionId('test-session-id');
        $sender->setSocketId('test-socket-id');
        $socket1 = new Socket();
        $socket1->setSessionId('session-1');
        $socket1->setSocketId('socket-1');
        $socket2 = new Socket();
        $socket2->setSessionId('session-2');
        $socket2->setSocketId('socket-2');
        $activeSockets = [$socket1, $socket2];

        $this->socketRepository->expects($this->once())
            ->method('findActiveConnections')
            ->willReturn($activeSockets)
        ;

        $this->em->expects($this->exactly(3))
            ->method('persist')
            ->with(self::callback(function ($entity) {
                return $entity instanceof Message || $entity instanceof Delivery;
            }))
        ;

        $this->em->expects($this->exactly(3))
            ->method('flush')
        ;

        $result = $this->messageService->broadcast($event, $data, $sender);
        $this->assertEquals(2, $result);
    }

    public function testSendToRooms(): void
    {
        $rooms = ['room-1', 'room-2'];
        $event = 'test-event';
        $data = ['test' => 'data'];
        $sender = new Socket();
        $sender->setSessionId('test-session-id');
        $sender->setSocketId('test-socket-id');
        $room1 = new Room();
        $room1->setName('room-1');
        $room2 = new Room();
        $room2->setName('room-2');
        $socket1 = new Socket();
        $socket1->setSessionId('session-1');
        $socket1->setSocketId('socket-1');
        $socket2 = new Socket();
        $socket2->setSessionId('session-2');
        $socket2->setSocketId('socket-2');

        $room1->addSocket($socket1);
        $room2->addSocket($socket2);

        $this->roomService->expects($this->exactly(2))
            ->method('findOrCreateRoom')
            ->willReturnOnConsecutiveCalls($room1, $room2)
        ;

        $this->em->expects($this->exactly(3))
            ->method('persist')
            ->with(self::callback(function ($entity) {
                return $entity instanceof Message || $entity instanceof Delivery;
            }))
        ;

        $this->em->expects($this->exactly(4))
            ->method('flush')
        ;

        $this->messageService->sendToRooms($rooms, $event, $data, $sender);
    }

    public function testSendToSocket(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $event = 'test-event';
        $data = ['test' => 'data'];
        $sender = new Socket();
        $sender->setSessionId('sender-session-id');
        $sender->setSocketId('sender-socket-id');

        $this->em->expects($this->exactly(2))
            ->method('persist')
            ->with(self::callback(function ($entity) {
                return $entity instanceof Message || $entity instanceof Delivery;
            }))
        ;

        $this->em->expects($this->exactly(2))
            ->method('flush')
        ;

        $this->messageService->sendToSocket($socket, $event, $data, $sender);
    }

    public function testSendToDisconnectedSocket(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $socket->setConnected(false);
        $event = 'test-event';
        $data = ['test' => 'data'];

        $this->em->expects($this->exactly(1))
            ->method('persist')
            ->with(self::isInstanceOf(Message::class))
        ;

        $this->em->expects($this->exactly(1))
            ->method('flush')
        ;

        $this->messageService->sendToSocket($socket, $event, $data);
    }

    public function testGetPendingDeliveries(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $delivery1 = new Delivery();
        $delivery1->setSocket($socket);
        $delivery1->setMessage(new Message());

        $delivery2 = new Delivery();
        $delivery2->setSocket($socket);
        $delivery2->setMessage(new Message());

        $expectedDeliveries = [$delivery1, $delivery2];

        $this->deliveryRepository->expects($this->once())
            ->method('findPendingDeliveries')
            ->with($socket)
            ->willReturn($expectedDeliveries)
        ;

        $result = $this->messageService->getPendingDeliveries($socket);
        $this->assertEquals($expectedDeliveries, $result);
    }

    public function testMarkDelivered(): void
    {
        $delivery = new Delivery();
        $delivery->setMessage(new Message());
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $delivery->setSocket($socket);

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->messageService->markDelivered($delivery);
        $this->assertEquals(MessageStatus::DELIVERED, $delivery->getStatus());
    }

    public function testMarkFailed(): void
    {
        $delivery = new Delivery();
        $delivery->setMessage(new Message());
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $delivery->setSocket($socket);
        $error = 'Test error';

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->messageService->markFailed($delivery, $error);
        $this->assertEquals(MessageStatus::FAILED, $delivery->getStatus());
        $this->assertEquals($error, $delivery->getError());
    }

    public function testGetMessageHistory(): void
    {
        $room = new Room();
        $room->setName('test-room');
        $limit = 10;
        $before = 100;
        $expectedMessages = [
            new Message(),
            new Message(),
        ];

        $this->messageRepository->expects($this->once())
            ->method('findRoomMessages')
            ->with($room, $limit, $before)
            ->willReturn($expectedMessages)
        ;

        $result = $this->messageService->getMessageHistory($room, $limit, $before);
        $this->assertEquals($expectedMessages, $result);
    }

    public function testCleanupOldMessages(): void
    {
        $days = 30;

        $this->messageRepository->expects($this->once())
            ->method('cleanupOldMessages')
            ->with($days)
        ;

        $this->messageService->cleanupOldMessages($days);
    }

    public function testCleanupOldDeliveries(): void
    {
        $days = 7;

        $this->deliveryRepository->expects($this->once())
            ->method('cleanupOldDeliveries')
            ->with($days)
        ;

        $this->messageService->cleanupOldDeliveries($days);
    }

    public function testDispatchMessageToSocket(): void
    {
        $message = new Message();
        $message->setEvent('test-event');
        $message->setData(['test' => 'data']);

        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $socket->setConnected(true);

        $this->em->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(Delivery::class))
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->messageService->dispatchMessageToSocket($message, $socket);
    }

    public function testDispatchMessageToDisconnectedSocket(): void
    {
        $message = new Message();
        $message->setEvent('test-event');
        $message->setData(['test' => 'data']);

        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $socket->setConnected(false);

        $this->em->expects($this->never())
            ->method('persist')
        ;

        $this->em->expects($this->never())
            ->method('flush')
        ;

        $this->messageService->dispatchMessageToSocket($message, $socket);
    }
}
