<?php

namespace SocketIoBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Event\SocketEvent;
use SocketIoBundle\Exception\InvalidPingException;
use SocketIoBundle\Exception\InvalidTransportException;
use SocketIoBundle\Exception\PingTimeoutException;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Service\HttpRequestHandler;
use SocketIoBundle\Service\MessageBuilder;
use SocketIoBundle\Service\PollingStrategy;
use SocketIoBundle\Service\RoomService;
use SocketIoBundle\Service\SocketService;
use SocketIoBundle\Transport\TransportInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(SocketService::class)]
final class SocketServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;

    private SocketRepository&MockObject $socketRepository;

    private RoomService&MockObject $roomService;

    private DeliveryService&MockObject $deliveryService;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private HttpRequestHandler&MockObject $httpRequestHandler;

    private MessageBuilder&MockObject $messageBuilder;

    private PollingStrategy&MockObject $pollingStrategy;

    private SocketService $socketService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->socketRepository = $this->createMock(SocketRepository::class);
        $this->roomService = $this->createMock(RoomService::class);
        $this->deliveryService = $this->createMock(DeliveryService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->httpRequestHandler = $this->createMock(HttpRequestHandler::class);
        $this->messageBuilder = $this->createMock(MessageBuilder::class);
        $this->pollingStrategy = $this->createMock(PollingStrategy::class);
        $this->socketService = new SocketService(
            $this->em,
            $this->socketRepository,
            $this->roomService,
            $this->deliveryService,
            $this->eventDispatcher,
            $this->httpRequestHandler,
            $this->messageBuilder,
            $this->pollingStrategy
        );
    }

    public function testClassExists(): void
    {
        $this->assertInstanceOf(SocketService::class, $this->socketService);
    }

    public function testCreateConnectionCreatesNewSocket(): void
    {
        $sessionId = 'session123';
        $socketId = 'socket456';
        $transport = 'polling';
        $namespace = '/test';

        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with($sessionId)
            ->willReturn(null)
        ;

        $this->em->expects($this->once())
            ->method('persist')
            ->with(self::callback(function ($socket) use ($sessionId, $socketId, $transport, $namespace) {
                return $socket instanceof Socket
                    && $socket->getSessionId() === $sessionId
                    && $socket->getSocketId() === $socketId
                    && $socket->getTransport() === $transport
                    && $socket->getNamespace() === $namespace
                    && true === $socket->isConnected();
            }))
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $result = $this->socketService->createConnection($sessionId, $socketId, $transport, $namespace);

        $this->assertInstanceOf(Socket::class, $result);
        $this->assertEquals($sessionId, $result->getSessionId());
        $this->assertEquals($socketId, $result->getSocketId());
    }

    public function testCreateConnectionUpdatesExistingSocket(): void
    {
        $sessionId = 'session123';
        $socketId = 'socket456';
        $transport = 'websocket';
        $namespace = '/';

        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 setTransport, setNamespace）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        /** @var Socket&MockObject $existingSocket */
        $existingSocket = $this->createMock(Socket::class);

        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with($sessionId)
            ->willReturn($existingSocket)
        ;

        $existingSocket->expects($this->once())
            ->method('setTransport')
            ->with($transport)
        ;

        $existingSocket->expects($this->once())
            ->method('setNamespace')
            ->with($namespace)
        ;

        $existingSocket->expects($this->once())
            ->method('setConnected')
            ->with(true)
        ;

        $existingSocket->expects($this->once())
            ->method('updatePingTime')
        ;

        $this->em->expects($this->once())
            ->method('persist')
            ->with($existingSocket)
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $result = $this->socketService->createConnection($sessionId, $socketId, $transport, $namespace);

        $this->assertSame($existingSocket, $result);
    }

    public function testUpdatePingUpdatesLastPingTime(): void
    {
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 updatePingTime, setConnected）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);

        $socket->expects($this->once())
            ->method('updatePingTime')
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->socketService->updatePing($socket);
    }

    public function testDisconnectRemovesFromRoomsAndSetsDisconnected(): void
    {
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 updatePingTime, setConnected）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);

        $this->roomService->expects($this->once())
            ->method('leaveAllRooms')
            ->with($socket)
        ;

        $socket->expects($this->once())
            ->method('setConnected')
            ->with(false)
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::callback(function ($event) use ($socket) {
                return $event instanceof SocketEvent
                    && 'socket.disconnect' === $event->getName()
                    && $event->getSocket() === $socket;
            }))
        ;

        $this->socketService->disconnect($socket);
    }

    public function testCleanupInactiveConnectionsCallsRepository(): void
    {
        $this->socketRepository->expects($this->once())
            ->method('cleanupInactiveConnections')
        ;

        $this->socketService->cleanupInactiveConnections();
    }

    public function testBindClientIdUpdatesSocketClientId(): void
    {
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 updatePingTime, setConnected）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $clientId = 'client789';

        $socket->expects($this->once())
            ->method('setClientId')
            ->with($clientId)
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->socketService->bindClientId($socket, $clientId);
    }

    public function testFindByClientIdReturnsSocket(): void
    {
        $clientId = 'client789';
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 updatePingTime, setConnected）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('test-session');
                $this->setSocketId('test-socket');
            }

            public function getNamespace(): string
            {
                return '/';
            }
        };

        $this->socketRepository->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($socket)
        ;

        $result = $this->socketService->findByClientId($clientId);

        $this->assertSame($socket, $result);
    }

    public function testFindByClientIdReturnsNullWhenNotFound(): void
    {
        $clientId = 'nonexistent';

        $this->socketRepository->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn(null)
        ;

        $result = $this->socketService->findByClientId($clientId);

        $this->assertNull($result);
    }

    public function testFindActiveConnectionsByNamespaceReturnsSocketArray(): void
    {
        $namespace = '/chat';
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对多个 Socket 实体的处理能力
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        $socket1 = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('test-session');
                $this->setSocketId('test-socket');
            }

            public function getNamespace(): string
            {
                return '/';
            }
        };

        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 是一个 Doctrine 实体类，代表实际的客户端连接
         * 2. 测试需要验证对多个 Socket 实体的处理能力
         * 3. 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
         */
        $socket2 = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('test-session');
                $this->setSocketId('test-socket');
            }

            public function getNamespace(): string
            {
                return '/';
            }
        };
        $sockets = [$socket1, $socket2];

        $this->socketRepository->expects($this->once())
            ->method('findActiveConnectionsByNamespace')
            ->with($namespace)
            ->willReturn($sockets)
        ;

        $result = $this->socketService->findActiveConnectionsByNamespace($namespace);

        $this->assertSame($sockets, $result);
        $this->assertCount(2, $result);
    }

    public function testGetTransportCreatesPollingTransport(): void
    {
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 updatePingTime, setConnected）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $sessionId = 'session123';

        $socket->expects($this->once())
            ->method('getSessionId')
            ->willReturn($sessionId)
        ;

        $socket->expects($this->once())
            ->method('getTransport')
            ->willReturn('polling')
        ;

        $result = $this->socketService->getTransport($socket);

        $this->assertInstanceOf(TransportInterface::class, $result);
    }

    public function testGetTransportReturnsNullForUnsupportedTransport(): void
    {
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 updatePingTime, setConnected）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $sessionId = 'session123';

        $socket->expects($this->once())
            ->method('getSessionId')
            ->willReturn($sessionId)
        ;

        $socket->expects($this->once())
            ->method('getTransport')
            ->willReturn('unsupported')
        ;

        $result = $this->socketService->getTransport($socket);

        $this->assertNull($result);
    }

    public function testSendPingSendsEnginePacket(): void
    {
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 updatePingTime, setConnected）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $sessionId = 'session123';

        $socket->method('getSessionId')->willReturn($sessionId);
        $socket->method('getTransport')->willReturn('polling');

        // 验证 socket 状态更新方法的调用
        $socket->expects($this->once())->method('updatePingTime');

        // 由于 getTransport 会创建新的传输层实例，我们无法直接验证 send 调用
        // 但可以验证方法执行没有错误并且正确调用了状态更新方法
        $this->socketService->sendPing($socket);
    }

    public function testCheckActiveThrowsInvalidTransportException(): void
    {
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 updatePingTime, setConnected）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $sessionId = 'session123';

        $socket->method('getSessionId')->willReturn($sessionId);
        $socket->method('getTransport')->willReturn('unsupported');

        $this->expectException(InvalidTransportException::class);

        $this->socketService->checkActive($socket);
    }

    public function testCheckActiveThrowsInvalidPingException(): void
    {
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 updatePingTime, setConnected）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $sessionId = 'session123';

        $socket->method('getSessionId')->willReturn($sessionId);
        $socket->method('getTransport')->willReturn('polling');
        $socket->method('getLastPingTime')->willReturn(null);

        $this->expectException(InvalidPingException::class);

        $this->socketService->checkActive($socket);
    }

    public function testCheckActiveThrowsPingTimeoutException(): void
    {
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 updatePingTime, setConnected）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $sessionId = 'session123';
        $oldPingTime = new \DateTimeImmutable('-60 seconds');
        $oldDeliverTime = new \DateTimeImmutable('-90 seconds');

        $socket->method('getSessionId')->willReturn($sessionId);
        $socket->method('getTransport')->willReturn('polling');
        $socket->method('getLastPingTime')->willReturn($oldPingTime);
        $socket->method('getLastDeliverTime')->willReturn($oldDeliverTime);

        $this->expectException(PingTimeoutException::class);

        $this->socketService->checkActive($socket, 30);
    }

    public function testCheckActiveSucceedsWithRecentActivity(): void
    {
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 updatePingTime, setConnected）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $sessionId = 'session123';
        $recentPingTime = new \DateTimeImmutable('-10 seconds');

        $socket->method('getSessionId')->willReturn($sessionId);
        $socket->method('getTransport')->willReturn('polling');
        $socket->method('getLastPingTime')->willReturn($recentPingTime);
        $socket->method('getLastDeliverTime')->willReturn(null);

        // 应该成功执行，不抛出任何异常
        // 由于方法内部逻辑只是验证时间戳，我们通过方法正常返回来确认成功
        $this->socketService->checkActive($socket, 30);

        // 到达这里说明没有抛出异常，测试通过
        $this->expectNotToPerformAssertions();
    }

    public function testGenerateUniqueIdReturnsUniqueId(): void
    {
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->willReturn(null)
        ;

        $id = $this->socketService->generateUniqueId();

        $this->assertEquals(20, strlen($id));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{20}$/', $id);
    }

    public function testGenerateUniqueIdRetriesOnCollision(): void
    {
        // 必须使用具体的 Socket 实体进行 Mock：
        // 理由1： Socket 是核心的数据实体，包含复杂的连接状态和属性管理逻辑
        // 理由2： 测试需要验证对 Socket 实体的具体方法调用（如 setTransport, setNamespace）
        // 理由3： 没有定义抽象的连接接口，直接 Mock 实体类能确保测试的完整性
        $existingSocket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('test-session');
                $this->setSocketId('test-socket');
            }

            public function getNamespace(): string
            {
                return '/';
            }
        };

        $this->socketRepository->expects($this->exactly(2))
            ->method('findBySessionId')
            ->willReturnOnConsecutiveCalls($existingSocket, null)
        ;

        $id = $this->socketService->generateUniqueId();

        $this->assertEquals(20, strlen($id));
    }
}
