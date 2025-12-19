<?php

namespace SocketIoBundle\Tests\Service;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Service\MessageService;
use SocketIoBundle\SocketIoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(MessageService::class)]
#[RunTestsInSeparateProcesses]
final class MessageServiceTest extends AbstractIntegrationTestCase
{
    private MessageService $messageService;

    /**
     * @return array<string, array<string, bool>>
     */
    public static function configureBundles(): array
    {
        return [
            FrameworkBundle::class => ['all' => true],
            DoctrineBundle::class => ['all' => true],
            DoctrineSnowflakeBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            SocketIoBundle::class => ['all' => true],
        ];
    }

    protected function onSetUp(): void
    {
        $this->messageService = self::getService(MessageService::class);
    }

    public function testBroadcast(): void
    {
        $event = 'test-event';
        $data = ['test' => 'data'];

        $sender = new Socket();
        $sender->setSessionId('test-session-id');
        $sender->setSocketId('test-socket-id');
        $sender->setNamespace('/');
        $sender->setConnected(true);
        $sender->updateLastActiveTime();
        $this->persistAndFlush($sender);

        $socket1 = new Socket();
        $socket1->setSessionId('session-1');
        $socket1->setSocketId('socket-1');
        $socket1->setNamespace('/');
        $socket1->setConnected(true);
        $socket1->updateLastActiveTime();
        $this->persistAndFlush($socket1);

        $socket2 = new Socket();
        $socket2->setSessionId('session-2');
        $socket2->setSocketId('socket-2');
        $socket2->setNamespace('/');
        $socket2->setConnected(true);
        $socket2->updateLastActiveTime();
        $this->persistAndFlush($socket2);

        $result = $this->messageService->broadcast($event, $data, $sender);
        // 广播会发送给所有活跃socket,包括sender
        $this->assertEquals(3, $result);
    }

    public function testSendToRooms(): void
    {
        $rooms = ['room-1', 'room-2'];
        $event = 'test-event';
        $data = ['test' => 'data'];

        $sender = new Socket();
        $sender->setSessionId('test-session-id');
        $sender->setSocketId('test-socket-id');
        $sender->setNamespace('/');
        $this->persistAndFlush($sender);

        $room1 = new Room();
        $room1->setName('room-1');
        $room1->setNamespace('/');
        $this->persistAndFlush($room1);

        $room2 = new Room();
        $room2->setName('room-2');
        $room2->setNamespace('/');
        $this->persistAndFlush($room2);

        $socket1 = new Socket();
        $socket1->setSessionId('session-1');
        $socket1->setSocketId('socket-1');
        $socket1->setNamespace('/');
        $socket1->joinRoom($room1);
        $this->persistAndFlush($socket1);

        $socket2 = new Socket();
        $socket2->setSessionId('session-2');
        $socket2->setSocketId('socket-2');
        $socket2->setNamespace('/');
        $socket2->joinRoom($room2);
        $this->persistAndFlush($socket2);

        $this->messageService->sendToRooms($rooms, $event, $data, $sender);

        $em = self::getEntityManager();
        $messages = $em->getRepository(Message::class)->findAll();

        $this->assertGreaterThanOrEqual(2, count($messages));
    }

    public function testSendToSocket(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $socket->setNamespace('/');
        $socket->setConnected(true);
        $this->persistAndFlush($socket);

        $event = 'test-event';
        $data = ['test' => 'data'];

        $sender = new Socket();
        $sender->setSessionId('sender-session-id');
        $sender->setSocketId('sender-socket-id');
        $sender->setNamespace('/');
        $this->persistAndFlush($sender);

        $this->messageService->sendToSocket($socket, $event, $data, $sender);

        $em = self::getEntityManager();
        $messages = $em->getRepository(Message::class)->findAll();

        $this->assertGreaterThanOrEqual(1, count($messages));
    }

    public function testSendToDisconnectedSocket(): void
    {
        // 先清理数据库
        $em = self::getEntityManager();
        $em->createQuery('DELETE FROM SocketIoBundle\Entity\Delivery')->execute();
        $em->createQuery('DELETE FROM SocketIoBundle\Entity\Message')->execute();

        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $socket->setNamespace('/');
        $socket->setConnected(false);
        $this->persistAndFlush($socket);

        $event = 'test-event';
        $data = ['test' => 'data'];

        $this->messageService->sendToSocket($socket, $event, $data);

        $em->clear();
        $messages = $em->getRepository(Message::class)->findAll();

        // 应该创建消息但不创建投递
        $this->assertGreaterThanOrEqual(1, count($messages));

        $deliveries = $em->getRepository(Delivery::class)->findAll();
        $this->assertCount(0, $deliveries);
    }

    public function testGetPendingDeliveries(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $socket->setNamespace('/');
        $this->persistAndFlush($socket);

        $message1 = new Message();
        $message1->setEvent('event1');
        $message1->setData(['data1']);
        $this->persistAndFlush($message1);

        $message2 = new Message();
        $message2->setEvent('event2');
        $message2->setData(['data2']);
        $this->persistAndFlush($message2);

        $delivery1 = new Delivery();
        $delivery1->setSocket($socket);
        $delivery1->setMessage($message1);
        $this->persistAndFlush($delivery1);

        $delivery2 = new Delivery();
        $delivery2->setSocket($socket);
        $delivery2->setMessage($message2);
        $this->persistAndFlush($delivery2);

        $result = $this->messageService->getPendingDeliveries($socket);

        $this->assertCount(2, $result);
    }

    public function testMarkDelivered(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $socket->setNamespace('/');
        $this->persistAndFlush($socket);

        $message = new Message();
        $message->setEvent('test');
        $message->setData(['test']);
        $this->persistAndFlush($message);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $this->persistAndFlush($delivery);

        $this->messageService->markDelivered($delivery);

        $em = self::getEntityManager();
        $em->refresh($delivery);

        $this->assertEquals(MessageStatus::DELIVERED, $delivery->getStatus());
    }

    public function testMarkFailed(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $socket->setNamespace('/');
        $this->persistAndFlush($socket);

        $message = new Message();
        $message->setEvent('test');
        $message->setData(['test']);
        $this->persistAndFlush($message);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $this->persistAndFlush($delivery);

        $error = 'Test error';

        $this->messageService->markFailed($delivery, $error);

        $em = self::getEntityManager();
        $em->refresh($delivery);

        $this->assertEquals(MessageStatus::FAILED, $delivery->getStatus());
        $this->assertEquals($error, $delivery->getError());
    }

    public function testGetMessageHistory(): void
    {
        $room = new Room();
        $room->setName('test-room');
        $room->setNamespace('/');
        $this->persistAndFlush($room);

        $message1 = new Message();
        $message1->setEvent('event1');
        $message1->setData(['data1']);
        $message1->addRoom($room);
        $this->persistAndFlush($message1);

        $message2 = new Message();
        $message2->setEvent('event2');
        $message2->setData(['data2']);
        $message2->addRoom($room);
        $this->persistAndFlush($message2);

        $limit = 10;

        // 使用 null 而不是 0，因为 findRoomMessages 在 before 不为 null 时会添加 m.id < before 条件
        $result = $this->messageService->getMessageHistory($room, $limit, null);

        $this->assertIsArray($result);
        $this->assertGreaterThanOrEqual(2, count($result));
    }

    public function testCleanupOldMessages(): void
    {
        $days = 30;

        $this->messageService->cleanupOldMessages($days);

        // 验证方法执行成功（没有异常）
        $this->expectNotToPerformAssertions();
    }

    public function testCleanupOldDeliveries(): void
    {
        $days = 7;

        $this->messageService->cleanupOldDeliveries($days);

        // 验证方法执行成功（没有异常）
        $this->expectNotToPerformAssertions();
    }

    public function testDispatchMessageToSocket(): void
    {
        $message = new Message();
        $message->setEvent('test-event');
        $message->setData(['test' => 'data']);
        $this->persistAndFlush($message);

        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $socket->setNamespace('/');
        $socket->setConnected(true);
        $this->persistAndFlush($socket);

        $this->messageService->dispatchMessageToSocket($message, $socket);

        $em = self::getEntityManager();
        $deliveries = $em->getRepository(Delivery::class)->findAll();

        $this->assertGreaterThanOrEqual(1, count($deliveries));
    }

    public function testDispatchMessageToDisconnectedSocket(): void
    {
        // 记录当前 deliveries 数量
        $em = self::getEntityManager();
        $initialCount = count($em->getRepository(Delivery::class)->findAll());

        $message = new Message();
        $message->setEvent('test-event');
        $message->setData(['test' => 'data']);
        $this->persistAndFlush($message);

        $socket = new Socket();
        $socket->setSessionId('test-session-id-disconnected');
        $socket->setSocketId('test-socket-id-disconnected');
        $socket->setNamespace('/');
        $socket->setConnected(false);
        $this->persistAndFlush($socket);

        $this->messageService->dispatchMessageToSocket($message, $socket);

        $deliveries = $em->getRepository(Delivery::class)->findAll();

        // 不应该为断开连接的 socket 创建新的投递，数量应该保持不变
        $this->assertCount($initialCount, $deliveries);
    }
}
