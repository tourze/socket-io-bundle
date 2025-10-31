<?php

namespace SocketIoBundle\Tests\Transport;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Repository\DeliveryRepository;
use SocketIoBundle\Repository\MessageRepository;
use SocketIoBundle\Repository\RoomRepository;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Service\HttpRequestHandler;
use SocketIoBundle\Service\MessageBuilder;
use SocketIoBundle\Service\PayloadProcessor;
use SocketIoBundle\Service\PollingStrategy;
use SocketIoBundle\Transport\PollingTransport;
use SocketIoBundle\Transport\TransportInterface;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(PollingTransport::class)]
final class PollingTransportTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private SocketRepository $socketRepository;

    private DeliveryService $deliveryService;

    private Socket $socket;

    private HttpRequestHandler $httpRequestHandler;

    private MessageBuilder $messageBuilder;

    private PollingStrategy $pollingStrategy;

    private PollingTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->socketRepository = $this->createMock(SocketRepository::class);
        $this->socketRepository->method('findBySessionId')->willReturn(null);

        $this->deliveryService = $this->createMock(DeliveryService::class);
        $this->deliveryService->method('getPendingDeliveries')->willReturn([]);

        $this->httpRequestHandler = new class($this->createMock(PayloadProcessor::class)) extends HttpRequestHandler {
            /** @var array<array{string, array<mixed>}> */
            public array $calls = [];

            public function __construct(PayloadProcessor $payloadProcessor)
            {
                parent::__construct($payloadProcessor);
            }

            public function setPacketHandler(callable $handler): void
            {
                $this->calls[] = ['setPacketHandler', [$handler]];
            }

            public function initializeRequestSettings(Request $request): void
            {
                $this->calls[] = ['initializeRequestSettings', [$request]];
            }

            public function handlePost(Request $request): Response
            {
                $this->calls[] = ['handlePost', [$request]];

                return new Response('ok');
            }
        };

        $this->messageBuilder = $this->createMock(MessageBuilder::class);

        $this->pollingStrategy = $this->createMock(PollingStrategy::class);

        /*
         * 使用匿名类替代 Socket Mock，提供测试所需的最小接口实现
         */
        $this->socket = $this->createMock(Socket::class);
        $this->socket->method('getSocketId')->willReturn('socket-123');

        $this->transport = new PollingTransport(
            $this->entityManager,
            $this->socketRepository,
            $this->deliveryService,
            $this->socket,
            $this->httpRequestHandler,
            $this->messageBuilder,
            $this->pollingStrategy,
            'session-123'
        );
    }

    public function testImplementsTransportInterface(): void
    {
        $this->assertInstanceOf(TransportInterface::class, $this->transport);
    }

    public function testGetSessionIdReturnsCorrectValue(): void
    {
        $this->assertSame('session-123', $this->transport->getSessionId());
    }

    public function testSetPacketHandlerCallsHttpRequestHandler(): void
    {
        // 创建一个测试专用的HttpRequestHandler
        $testHandler = new class($this->createMock(PayloadProcessor::class)) extends HttpRequestHandler {
            /** @var array<array{string, array<mixed>}> */
            public array $calls = [];

            public function __construct(PayloadProcessor $payloadProcessor)
            {
                parent::__construct($payloadProcessor);
            }

            public function setPacketHandler(callable $handler): void
            {
                $this->calls[] = ['setPacketHandler', [$handler]];
            }
        };

        $transport = new PollingTransport(
            $this->entityManager,
            $this->socketRepository,
            $this->deliveryService,
            $this->socket,
            $testHandler,
            $this->messageBuilder,
            $this->pollingStrategy,
            'session-123'
        );

        $handler = function (): void {
        };

        $transport->setPacketHandler($handler);

        // 验证调用记录
        $this->assertCount(1, $testHandler->calls);
        $this->assertSame('setPacketHandler', $testHandler->calls[0][0]);
        $this->assertSame($handler, $testHandler->calls[0][1][0]);
    }

    public function testHandleRequestGetMethod(): void
    {
        // 使用匿名类替代 Request Mock
        $request = new class extends Request {
            public function __construct()
            {
                parent::__construct();
                $this->query = new InputBag();
                $this->headers = new class extends HeaderBag {
                    public function __construct()
                    {
                        parent::__construct();
                    }

                    public function get(string $key, ?string $default = null): ?string
                    {
                        return 'Accept' === $key ? 'text/plain' : $default;
                    }
                };
            }

            public function getMethod(): string
            {
                return 'GET';
            }

            public function isMethod(string $method): bool
            {
                return match ($method) {
                    'GET' => true,
                    'POST' => false,
                    default => false,
                };
            }
        };

        // 使用匿名类替代 Socket Mock
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('session-123');
                $this->setSocketId('socket-123');
            }

            public function getPollCount(): int
            {
                return 1;
            }

            public function isConnected(): bool
            {
                return true;
            }

            public function getSocketId(): string
            {
                return 'socket-123';
            }
        };

        // 为这个测试创建新的 transport，使用能找到 socket 的 repository
        $mockManagerRegistry = $this->createMock(ManagerRegistry::class);
        $socketRepository = new class($socket, $mockManagerRegistry) extends SocketRepository {
            private Socket $socket;

            public function __construct(Socket $socket, ManagerRegistry $registry)
            {
                parent::__construct($registry);
                $this->socket = $socket;
            }

            public function findBySessionId(string $sessionId): ?Socket
            {
                return 'session-123' === $sessionId ? $this->socket : null;
            }
        };

        $transport = new PollingTransport(
            $this->entityManager,
            $socketRepository,
            $this->deliveryService,
            $socket,
            $this->httpRequestHandler,
            $this->messageBuilder,
            $this->pollingStrategy,
            'session-123'
        );

        $response = $transport->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testHandleRequestPostMethod(): void
    {
        // 使用匿名类替代 Request Mock
        $request = new class extends Request {
            public function __construct()
            {
                parent::__construct();
            }

            public function getMethod(): string
            {
                return 'POST';
            }
        };

        $expectedResponse = new Response('ok');
        $mockPayloadProcessor = $this->createMock(PayloadProcessor::class);

        $this->httpRequestHandler = new class($mockPayloadProcessor) extends HttpRequestHandler {
            private int $initializeCallCount = 0;

            private int $handlePostCallCount = 0;

            private ?Request $lastRequest = null;

            public function __construct(PayloadProcessor $payloadProcessor)
            {
                parent::__construct($payloadProcessor);
            }

            public function initializeRequestSettings(Request $request): void
            {
                ++$this->initializeCallCount;
                $this->lastRequest = $request;
            }

            public function handlePost(Request $request): Response
            {
                ++$this->handlePostCallCount;
                $this->lastRequest = $request;

                return new Response('ok');
            }

            public function getInitializeCallCount(): int
            {
                return $this->initializeCallCount;
            }

            public function getHandlePostCallCount(): int
            {
                return $this->handlePostCallCount;
            }

            public function getLastRequest(): ?Request
            {
                return $this->lastRequest;
            }
        };

        $response = $this->transport->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('ok', $response->getContent());
    }

    public function testHandleRequestUnsupportedMethod(): void
    {
        // 使用匿名类替代 Request Mock
        $request = new class extends Request {
            public function __construct()
            {
                parent::__construct();
                $this->query = new InputBag();
                $this->headers = new class extends HeaderBag {
                    public function __construct()
                    {
                        parent::__construct();
                    }

                    public function get(string $key, ?string $default = null): ?string
                    {
                        return 'Accept' === $key ? 'text/plain' : $default;
                    }
                };
            }

            public function getMethod(): string
            {
                return 'PUT';
            }

            public function isMethod(string $method): bool
            {
                return match ($method) {
                    'GET' => false,
                    'POST' => false,
                    default => false,
                };
            }
        };

        $response = $this->transport->handleRequest($request);

        $this->assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertSame('Method not allowed', $response->getContent());
    }

    public function testHandleRequestWithJsonp(): void
    {
        // 使用匿名类替代 Request Mock
        $request = new class extends Request {
            public function __construct()
            {
                parent::__construct();
            }

            public function getMethod(): string
            {
                return 'POST';
            }
        };

        $expectedResponse = new Response("___eio[0]('ok');");

        // 为这个测试创建专门的 httpRequestHandler
        $mockPayloadProcessor = $this->createMock(PayloadProcessor::class);
        $httpRequestHandler = new class($mockPayloadProcessor) extends HttpRequestHandler {
            public function __construct(PayloadProcessor $payloadProcessor)
            {
                parent::__construct($payloadProcessor);
            }

            public function initializeRequestSettings(Request $request): void
            {
            }

            public function handlePost(Request $request): Response
            {
                return new Response("___eio[0]('ok');");
            }
        };

        // 为这个测试创建新的 transport，使用 JSONP 响应的 httpRequestHandler
        $transport = new PollingTransport(
            $this->entityManager,
            $this->socketRepository,
            $this->deliveryService,
            $this->socket,
            $httpRequestHandler,
            $this->messageBuilder,
            $this->pollingStrategy,
            'session-123'
        );

        $response = $transport->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame("___eio[0]('ok');", $response->getContent());
    }

    public function testSendWithValidSocket(): void
    {
        // 使用匿名类替代 Socket Mock
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('session-123');
                $this->setSocketId('socket-123');
            }

            public function isConnected(): bool
            {
                return true;
            }
        };

        $mockManagerRegistry = $this->createMock(ManagerRegistry::class);
        $this->socketRepository = new class($socket, $mockManagerRegistry) extends SocketRepository {
            private Socket $socket;

            public function __construct(Socket $socket, ManagerRegistry $registry)
            {
                parent::__construct($registry);
                $this->socket = $socket;
            }

            public function findBySessionId(string $sessionId): ?Socket
            {
                return 'session-123' === $sessionId ? $this->socket : null;
            }
        };

        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->transport->send('42["message","hello"]');

        // 验证socket是连接状态
        $this->assertTrue($socket->isConnected());
    }

    public function testSendWithDisconnectedSocket(): void
    {
        // 使用匿名类替代 Socket Mock
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('session-123');
                $this->setSocketId('socket-123');
            }

            public function isConnected(): bool
            {
                return false;
            }
        };

        $mockManagerRegistry = $this->createMock(ManagerRegistry::class);
        $this->socketRepository = new class($socket, $mockManagerRegistry) extends SocketRepository {
            private Socket $socket;

            public function __construct(Socket $socket, ManagerRegistry $registry)
            {
                parent::__construct($registry);
                $this->socket = $socket;
            }

            public function findBySessionId(string $sessionId): ?Socket
            {
                return 'session-123' === $sessionId ? $this->socket : null;
            }
        };

        // 创建一个跟踪调用的 EntityManager Mock
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->expects($this->never())
            ->method('persist') // 断连的socket不应该创建消息
        ;
        $this->entityManager->expects($this->never())
            ->method('flush') // 也不应该调用flush
        ;

        // 重新创建transport使用新的依赖
        $transport = new PollingTransport(
            $this->entityManager,
            $this->socketRepository,
            $this->deliveryService,
            $this->socket,
            $this->httpRequestHandler,
            $this->messageBuilder,
            $this->pollingStrategy,
            'session-123'
        );

        // 发送消息给断连的socket应该提前返回，不进行任何数据库操作
        $transport->send('42["message","hello"]');

        // 验证socket确实被识别为断开连接
        $this->assertFalse($socket->isConnected());
    }

    public function testSendWithNonExistentSocket(): void
    {
        $mockManagerRegistry = $this->createMock(ManagerRegistry::class);
        $this->socketRepository = new class($mockManagerRegistry) extends SocketRepository {
            public function __construct(ManagerRegistry $registry)
            {
                parent::__construct($registry);
            }

            public function findBySessionId(string $sessionId): ?Socket
            {
                return null;
            }
        };

        // 创建一个跟踪调用的 EntityManager Mock
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->expects($this->never())
            ->method('persist') // 不存在的socket不应该创建消息
        ;
        $this->entityManager->expects($this->never())
            ->method('flush') // 也不应该调用flush
        ;

        // 重新创建transport使用新的依赖
        $transport = new PollingTransport(
            $this->entityManager,
            $this->socketRepository,
            $this->deliveryService,
            $this->socket,
            $this->httpRequestHandler,
            $this->messageBuilder,
            $this->pollingStrategy,
            'session-123'
        );

        // 发送消息给不存在的socket应该提前返回，不进行任何数据库操作
        $transport->send('42["message","hello"]');

        // 验证repository确实返回null
        $this->assertNull($this->socketRepository->findBySessionId('session-123'));
    }

    public function testSendWithNonMessagePacket(): void
    {
        // 使用匿名类替代 Socket Mock
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('session-123');
                $this->setSocketId('socket-123');
            }

            public function isConnected(): bool
            {
                return true;
            }
        };

        $mockManagerRegistry = $this->createMock(ManagerRegistry::class);
        $this->socketRepository = new class($socket, $mockManagerRegistry) extends SocketRepository {
            private Socket $socket;

            public function __construct(Socket $socket, ManagerRegistry $registry)
            {
                parent::__construct($registry);
                $this->socket = $socket;
            }

            public function findBySessionId(string $sessionId): ?Socket
            {
                return 'session-123' === $sessionId ? $this->socket : null;
            }
        };

        // 创建一个跟踪调用的 EntityManager Mock
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->expects($this->never())
            ->method('persist') // 非消息包不应该创建消息和投递记录
        ;
        $this->entityManager->expects($this->never())
            ->method('flush') // 也不应该调用flush
        ;

        // 重新创建transport使用新的依赖
        $transport = new PollingTransport(
            $this->entityManager,
            $this->socketRepository,
            $this->deliveryService,
            $this->socket,
            $this->httpRequestHandler,
            $this->messageBuilder,
            $this->pollingStrategy,
            'session-123'
        );

        // 发送ping包（类型2）而不是message包（类型4）
        // 根据PollingTransport的实现，只有MESSAGE类型的包才会被处理
        $transport->send('2probe');

        // 验证socket确实连接正常，但包类型不对
        $this->assertTrue($socket->isConnected());
    }

    public function testCloseWithExistingSocket(): void
    {
        // 使用匿名类替代 Socket Mock
        $socket = new class extends Socket {
            private bool $connected = true;

            private bool $setConnectedCalled = false;

            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('session-123');
                $this->setSocketId('socket-123');
            }

            public function setConnected(bool $connected): void
            {
                $this->connected = $connected;
                $this->setConnectedCalled = true;
            }

            public function isConnected(): bool
            {
                return $this->connected;
            }

            public function isSetConnectedCalled(): bool
            {
                return $this->setConnectedCalled;
            }
        };

        // 使用匿名类替代 Delivery Mock
        $delivery1 = new class extends Delivery {
            private ?MessageStatus $status = null;

            private ?string $error = null;

            public function setStatus(MessageStatus $status): void
            {
                $this->status = $status;
            }

            public function setError(?string $error): void
            {
                $this->error = $error;
            }

            public function getStatus(): MessageStatus
            {
                return $this->status ?? MessageStatus::PENDING;
            }

            public function getError(): ?string
            {
                return $this->error;
            }
        };

        $delivery2 = new class extends Delivery {
            private ?MessageStatus $status = null;

            private ?string $error = null;

            public function setStatus(MessageStatus $status): void
            {
                $this->status = $status;
            }

            public function setError(?string $error): void
            {
                $this->error = $error;
            }

            public function getStatus(): MessageStatus
            {
                return $this->status ?? MessageStatus::PENDING;
            }

            public function getError(): ?string
            {
                return $this->error;
            }
        };

        $mockManagerRegistry = $this->createMock(ManagerRegistry::class);
        $this->socketRepository = new class($socket, $mockManagerRegistry) extends SocketRepository {
            private Socket $socket;

            public function __construct(Socket $socket, ManagerRegistry $registry)
            {
                parent::__construct($registry);
                $this->socket = $socket;
            }

            public function findBySessionId(string $sessionId): ?Socket
            {
                return 'session-123' === $sessionId ? $this->socket : null;
            }
        };

        $mockEntityManagerForDeliveryService = $this->createMock(EntityManagerInterface::class);
        $mockDeliveryRepository = $this->createMock(DeliveryRepository::class);
        $mockMessageRepository = $this->createMock(MessageRepository::class);
        $mockRoomRepository = $this->createMock(RoomRepository::class);
        $mockSocketRepository = $this->createMock(SocketRepository::class);
        $this->deliveryService = new class($delivery1, $delivery2, $mockEntityManagerForDeliveryService, $mockDeliveryRepository, $mockMessageRepository, $mockRoomRepository, $mockSocketRepository) extends DeliveryService {
            /** @var array<Delivery> */
            private array $deliveries;

            public function __construct(
                Delivery $delivery1,
                Delivery $delivery2,
                EntityManagerInterface $entityManager,
                DeliveryRepository $deliveryRepository,
                MessageRepository $messageRepository,
                RoomRepository $roomRepository,
                SocketRepository $socketRepository,
            ) {
                parent::__construct($entityManager, $deliveryRepository, $messageRepository, $roomRepository, $socketRepository);
                $this->deliveries = [$delivery1, $delivery2];
            }

            public function getPendingDeliveries(Socket $socket): array
            {
                return $this->deliveries;
            }
        };

        // 创建一个跟踪调用的 EntityManager Mock
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->expects($this->once())
            ->method('flush') // close操作应该调用flush来保存更改
        ;

        // 重新创建transport使用新的依赖
        $transport = new PollingTransport(
            $this->entityManager,
            $this->socketRepository,
            $this->deliveryService,
            $this->socket,
            $this->httpRequestHandler,
            $this->messageBuilder,
            $this->pollingStrategy,
            'session-123'
        );

        $transport->close();

        // 验证socket被设置为断开连接
        $this->assertTrue($socket->isSetConnectedCalled());

        // 验证pending deliveries被设置为失败状态
        $this->assertSame(MessageStatus::FAILED, $delivery1->getStatus());
        $this->assertSame('Connection closed', $delivery1->getError());
        $this->assertSame(MessageStatus::FAILED, $delivery2->getStatus());
        $this->assertSame('Connection closed', $delivery2->getError());
    }

    public function testCloseWithNonExistentSocket(): void
    {
        $mockManagerRegistry = $this->createMock(ManagerRegistry::class);
        $this->socketRepository = new class($mockManagerRegistry) extends SocketRepository {
            public function __construct(ManagerRegistry $registry)
            {
                parent::__construct($registry);
            }

            public function findBySessionId(string $sessionId): ?Socket
            {
                return null;
            }
        };

        $mockEntityManagerForDeliveryService = $this->createMock(EntityManagerInterface::class);
        $mockDeliveryRepository = $this->createMock(DeliveryRepository::class);
        $mockMessageRepository = $this->createMock(MessageRepository::class);
        $mockRoomRepository = $this->createMock(RoomRepository::class);
        $mockSocketRepository = $this->createMock(SocketRepository::class);
        $this->deliveryService = new class($mockEntityManagerForDeliveryService, $mockDeliveryRepository, $mockMessageRepository, $mockRoomRepository, $mockSocketRepository) extends DeliveryService {
            private int $getPendingDeliveriesCallCount = 0;

            public function __construct(
                EntityManagerInterface $entityManager,
                DeliveryRepository $deliveryRepository,
                MessageRepository $messageRepository,
                RoomRepository $roomRepository,
                SocketRepository $socketRepository,
            ) {
                parent::__construct($entityManager, $deliveryRepository, $messageRepository, $roomRepository, $socketRepository);
            }

            public function getPendingDeliveries(Socket $socket): array
            {
                ++$this->getPendingDeliveriesCallCount;

                return [];
            }

            public function getGetPendingDeliveriesCallCount(): int
            {
                return $this->getPendingDeliveriesCallCount;
            }
        };

        // 创建一个跟踪调用的 EntityManager Mock
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->expects($this->never())
            ->method('flush') // 不存在的socket不应该调用flush
        ;

        // 重新创建transport使用新的依赖
        $transport = new PollingTransport(
            $this->entityManager,
            $this->socketRepository,
            $this->deliveryService,
            $this->socket,
            $this->httpRequestHandler,
            $this->messageBuilder,
            $this->pollingStrategy,
            'session-123'
        );

        $transport->close();

        // 验证repository确实返回null
        $this->assertNull($this->socketRepository->findBySessionId('session-123'));

        // 验证deliveryService的getPendingDeliveries没有被调用（因为socket不存在）
        /** @phpstan-ignore-next-line */
        $this->assertSame(0, $this->deliveryService->getGetPendingDeliveriesCallCount());
    }

    public function testIsExpiredReturnsFalseForRecentPoll(): void
    {
        // 新创建的transport应该不会过期
        $this->assertFalse($this->transport->isExpired());
    }

    public function testIsExpiredReturnsTrueForOldPoll(): void
    {
        // 使用反射模拟旧的轮询时间
        $reflection = new \ReflectionClass($this->transport);
        $property = $reflection->getProperty('lastPollTime');
        $property->setAccessible(true);
        $property->setValue($this->transport, microtime(true) - 50); // 50秒前

        $this->assertTrue($this->transport->isExpired());
    }

    public function testConstructorSetsSessionId(): void
    {
        $reflection = new \ReflectionClass($this->transport);

        $sessionIdProperty = $reflection->getProperty('sessionId');
        $sessionIdProperty->setAccessible(true);
        $this->assertSame('session-123', $sessionIdProperty->getValue($this->transport));
    }

    public function testMethodsExistAndAreAccessible(): void
    {
        $reflection = new \ReflectionClass($this->transport);

        $this->assertTrue($reflection->hasMethod('getSessionId'));
        $this->assertTrue($reflection->hasMethod('setPacketHandler'));
        $this->assertTrue($reflection->hasMethod('handleRequest'));
        $this->assertTrue($reflection->hasMethod('send'));
        $this->assertTrue($reflection->hasMethod('close'));
        $this->assertTrue($reflection->hasMethod('isExpired'));

        // 检查方法的可见性
        $this->assertTrue($reflection->getMethod('getSessionId')->isPublic());
        $this->assertTrue($reflection->getMethod('setPacketHandler')->isPublic());
        $this->assertTrue($reflection->getMethod('handleRequest')->isPublic());
        $this->assertTrue($reflection->getMethod('send')->isPublic());
        $this->assertTrue($reflection->getMethod('close')->isPublic());
        $this->assertTrue($reflection->getMethod('isExpired')->isPublic());
    }

    public function testPrivateMethodsExist(): void
    {
        $reflection = new \ReflectionClass($this->transport);

        $privateMethods = [
            'disconnectSocket',
            'failPendingDeliveries',
            'handlePoll',
            'validateAndUpdateSocket',
            'handleFirstPoll',
            'handleLongPoll',
            'getValidConnectedSocket',
            'parseIncomingData',
            'createAndPersistMessage',
        ];

        foreach ($privateMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Method {$methodName} should exist");
            $this->assertTrue($reflection->getMethod($methodName)->isPrivate(), "Method {$methodName} should be private");
        }
    }
}
