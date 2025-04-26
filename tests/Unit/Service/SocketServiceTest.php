<?php

namespace SocketIoBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Event\SocketEvent;
use SocketIoBundle\Exception\InvalidPingException;
use SocketIoBundle\Exception\InvalidTransportException;
use SocketIoBundle\Exception\PingTimeoutException;
use SocketIoBundle\Repository\{RoomRepository, SocketRepository};
use SocketIoBundle\Service\{DeliveryService, RoomService, SocketService};
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SocketServiceTest extends TestCase
{
    private SocketService|MockObject $socketService;
    private EntityManagerInterface|MockObject $em;
    private SocketRepository|MockObject $socketRepository;
    private RoomRepository|MockObject $roomRepository;
    private RoomService|MockObject $roomService;
    private DeliveryService|MockObject $deliveryService;
    private EventDispatcherInterface|MockObject $eventDispatcher;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->socketRepository = $this->createMock(SocketRepository::class);
        $this->roomRepository = $this->createMock(RoomRepository::class);
        $this->roomService = $this->createMock(RoomService::class);
        $this->deliveryService = $this->createMock(DeliveryService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->socketService = new SocketService(
            $this->em,
            $this->socketRepository,
            $this->roomRepository,
            $this->roomService,
            $this->deliveryService,
            $this->eventDispatcher
        );
    }

    public function testCreateConnection(): void
    {
        $sessionId = 'test-session-id';
        $socketId = 'test-socket-id';
        $transport = 'polling';
        $namespace = '/';

        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with($sessionId)
            ->willReturn(null);

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Socket::class));

        $this->em->expects($this->once())
            ->method('flush');

        $socket = $this->socketService->createConnection($sessionId, $socketId, $transport, $namespace);

        $this->assertInstanceOf(Socket::class, $socket);
        $this->assertEquals($sessionId, $socket->getSessionId());
        $this->assertEquals($socketId, $socket->getSocketId());
        $this->assertEquals($transport, $socket->getTransport());
        $this->assertEquals($namespace, $socket->getNamespace());
        $this->assertTrue($socket->isConnected());
    }

    public function testUpdatePing(): void
    {
        $socket = new Socket('test-session-id', 'test-socket-id');
        $this->em->expects($this->once())
            ->method('flush');

        $this->socketService->updatePing($socket);
        $this->assertNotNull($socket->getLastPingTime());
    }

    public function testDisconnect(): void
    {
        $socket = new Socket('test-session-id', 'test-socket-id');
        $socket->setConnected(true);

        $this->roomService->expects($this->once())
            ->method('leaveAllRooms')
            ->with($socket);

        $this->em->expects($this->once())
            ->method('flush');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(SocketEvent::class));

        $this->socketService->disconnect($socket);
        $this->assertFalse($socket->isConnected());
    }

    public function testBindClientId(): void
    {
        $socket = new Socket('test-session-id', 'test-socket-id');
        $clientId = 'test-client-id';

        $this->em->expects($this->once())
            ->method('flush');

        $this->socketService->bindClientId($socket, $clientId);
        $this->assertEquals($clientId, $socket->getClientId());
    }

    public function testFindByClientId(): void
    {
        $clientId = 'test-client-id';
        $expectedSocket = new Socket('test-session-id', 'test-socket-id');

        $this->socketRepository->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($expectedSocket);

        $result = $this->socketService->findByClientId($clientId);
        $this->assertEquals($expectedSocket, $result);
    }

    public function testFindActiveConnectionsByNamespace(): void
    {
        $namespace = '/test';
        $expectedSockets = [
            new Socket('test-session-id-1', 'test-socket-id-1'),
            new Socket('test-session-id-2', 'test-socket-id-2')
        ];

        $this->socketRepository->expects($this->once())
            ->method('findActiveConnectionsByNamespace')
            ->with($namespace)
            ->willReturn($expectedSockets);

        $result = $this->socketService->findActiveConnectionsByNamespace($namespace);
        $this->assertEquals($expectedSockets, $result);
    }

    public function testCheckActiveWithValidSocket(): void
    {
        $socket = new Socket('test-session-id', 'test-socket-id');
        $socket->updatePingTime();
        $socket->setLastDeliverTime(new \DateTime());

        $this->socketService->checkActive($socket);
        $this->assertTrue(true); // 如果没有抛出异常，测试通过
    }

    public function testCheckActiveWithInvalidTransport(): void
    {
        $this->expectException(InvalidTransportException::class);
        
        $socket = new Socket('test-session-id', 'test-socket-id');
        $socket->setTransport('invalid-transport');
        
        $this->socketService->checkActive($socket);
    }

    public function testCheckActiveWithInvalidPing(): void
    {
        $this->expectException(InvalidPingException::class);
        
        $socket = new Socket('test-session-id', 'test-socket-id');
        $socket->setLastPingTime(null);
        
        $this->socketService->checkActive($socket);
    }

    public function testCheckActiveWithPingTimeout(): void
    {
        $this->expectException(PingTimeoutException::class);
        
        $socket = new Socket('test-session-id', 'test-socket-id');
        $socket->setLastPingTime(new \DateTime('-31 seconds'));
        $socket->setLastDeliverTime(new \DateTime('-61 seconds'));
        
        $this->socketService->checkActive($socket);
    }

    public function testGenerateUniqueId(): void
    {
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->willReturn(null);

        $id = $this->socketService->generateUniqueId();
        
        $this->assertIsString($id);
        $this->assertEquals(20, strlen($id));
        $this->assertTrue(ctype_xdigit($id));
    }
} 