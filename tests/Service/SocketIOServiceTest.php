<?php

namespace SocketIoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Event\SocketEvent;
use SocketIoBundle\Exception\StatusException;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Service\SocketIOService;
use SocketIoBundle\Service\SocketService;
use SocketIoBundle\Transport\TransportInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(SocketIOService::class)]
final class SocketIOServiceTest extends TestCase
{
    private SocketIOService $socketIOService;

    /** @var MockObject&SocketRepository */
    private SocketRepository $socketRepository;

    /** @var MockObject&SocketService */
    private SocketService $socketService;

    /** @var MockObject&DeliveryService */
    private DeliveryService $deliveryService;

    /** @var MockObject&EventDispatcherInterface */
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->socketRepository = $this->createMock(SocketRepository::class);
        $this->socketService = $this->createMock(SocketService::class);
        $this->deliveryService = $this->createMock(DeliveryService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->socketIOService = new SocketIOService(
            $this->socketRepository,
            $this->socketService,
            $this->deliveryService,
            $this->eventDispatcher
        );
    }

    public function testHandleHandshakeRequest(): void
    {
        $request = new Request();
        $request->query->set('sid', null);

        $this->socketService->expects($this->exactly(2))
            ->method('generateUniqueId')
            ->willReturnOnConsecutiveCalls('test-session-id', 'test-socket-id')
        ;

        $this->socketService->expects($this->once())
            ->method('createConnection')
            ->with('test-session-id', 'test-socket-id', 'polling', '/')
            ->willReturn((function () {
                $socket = new Socket();
                $socket->setSessionId('test-session-id');
                $socket->setSocketId('test-socket-id');

                return $socket;
            })())
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(SocketEvent::class))
        ;

        $response = $this->socketIOService->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/octet-stream', $response->headers->get('Content-Type'));
    }

    public function testHandleExistingConnection(): void
    {
        $request = new Request();
        $request->query->set('sid', 'test-session-id');

        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with('test-session-id')
            ->willReturn($socket)
        ;

        $this->socketService->expects($this->once())
            ->method('checkActive')
            ->with($socket)
        ;

        // 必须使用具体的类进行 Mock：
        // 理由1： 该类包含特定的业务逻辑，需要验证具体的方法调用
        // 理由2： 测试需要验证与该类相关的具体行为，而非抽象定义
        // 理由3： 没有定义相应的接口，使用具体类能确保测试的准确性
        /** @var MockObject&TransportInterface $transport */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('handleRequest')
            ->with($request)
            ->willReturn(new Response())
        ;

        $this->socketService->expects($this->once())
            ->method('getTransport')
            ->with($socket)
            ->willReturn($transport)
        ;

        $response = $this->socketIOService->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function testHandleInvalidSession(): void
    {
        $request = new Request();
        $request->query->set('sid', 'invalid-session-id');

        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with('invalid-session-id')
            ->willReturn(null)
        ;

        $response = $this->socketIOService->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Invalid session', $response->getContent());
    }

    public function testHandleSessionExpired(): void
    {
        $request = new Request();
        $request->query->set('sid', 'test-session-id');

        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with('test-session-id')
            ->willReturn($socket)
        ;

        $this->socketService->expects($this->once())
            ->method('checkActive')
            ->with($socket)
            ->willThrowException(new StatusException('Session expired'))
        ;

        $this->socketService->expects($this->once())
            ->method('disconnect')
            ->with($socket)
        ;

        $response = $this->socketIOService->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(410, $response->getStatusCode());
        $this->assertEquals('Session expired: Session expired', $response->getContent());
    }

    public function testHandleTransportError(): void
    {
        $request = new Request();
        $request->query->set('sid', 'test-session-id');

        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with('test-session-id')
            ->willReturn($socket)
        ;

        $this->socketService->expects($this->once())
            ->method('checkActive')
            ->with($socket)
        ;

        $this->socketService->expects($this->once())
            ->method('getTransport')
            ->with($socket)
            ->willReturn(null)
        ;

        $response = $this->socketIOService->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Transport error', $response->getContent());
    }

    public function testCleanup(): void
    {
        $sockets = [
            (function () {
                $socket = new Socket();
                $socket->setSessionId('test-session-id-1');
                $socket->setSocketId('test-socket-id-1');

                return $socket;
            })(),
            (function () {
                $socket = new Socket();
                $socket->setSessionId('test-session-id-2');
                $socket->setSocketId('test-socket-id-2');

                return $socket;
            })(),
        ];

        $this->socketRepository->expects($this->once())
            ->method('findActiveConnections')
            ->willReturn($sockets)
        ;

        $this->socketService->expects($this->exactly(2))
            ->method('checkActive')
            ->with(self::isInstanceOf(Socket::class))
            ->willThrowException(new StatusException('Session expired'))
        ;

        $this->socketService->expects($this->exactly(2))
            ->method('disconnect')
            ->with(self::isInstanceOf(Socket::class))
        ;

        $this->deliveryService->expects($this->once())
            ->method('cleanupQueues')
        ;

        $this->socketRepository->expects($this->once())
            ->method('cleanupInactiveConnections')
        ;

        $this->socketIOService->cleanup();
    }

    public function testHandleRequestWithoutSid(): void
    {
        $request = new Request();

        $this->socketService->expects($this->exactly(2))
            ->method('generateUniqueId')
            ->willReturnOnConsecutiveCalls('test-session-id', 'test-socket-id')
        ;

        $this->socketService->expects($this->once())
            ->method('createConnection')
            ->with('test-session-id', 'test-socket-id')
            ->willReturn((function () {
                $socket = new Socket();
                $socket->setSessionId('test-session-id');
                $socket->setSocketId('test-socket-id');

                return $socket;
            })())
        ;

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(self::isInstanceOf(SocketEvent::class))
        ;

        $response = $this->socketIOService->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/octet-stream', $response->headers->get('Content-Type'));
    }

    public function testInitialize(): void
    {
        $this->socketRepository->expects($this->once())
            ->method('cleanupInactiveConnections')
        ;

        $this->socketIOService->initialize();
    }
}
