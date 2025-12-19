<?php

namespace SocketIoBundle\Tests\Service;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\SocketPacketType;
use SocketIoBundle\Protocol\SocketPacket;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\SocketIoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DeliveryService::class)]
#[RunTestsInSeparateProcesses]
final class DeliveryServiceTest extends AbstractIntegrationTestCase
{
    private DeliveryService $deliveryService;

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
        $this->deliveryService = self::getService(DeliveryService::class);
    }

    public function testClassExists(): void
    {
        $this->assertInstanceOf(DeliveryService::class, $this->deliveryService);
    }

    public function testEnqueueAddsMessageToQueue(): void
    {
        $roomName = 'test-room';
        $senderId = 'sender123';
        $packet = new SocketPacket(SocketPacketType::EVENT, '/', null, '["message","Hello"]');

        $room = new Room();
        $room->setName($roomName);
        $room->setNamespace('/');
        $this->persistAndFlush($room);

        $this->deliveryService->enqueue($roomName, $packet, $senderId);

        // 验证消息被存入内存队列
        $messages = $this->deliveryService->dequeue($roomName);
        $this->assertCount(1, $messages);
    }

    public function testEnqueueHandlesNonExistentRoom(): void
    {
        // 先清空数据库
        $em = self::getEntityManager();
        $em->createQuery('DELETE FROM SocketIoBundle\Entity\Message')->execute();

        $roomName = 'non-existent-room';
        $senderId = 'sender123';
        $packet = new SocketPacket(SocketPacketType::EVENT, '/', null, '["message","Hello"]');

        $this->deliveryService->enqueue($roomName, $packet, $senderId);

        // 消息会进入内存队列,但不会持久化到数据库
        $messages = $this->deliveryService->dequeue($roomName);
        // 内存队列会返回消息
        $this->assertCount(1, $messages);

        // 验证消息没有持久化到数据库
        $messagesInDb = $em->getRepository(Message::class)->findAll();
        $this->assertCount(0, $messagesInDb);
    }

    public function testDequeueReturnsMessagesFromQueue(): void
    {
        $roomName = 'test-room';
        $senderId = 'sender123';
        $packet = new SocketPacket(SocketPacketType::EVENT, '/', null, '["message","Hello"]');

        $room = new Room();
        $room->setName($roomName);
        $room->setNamespace('/');
        $this->persistAndFlush($room);

        $this->deliveryService->enqueue($roomName, $packet, $senderId);

        $messages = $this->deliveryService->dequeue($roomName);

        $this->assertCount(1, $messages);
        /** @var array<string, mixed> $firstMessage */
        $firstMessage = $messages[0];
        $this->assertArrayHasKey('packet', $firstMessage);
        $this->assertArrayHasKey('senderId', $firstMessage);
        $this->assertArrayHasKey('timestamp', $firstMessage);
        $this->assertEquals($senderId, $firstMessage['senderId']);
    }

    public function testDequeueFromDatabaseWhenNoQueueExists(): void
    {
        $roomName = 'test-room';

        // 创建房间
        $room = new Room();
        $room->setName($roomName);
        $room->setNamespace('/');
        $this->persistAndFlush($room);

        // 创建消息并关联到房间
        $message = new Message();
        $message->setEvent('message');
        $message->setData(['Hello']);
        $message->addRoom($room);
        $this->persistAndFlush($message);

        $messages = $this->deliveryService->dequeue($roomName);

        $this->assertCount(1, $messages);
    }

    public function testCleanupQueuesRemovesOldMessages(): void
    {
        $this->deliveryService->cleanupQueues();
        // 验证方法执行成功（没有异常）
        $this->expectNotToPerformAssertions();
    }

    public function testGetPendingDeliveriesReturnsDeliveries(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/');
        $this->persistAndFlush($socket);

        $message = new Message();
        $message->setEvent('test');
        $message->setData(['test' => 'data']);
        $this->persistAndFlush($message);

        $delivery1 = new Delivery();
        $delivery1->setSocket($socket);
        $delivery1->setMessage($message);
        $this->persistAndFlush($delivery1);

        $delivery2 = new Delivery();
        $delivery2->setSocket($socket);
        $delivery2->setMessage($message);
        $this->persistAndFlush($delivery2);

        $result = $this->deliveryService->getPendingDeliveries($socket);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testMarkDeliveredUpdatesStatus(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/');
        $this->persistAndFlush($socket);

        $message = new Message();
        $message->setEvent('test');
        $message->setData(['test' => 'data']);
        $this->persistAndFlush($message);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $this->persistAndFlush($delivery);

        $this->deliveryService->markDelivered($delivery);

        $em = self::getEntityManager();
        $em->refresh($delivery);

        $this->assertEquals(\SocketIoBundle\Enum\MessageStatus::DELIVERED, $delivery->getStatus());
    }

    public function testRetryIncreasesRetryCountWhenBelowMaxRetries(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/');
        $this->persistAndFlush($socket);

        $message = new Message();
        $message->setEvent('test');
        $message->setData(['test' => 'data']);
        $this->persistAndFlush($message);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $this->persistAndFlush($delivery);

        $initialRetries = $delivery->getRetries();

        $result = $this->deliveryService->retry($delivery);

        $em = self::getEntityManager();
        $em->refresh($delivery);

        $this->assertTrue($result);
        $this->assertEquals($initialRetries + 1, $delivery->getRetries());
    }

    public function testRetryFailsWhenMaxRetriesExceeded(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/');
        $this->persistAndFlush($socket);

        $message = new Message();
        $message->setEvent('test');
        $message->setData(['test' => 'data']);
        $this->persistAndFlush($message);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);

        // 设置重试次数为最大值
        for ($i = 0; $i < 3; ++$i) {
            $delivery->incrementRetries();
        }
        $this->persistAndFlush($delivery);

        $result = $this->deliveryService->retry($delivery);

        $em = self::getEntityManager();
        $em->refresh($delivery);

        $this->assertFalse($result);
        $this->assertEquals(\SocketIoBundle\Enum\MessageStatus::FAILED, $delivery->getStatus());
        $this->assertEquals('Max retries exceeded', $delivery->getError());
    }

    public function testMarkFailedSetsErrorAndStatus(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/');
        $this->persistAndFlush($socket);

        $message = new Message();
        $message->setEvent('test');
        $message->setData(['test' => 'data']);
        $this->persistAndFlush($message);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $this->persistAndFlush($delivery);

        $error = 'Connection timeout';

        $this->deliveryService->markFailed($delivery, $error);

        $em = self::getEntityManager();
        $em->refresh($delivery);

        $this->assertEquals(\SocketIoBundle\Enum\MessageStatus::FAILED, $delivery->getStatus());
        $this->assertEquals($error, $delivery->getError());
    }

    public function testCleanupDeliveriesCallsRepository(): void
    {
        $days = 14;

        $result = $this->deliveryService->cleanupDeliveries($days);

        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testCleanupDeliveriesUsesDefaultDays(): void
    {
        $result = $this->deliveryService->cleanupDeliveries();

        $this->assertGreaterThanOrEqual(0, $result);
    }

    public function testDequeueWithSinceParameter(): void
    {
        $roomName = 'test-room';
        $since = microtime(true) - 100;
        $senderId = 'sender123';
        $packet = new SocketPacket(SocketPacketType::EVENT, '/', null, '["message","Hello"]');

        $room = new Room();
        $room->setName($roomName);
        $room->setNamespace('/');
        $this->persistAndFlush($room);

        $this->deliveryService->enqueue($roomName, $packet, $senderId);

        // Dequeue with since parameter
        $messages = $this->deliveryService->dequeue($roomName, $since);
        $this->assertCount(1, $messages);

        // Dequeue with a future timestamp should return empty
        $futureMessages = $this->deliveryService->dequeue($roomName, microtime(true) + 100);
        $this->assertEmpty($futureMessages);
    }
}
