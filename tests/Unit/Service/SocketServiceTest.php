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
use SocketIoBundle\Repository\{SocketRepository};
use SocketIoBundle\Service\{DeliveryService, RoomService, SocketService};
use SocketIoBundle\Transport\PollingTransport;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SocketServiceTest extends TestCase
{
    private SocketService $socketService;
    private EntityManagerInterface|MockObject $em;
    private SocketRepository|MockObject $socketRepository;
    private RoomService|MockObject $roomService;
    private DeliveryService|MockObject $deliveryService;
    private EventDispatcherInterface|MockObject $eventDispatcher;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->socketRepository = $this->createMock(SocketRepository::class);
        $this->roomService = $this->createMock(RoomService::class);
        $this->deliveryService = $this->createMock(DeliveryService::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->socketService = new SocketService(
            $this->em,
            $this->socketRepository,
            $this->roomService,
            $this->deliveryService,
            $this->eventDispatcher
        );
    }

    public function testCreateConnection_New(): void
    {
        $sessionId = 'test-session-id';
        $socketId = 'test-socket-id';
        $transport = 'polling';
        $namespace = '/test';
        
        // 模拟查询数据库未找到现有Socket
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with($sessionId)
            ->willReturn(null);
            
        // 期望持久化和提交到数据库
        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function($socket) use ($sessionId, $socketId, $transport, $namespace) {
                return $socket instanceof Socket
                    && $socket->getSessionId() === $sessionId
                    && $socket->getSocketId() === $socketId
                    && $socket->getTransport() === $transport
                    && $socket->getNamespace() === $namespace
                    && $socket->isConnected() === true
                    && $socket->getLastPingTime() instanceof \DateTimeImmutable;
            }));
            
        $this->em->expects($this->once())
            ->method('flush');
            
        // 调用方法并验证结果
        $socket = $this->socketService->createConnection($sessionId, $socketId, $transport, $namespace);
        
        $this->assertInstanceOf(Socket::class, $socket);
        $this->assertEquals($sessionId, $socket->getSessionId());
        $this->assertEquals($socketId, $socket->getSocketId());
        $this->assertEquals($transport, $socket->getTransport());
        $this->assertEquals($namespace, $socket->getNamespace());
        $this->assertTrue($socket->isConnected());
    }

    public function testCreateConnection_Existing(): void
    {
        $sessionId = 'test-session-id';
        $socketId = 'test-socket-id';
        $transport = 'polling';
        $namespace = '/test';
        
        // 创建一个已有的Socket
        $existingSocket = new Socket($sessionId, $socketId);
        $existingSocket->setConnected(false)
                      ->setTransport('old-transport')
                      ->setNamespace('/old-namespace');
                      
        // 模拟查询数据库找到现有Socket
        $this->socketRepository->expects($this->once())
            ->method('findBySessionId')
            ->with($sessionId)
            ->willReturn($existingSocket);
            
        // 期望更新和提交到数据库
        $this->em->expects($this->once())
            ->method('persist')
            ->with($existingSocket);
            
        $this->em->expects($this->once())
            ->method('flush');
            
        // 调用方法并验证结果
        $socket = $this->socketService->createConnection($sessionId, $socketId, $transport, $namespace);
        
        $this->assertSame($existingSocket, $socket);
        $this->assertEquals($sessionId, $socket->getSessionId());
        $this->assertEquals($transport, $socket->getTransport());
        $this->assertEquals($namespace, $socket->getNamespace());
        $this->assertTrue($socket->isConnected());
    }

    public function testUpdatePing(): void
    {
        $socket = new Socket('test-session', 'test-socket');
        $oldPingTime = $socket->getLastPingTime();
        
        // 等待一小段时间确保时间戳不同
        usleep(1000);
        
        // 期望提交到数据库
        $this->em->expects($this->once())
            ->method('flush');
            
        // 调用方法
        $this->socketService->updatePing($socket);
        
        // 验证结果
        $this->assertNotSame($oldPingTime, $socket->getLastPingTime());
    }

    public function testDisconnect(): void
    {
        $socket = new Socket('test-session', 'test-socket');
        $socket->setConnected(true);
        
        // 期望首先离开所有房间
        $this->roomService->expects($this->once())
            ->method('leaveAllRooms')
            ->with($socket);
            
        // 期望提交到数据库
        $this->em->expects($this->once())
            ->method('flush');
            
        // 期望分发断开连接事件
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function($event) use ($socket) {
                return $event instanceof SocketEvent
                    && $event->getName() === 'socket.disconnect'
                    && $event->getSocket() === $socket;
            }));
            
        // 调用方法
        $this->socketService->disconnect($socket);
        
        // 验证结果
        $this->assertFalse($socket->isConnected());
    }

    public function testCleanupInactiveConnections(): void
    {
        // 期望调用底层仓库方法
        $this->socketRepository->expects($this->once())
            ->method('cleanupInactiveConnections');
            
        // 调用方法
        $this->socketService->cleanupInactiveConnections();
    }

    public function testBindClientId(): void
    {
        $socket = new Socket('test-session', 'test-socket');
        $clientId = 'user-123';
        
        // 期望提交到数据库
        $this->em->expects($this->once())
            ->method('flush');
            
        // 调用方法
        $this->socketService->bindClientId($socket, $clientId);
        
        // 验证结果
        $this->assertEquals($clientId, $socket->getClientId());
    }

    public function testFindByClientId(): void
    {
        $clientId = 'user-123';
        $socket = new Socket('test-session', 'test-socket');
        
        // 期望调用底层仓库方法
        $this->socketRepository->expects($this->once())
            ->method('findByClientId')
            ->with($clientId)
            ->willReturn($socket);
            
        // 调用方法并验证结果
        $result = $this->socketService->findByClientId($clientId);
        $this->assertSame($socket, $result);
    }

    public function testFindActiveConnectionsByNamespace(): void
    {
        $namespace = '/test';
        $socket1 = new Socket('session1', 'socket1');
        $socket2 = new Socket('session2', 'socket2');
        $activeSockets = [$socket1, $socket2];
        
        // 期望调用底层仓库方法
        $this->socketRepository->expects($this->once())
            ->method('findActiveConnectionsByNamespace')
            ->with($namespace)
            ->willReturn($activeSockets);
            
        // 调用方法并验证结果
        $result = $this->socketService->findActiveConnectionsByNamespace($namespace);
        $this->assertSame($activeSockets, $result);
    }

    public function testGenerateUniqueId(): void
    {
        // 调用方法获取两个ID
        $id1 = $this->socketService->generateUniqueId();
        $id2 = $this->socketService->generateUniqueId();
        
        // 验证生成的ID不为空且不相同
        $this->assertNotEmpty($id1);
        $this->assertNotEmpty($id2);
        $this->assertNotEquals($id1, $id2);
    }

    public function testGetTransport_Polling(): void
    {
        $socket = new Socket('test-session', 'test-socket');
        $socket->setTransport('polling');
        
        // 调用方法并验证结果
        $transport = $this->socketService->getTransport($socket);
        
        $this->assertInstanceOf(PollingTransport::class, $transport);
    }

    public function testGetTransport_UnsupportedType(): void
    {
        $socket = new Socket('test-session', 'test-socket');
        $socket->setTransport('unsupported');
        
        // 调用方法并验证结果
        $transport = $this->socketService->getTransport($socket);
        
        $this->assertNull($transport);
    }

    public function testSendPing(): void
    {
        $sessionId = 'test-session';
        $socket = new Socket($sessionId, 'test-socket');
        
        // 创建模拟传输对象
        $transport = $this->createMock(PollingTransport::class);
        
        // 创建一个能返回模拟传输对象的SocketService的部分模拟对象
        $socketService = $this->getMockBuilder(SocketService::class)
            ->setConstructorArgs([
                $this->em,
                $this->socketRepository,
                $this->roomService,
                $this->deliveryService,
                $this->eventDispatcher
            ])
            ->onlyMethods(['getTransport'])
            ->getMock();
            
        $socketService->expects($this->once())
            ->method('getTransport')
            ->with($socket)
            ->willReturn($transport);
            
        // 期望传输对象发送ping包
        $transport->expects($this->once())
            ->method('send')
            ->with($this->callback(function($data) {
                return strpos($data, '2') === 0; // Engine.IO ping packet
            }));
            
        // 调用方法
        $socketService->sendPing($socket);
    }

    public function testCheckActive_ValidSocket(): void
    {
        $socket = new Socket('test-session', 'test-socket');
        $socket->updatePingTime();
        
        // 创建模拟传输对象
        $transport = $this->createMock(PollingTransport::class);
        $transport->expects($this->once())
            ->method('isExpired')
            ->willReturn(false);
            
        // 创建一个能返回模拟传输对象的SocketService的部分模拟对象
        $socketService = $this->getMockBuilder(SocketService::class)
            ->setConstructorArgs([
                $this->em,
                $this->socketRepository,
                $this->roomService,
                $this->deliveryService,
                $this->eventDispatcher
            ])
            ->onlyMethods(['getTransport'])
            ->getMock();
            
        $socketService->expects($this->once())
            ->method('getTransport')
            ->with($socket)
            ->willReturn($transport);
            
        // 调用方法 - 不应抛出异常
        $socketService->checkActive($socket);
    }

    public function testCheckActive_InvalidTransport(): void
    {
        $sessionId = 'test-session';
        $socket = new Socket($sessionId, 'test-socket');
        
        // 创建一个返回null的SocketService的部分模拟对象
        $socketService = $this->getMockBuilder(SocketService::class)
            ->setConstructorArgs([
                $this->em,
                $this->socketRepository,
                $this->roomService,
                $this->deliveryService,
                $this->eventDispatcher
            ])
            ->onlyMethods(['getTransport'])
            ->getMock();
            
        $socketService->expects($this->once())
            ->method('getTransport')
            ->with($socket)
            ->willReturn(null);
            
        // 期望抛出InvalidTransportException异常
        $this->expectException(InvalidTransportException::class);
        $this->expectExceptionMessage($sessionId);
        
        // 调用方法
        $socketService->checkActive($socket);
    }

    public function testCheckActive_ExpiredTransport(): void
    {
        $sessionId = 'test-session';
        $socket = new Socket($sessionId, 'test-socket');
        
        // 创建模拟传输对象
        $transport = $this->createMock(PollingTransport::class);
        $transport->expects($this->once())
            ->method('isExpired')
            ->willReturn(true);
            
        // 创建一个能返回模拟传输对象的SocketService的部分模拟对象
        $socketService = $this->getMockBuilder(SocketService::class)
            ->setConstructorArgs([
                $this->em,
                $this->socketRepository,
                $this->roomService,
                $this->deliveryService,
                $this->eventDispatcher
            ])
            ->onlyMethods(['getTransport'])
            ->getMock();
            
        $socketService->expects($this->once())
            ->method('getTransport')
            ->with($socket)
            ->willReturn($transport);
            
        // 期望抛出InvalidTransportException异常
        $this->expectException(InvalidTransportException::class);
        $this->expectExceptionMessage($sessionId);
        
        // 调用方法
        $socketService->checkActive($socket);
    }

    public function testCheckActive_InvalidPingTime(): void
    {
        $sessionId = 'test-session';
        $socket = new Socket($sessionId, 'test-socket');
        $socket->setLastPingTime(null); // 设置为无效的ping时间
        
        // 创建模拟传输对象
        $transport = $this->createMock(PollingTransport::class);
        $transport->expects($this->once())
            ->method('isExpired')
            ->willReturn(false);
            
        // 创建一个能返回模拟传输对象的SocketService的部分模拟对象
        $socketService = $this->getMockBuilder(SocketService::class)
            ->setConstructorArgs([
                $this->em,
                $this->socketRepository,
                $this->roomService,
                $this->deliveryService,
                $this->eventDispatcher
            ])
            ->onlyMethods(['getTransport'])
            ->getMock();
            
        $socketService->expects($this->once())
            ->method('getTransport')
            ->with($socket)
            ->willReturn($transport);
            
        // 期望抛出InvalidPingException异常
        $this->expectException(InvalidPingException::class);
        $this->expectExceptionMessage($sessionId);
        
        // 调用方法
        $socketService->checkActive($socket);
    }

    public function testCheckActive_PingTimeout(): void
    {
        $sessionId = 'test-session';
        $socket = new Socket($sessionId, 'test-socket');
        
        // 设置为很久以前的ping时间
        $oldDateTime = new \DateTimeImmutable('-60 seconds'); // 60秒前
        $socket->setLastPingTime($oldDateTime);
        
        // 设置最后投递时间也为很久以前
        $oldDeliverTime = new \DateTimeImmutable('-120 seconds'); // 120秒前
        $socket->setLastDeliverTime($oldDeliverTime);
        
        // 创建模拟传输对象
        $transport = $this->createMock(PollingTransport::class);
        $transport->expects($this->once())
            ->method('isExpired')
            ->willReturn(false);
            
        // 创建一个能返回模拟传输对象的SocketService的部分模拟对象
        $socketService = $this->getMockBuilder(SocketService::class)
            ->setConstructorArgs([
                $this->em,
                $this->socketRepository,
                $this->roomService,
                $this->deliveryService,
                $this->eventDispatcher
            ])
            ->onlyMethods(['getTransport'])
            ->getMock();
            
        $socketService->expects($this->once())
            ->method('getTransport')
            ->with($socket)
            ->willReturn($transport);
            
        // 期望抛出PingTimeoutException异常
        $this->expectException(PingTimeoutException::class);
        
        // 调用方法，设置超时时间为5秒，确保超时
        $socketService->checkActive($socket, 5);
    }
}
