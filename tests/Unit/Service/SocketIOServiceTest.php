<?php

namespace SocketIoBundle\Tests\Unit\Service;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Event\SocketEvent;
use SocketIoBundle\Exception\StatusException;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\{DeliveryService, SocketIOService, SocketService};
use SocketIoBundle\Transport\TransportInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\{Request, Response};

class SocketIOServiceTest extends TestCase
{
    private SocketIOService $socketIOService;
    /** @var SocketRepository&MockObject */
    private SocketRepository $socketRepository;
    /** @var SocketService&MockObject */
    private SocketService $socketService;
    /** @var DeliveryService&MockObject */
    private DeliveryService $deliveryService;
    /** @var EventDispatcherInterface&MockObject */
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
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
            ->willReturnOnConsecutiveCalls('test-session-id', 'test-socket-id');

        $this->socketService->expects($this->once())
            ->method('createConnection')
            ->with('test-session-id', 'test-socket-id', 'polling', '/')
            ->willReturn(new Socket('test-session-id', 'test-socket-id'));

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SocketEvent::class));

        $response = $this->socketIOService->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/octet-stream', $response->headers->get('Content-Type'));
    }

    public function testHandleExistingConnection(): void
    {
        $request = new Request();
        $request->query->set('sid', 'test-session-id');

        $socket = new Socket('test-session-id', 'test-socket-id');
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with('test-session-id')
            ->willReturn($socket);

        $this->socketService->expects($this->once())
            ->method('checkActive')
            ->with($socket);

        /** @var TransportInterface&MockObject */
        $transport = $this->createMock(TransportInterface::class);
        $transport->expects($this->once())
            ->method('handleRequest')
            ->with($request)
            ->willReturn(new Response());

        $this->socketService->expects($this->once())
            ->method('getTransport')
            ->with($socket)
            ->willReturn($transport);

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
            ->willReturn(null);

        $response = $this->socketIOService->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('Invalid session', $response->getContent());
    }

    public function testHandleSessionExpired(): void
    {
        $request = new Request();
        $request->query->set('sid', 'test-session-id');

        $socket = new Socket('test-session-id', 'test-socket-id');
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with('test-session-id')
            ->willReturn($socket);

        $this->socketService->expects($this->once())
            ->method('checkActive')
            ->with($socket)
            ->willThrowException(new StatusException('Session expired'));

        $this->socketService->expects($this->once())
            ->method('disconnect')
            ->with($socket);

        $response = $this->socketIOService->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(410, $response->getStatusCode());
        $this->assertEquals('Session expired: Session expired', $response->getContent());
    }

    public function testHandleTransportError(): void
    {
        $request = new Request();
        $request->query->set('sid', 'test-session-id');

        $socket = new Socket('test-session-id', 'test-socket-id');
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with('test-session-id')
            ->willReturn($socket);

        $this->socketService->expects($this->once())
            ->method('checkActive')
            ->with($socket);

        $this->socketService->expects($this->once())
            ->method('getTransport')
            ->with($socket)
            ->willReturn(null);

        $response = $this->socketIOService->handleRequest($request);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals('Transport error', $response->getContent());
    }

    public function testCleanup(): void
    {
        $sockets = [
            new Socket('test-session-id-1', 'test-socket-id-1'),
            new Socket('test-session-id-2', 'test-socket-id-2')
        ];

        $this->socketRepository->expects($this->once())
            ->method('findActiveConnections')
            ->willReturn($sockets);

        $this->socketService->expects($this->exactly(2))
            ->method('checkActive')
            ->with($this->isInstanceOf(Socket::class))
            ->willThrowException(new StatusException('Session expired'));

        $this->socketService->expects($this->exactly(2))
            ->method('disconnect')
            ->with($this->isInstanceOf(Socket::class));

        $this->deliveryService->expects($this->once())
            ->method('cleanupQueues');

        $this->socketRepository->expects($this->once())
            ->method('cleanupInactiveConnections');

        $this->socketIOService->cleanup();
    }
}
