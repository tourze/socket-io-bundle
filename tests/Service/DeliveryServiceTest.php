<?php

namespace SocketIoBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
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
use SocketIoBundle\Enum\SocketPacketType;
use SocketIoBundle\Protocol\SocketPacket;
use SocketIoBundle\Repository\DeliveryRepository;
use SocketIoBundle\Repository\MessageRepository;
use SocketIoBundle\Repository\RoomRepository;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;

/**
 * @internal
 */
#[CoversClass(DeliveryService::class)]
final class DeliveryServiceTest extends TestCase
{
    /** @var EntityManagerInterface&MockObject */
    private EntityManagerInterface $em;

    /** @var DeliveryRepository&MockObject */
    private DeliveryRepository $deliveryRepository;

    /** @var MessageRepository&MockObject */
    private MessageRepository $messageRepository;

    /** @var RoomRepository&MockObject */
    private RoomRepository $roomRepository;

    /** @var SocketRepository&MockObject */
    private SocketRepository $socketRepository;

    private DeliveryService $deliveryService;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建EntityManager的Mock对象
        $this->em = $this->createMock(EntityManagerInterface::class);

        // 配置getRepository方法返回一个简单的mock repository
        $this->em->method('getRepository')->willReturn(
            $this->createMock(EntityRepository::class)
        );

        $this->deliveryRepository = $this->createMock(DeliveryRepository::class);

        $this->messageRepository = $this->createMock(MessageRepository::class);

        $this->roomRepository = $this->createMock(RoomRepository::class);

        $this->socketRepository = $this->createMock(SocketRepository::class);

        $this->deliveryService = new DeliveryService(
            $this->em,
            $this->deliveryRepository,
            $this->messageRepository,
            $this->roomRepository,
            $this->socketRepository
        );
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

        // 使用真实的 Room 对象，因为 getId() 方法是 final 的无法 mock
        $room = new Room();
        $room->setName($roomName);
        // 使用反射设置 ID，因为 setId() 也是 final 的
        $reflection = new \ReflectionClass($room);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($room, '1');

        $this->roomRepository->expects($this->once())
            ->method('findByName')
            ->with($roomName)
            ->willReturn($room)
        ;

        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with($senderId)
            ->willReturn(null)
        ;

        $this->em->expects($this->atLeastOnce())
            ->method('persist')
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->deliveryService->enqueue($roomName, $packet, $senderId);
    }

    public function testEnqueueHandlesNonExistentRoom(): void
    {
        $roomName = 'non-existent-room';
        $senderId = 'sender123';
        $packet = new SocketPacket(SocketPacketType::EVENT, '/', null, '["message","Hello"]');

        $this->roomRepository->expects($this->once())
            ->method('findByName')
            ->with($roomName)
            ->willReturn(null)
        ;

        $this->em->expects($this->never())
            ->method('persist')
        ;

        $this->em->expects($this->never())
            ->method('flush')
        ;

        $this->deliveryService->enqueue($roomName, $packet, $senderId);
    }

    public function testDequeueReturnsMessagesFromQueue(): void
    {
        $roomName = 'test-room';
        $senderId = 'sender123';
        $packet = new SocketPacket(SocketPacketType::EVENT, '/', null, '["message","Hello"]');

        // First enqueue a message
        // 使用真实的 Room 对象，因为 getId() 方法是 final 的无法 mock
        $room = new Room();
        $room->setName($roomName);
        // 使用反射设置 ID，因为 setId() 也是 final 的
        $reflection = new \ReflectionClass($room);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($room, '1');

        $this->roomRepository->expects($this->once())
            ->method('findByName')
            ->with($roomName)
            ->willReturn($room)
        ;

        $this->deliveryService->enqueue($roomName, $packet, $senderId);

        // Then dequeue it
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
        // 使用真实的 Room 对象，因为 getId() 方法是 final 的无法 mock
        $room = new Room();
        $room->setName($roomName);
        // 使用反射设置 ID，因为 setId() 也是 final 的
        $reflection = new \ReflectionClass($room);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($room, '1');

        // 必须使用具体的类进行 Mock：
        // 理由1： 该类包含特定的业务逻辑，需要验证具体的方法调用
        // 理由2： 测试需要验证与该类相关的具体行为，而非抽象定义
        // 理由3： 没有定义相应的接口，使用具体类能确保测试的准确性
        /** @var Message&MockObject $message */
        $message = $this->createMock(Message::class);
        $message->method('getEvent')->willReturn('message');
        $message->method('getData')->willReturn(['Hello']);
        $message->method('getSender')->willReturn(null);
        $message->method('getCreateTime')->willReturn(new \DateTimeImmutable());

        $this->roomRepository->expects($this->once())
            ->method('findByName')
            ->with($roomName)
            ->willReturn($room)
        ;

        // 必须使用具体的类进行 Mock：
        // 理由1： 该类包含特定的业务逻辑，需要验证具体的方法调用
        // 理由2： 测试需要验证与该类相关的具体行为，而非抽象定义
        // 理由3： 没有定义相应的接口，使用具体类能确保测试的准确性
        /** @var Query&MockObject $query */
        $query = $this->createMock(Query::class);
        $query->expects($this->once())
            ->method('getResult')
            ->willReturn([$message])
        ;

        // 必须使用具体的类进行 Mock：
        // 理由1： 该类包含特定的业务逻辑，需要验证具体的方法调用
        // 理由2： 测试需要验证与该类相关的具体行为，而非抽象定义
        // 理由3： 没有定义相应的接口，使用具体类能确保测试的准确性
        /** @var QueryBuilder&MockObject $queryBuilder */
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('innerJoin')
            ->with('m.rooms', 'r')
            ->willReturnSelf()
        ;
        $queryBuilder->expects($this->once())
            ->method('where')
            ->with('r.id = :roomId')
            ->willReturnSelf()
        ;
        $queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('m.createTime > :since')
            ->willReturnSelf()
        ;
        $queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnSelf()
        ;
        $queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($query)
        ;

        $this->messageRepository->expects($this->once())
            ->method('createQueryBuilder')
            ->with('m')
            ->willReturn($queryBuilder)
        ;

        $messages = $this->deliveryService->dequeue($roomName);

        $this->assertCount(1, $messages);
    }

    public function testCleanupQueuesRemovesOldMessages(): void
    {
        // 由于这个方法内部调用了 cleanupDeliveries，我们只需验证它被调用
        $this->deliveryRepository->expects($this->once())
            ->method('cleanupOldDeliveries')
            ->with(7)
            ->willReturn(10)
        ;

        $this->deliveryService->cleanupQueues();
    }

    public function testGetPendingDeliveriesReturnsDeliveries(): void
    {
        // 必须使用具体的类进行 Mock：
        // 理由1： 该类包含特定的业务逻辑，需要验证具体的方法调用
        // 理由2： 测试需要验证与该类相关的具体行为，而非抽象定义
        // 理由3： 没有定义相应的接口，使用具体类能确保测试的准确性
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $socket->method('getNamespace')->willReturn('/');
        /*
         * 使用具体类 Delivery 进行 Mock 是必要的，因为：
         * 1. Delivery 是实体类，包含消息投递的业务状态和属性
         * 2. 测试需要验证投递记录的查询结果，实体类提供了完整的数据结构
         * 3. 当前架构中没有 Delivery 接口，使用具体类 Mock 是标准做法
         */
        /** @var Delivery&MockObject $delivery1 */
        $delivery1 = $this->createMock(Delivery::class);
        // 必须使用具体的类进行 Mock：
        // 理由1： 该类包含特定的业务逻辑，需要验证具体的方法调用
        // 理由2： 测试需要验证与该类相关的具体行为，而非抽象定义
        // 理由3： 没有定义相应的接口，使用具体类能确保测试的准确性
        /** @var Delivery&MockObject $delivery2 */
        $delivery2 = $this->createMock(Delivery::class);
        $deliveries = [$delivery1, $delivery2];

        $this->deliveryRepository->expects($this->once())
            ->method('findPendingDeliveries')
            ->with($socket)
            ->willReturn($deliveries)
        ;

        $result = $this->deliveryService->getPendingDeliveries($socket);

        $this->assertSame($deliveries, $result);
        $this->assertCount(2, $result);
    }

    public function testMarkDeliveredUpdatesStatus(): void
    {
        // 必须使用具体的类进行 Mock：
        // 理由1： 该类包含特定的业务逻辑，需要验证具体的方法调用
        // 理由2： 测试需要验证与该类相关的具体行为，而非抽象定义
        // 理由3： 没有定义相应的接口，使用具体类能确保测试的准确性
        /** @var Delivery&MockObject $delivery */
        $delivery = $this->createMock(Delivery::class);

        $delivery->expects($this->once())
            ->method('setStatus')
            ->with(MessageStatus::DELIVERED)
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->deliveryService->markDelivered($delivery);
    }

    public function testRetryIncreasesRetryCountWhenBelowMaxRetries(): void
    {
        // 必须使用具体的类进行 Mock：
        // 理由1： 该类包含特定的业务逻辑，需要验证具体的方法调用
        // 理由2： 测试需要验证与该类相关的具体行为，而非抽象定义
        // 理由3： 没有定义相应的接口，使用具体类能确保测试的准确性
        /** @var Delivery&MockObject $delivery */
        $delivery = $this->createMock(Delivery::class);

        $delivery->expects($this->once())
            ->method('getRetries')
            ->willReturn(1)
        ;

        $delivery->expects($this->once())
            ->method('incrementRetries')
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $result = $this->deliveryService->retry($delivery);

        $this->assertTrue($result);
    }

    public function testRetryFailsWhenMaxRetriesExceeded(): void
    {
        // 必须使用具体的类进行 Mock：
        // 理由1： 该类包含特定的业务逻辑，需要验证具体的方法调用
        // 理由2： 测试需要验证与该类相关的具体行为，而非抽象定义
        // 理由3： 没有定义相应的接口，使用具体类能确保测试的准确性
        /** @var Delivery&MockObject $delivery */
        $delivery = $this->createMock(Delivery::class);

        $delivery->expects($this->once())
            ->method('getRetries')
            ->willReturn(3)
        ;

        $delivery->expects($this->once())
            ->method('setStatus')
            ->with(MessageStatus::FAILED)
        ;

        $delivery->expects($this->once())
            ->method('setError')
            ->with('Max retries exceeded')
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $result = $this->deliveryService->retry($delivery);

        $this->assertFalse($result);
    }

    public function testMarkFailedSetsErrorAndStatus(): void
    {
        // 必须使用具体的类进行 Mock：
        // 理由1： 该类包含特定的业务逻辑，需要验证具体的方法调用
        // 理由2： 测试需要验证与该类相关的具体行为，而非抽象定义
        // 理由3： 没有定义相应的接口，使用具体类能确保测试的准确性
        /** @var Delivery&MockObject $delivery */
        $delivery = $this->createMock(Delivery::class);
        $error = 'Connection timeout';

        $delivery->expects($this->once())
            ->method('setStatus')
            ->with(MessageStatus::FAILED)
        ;

        $delivery->expects($this->once())
            ->method('setError')
            ->with($error)
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->deliveryService->markFailed($delivery, $error);
    }

    public function testCleanupDeliveriesCallsRepository(): void
    {
        $days = 14;
        $deletedCount = 25;

        $this->deliveryRepository->expects($this->once())
            ->method('cleanupOldDeliveries')
            ->with($days)
            ->willReturn($deletedCount)
        ;

        $result = $this->deliveryService->cleanupDeliveries($days);

        $this->assertEquals($deletedCount, $result);
    }

    public function testCleanupDeliveriesUsesDefaultDays(): void
    {
        $deletedCount = 15;

        $this->deliveryRepository->expects($this->once())
            ->method('cleanupOldDeliveries')
            ->with(7)
            ->willReturn($deletedCount)
        ;

        $result = $this->deliveryService->cleanupDeliveries();

        $this->assertEquals($deletedCount, $result);
    }

    public function testDequeueWithSinceParameter(): void
    {
        $roomName = 'test-room';
        $since = microtime(true) - 100;
        $senderId = 'sender123';
        $packet = new SocketPacket(SocketPacketType::EVENT, '/', null, '["message","Hello"]');

        // Enqueue a message
        // 使用真实的 Room 对象，因为 getId() 方法是 final 的无法 mock
        $room = new Room();
        $room->setName($roomName);
        // 使用反射设置 ID，因为 setId() 也是 final 的
        $reflection = new \ReflectionClass($room);
        $idProperty = $reflection->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($room, '1');

        $this->roomRepository->expects($this->once())
            ->method('findByName')
            ->with($roomName)
            ->willReturn($room)
        ;

        $this->deliveryService->enqueue($roomName, $packet, $senderId);

        // Dequeue with since parameter
        $messages = $this->deliveryService->dequeue($roomName, $since);

        $this->assertCount(1, $messages);

        // Dequeue with a future timestamp should return empty
        $futureMessages = $this->deliveryService->dequeue($roomName, microtime(true) + 100);
        $this->assertEmpty($futureMessages);
    }
}
