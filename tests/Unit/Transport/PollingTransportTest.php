<?php

namespace SocketIoBundle\Tests\Unit\Transport;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Transport\PollingTransport;
use SocketIoBundle\Transport\TransportInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PollingTransportTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private SocketRepository $socketRepository;
    private DeliveryService $deliveryService;
    private Socket $socket;
    private PollingTransport $transport;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->socketRepository = $this->createMock(SocketRepository::class);
        $this->deliveryService = $this->createMock(DeliveryService::class);
        $this->socket = $this->createMock(Socket::class);
        
        $this->socket->expects($this->any())->method('getSocketId')->willReturn('socket-123');
        
        $this->transport = new PollingTransport(
            $this->entityManager,
            $this->socketRepository,
            $this->deliveryService,
            $this->socket,
            'session-123'
        );
    }

    public function test_implements_transport_interface(): void
    {
        $this->assertInstanceOf(TransportInterface::class, $this->transport);
    }

    public function test_get_session_id_returns_correct_value(): void
    {
        $this->assertSame('session-123', $this->transport->getSessionId());
    }

    public function test_set_packet_handler_stores_callable(): void
    {
        $handler = function () {
        };
        
        $this->transport->setPacketHandler($handler);
        
        // 由于 packetHandler 是私有属性，我们通过反射验证
        $reflection = new \ReflectionClass($this->transport);
        $property = $reflection->getProperty('packetHandler');
        $property->setAccessible(true);
        
        $this->assertSame($handler, $property->getValue($this->transport));
    }

    public function test_handle_request_get_method(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->any())->method('isMethod')->willReturnMap([
            ['GET', true],
            ['POST', false],
        ]);
        $request->query = new \Symfony\Component\HttpFoundation\InputBag();
        $request->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
        $request->headers->expects($this->any())->method('get')->with('Accept')->willReturn('text/plain');
        
        $socket = $this->createMock(Socket::class);
        $socket->expects($this->any())->method('getPollCount')->willReturn(1);
        $socket->expects($this->any())->method('isConnected')->willReturn(true);
        
        $this->socketRepository->expects($this->any())->method('findBySessionId')
            ->with('session-123')
            ->willReturn($socket);
        
        $response = $this->transport->handleRequest($request);
        
        $this->assertInstanceOf(Response::class, $response);
    }

    public function test_handle_request_post_method(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->any())->method('isMethod')->willReturnMap([
            ['GET', false],
            ['POST', true],
        ]);
        $request->expects($this->any())->method('getContent')->willReturn('2probe');
        $request->query = new \Symfony\Component\HttpFoundation\InputBag();
        $request->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
        $request->headers->expects($this->any())->method('get')->with('Accept')->willReturn('text/plain');
        
        // 模拟环境变量
        $_ENV['SOCKET_IO_MAX_PAYLOAD_SIZE'] = '100000';
        
        $response = $this->transport->handleRequest($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('ok', $response->getContent());
    }

    public function test_handle_request_unsupported_method(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->any())->method('isMethod')->willReturnMap([
            ['GET', false],
            ['POST', false],
        ]);
        $request->query = new \Symfony\Component\HttpFoundation\InputBag();
        $request->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
        $request->headers->expects($this->any())->method('get')->with('Accept')->willReturn('text/plain');
        
        $response = $this->transport->handleRequest($request);
        
        $this->assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
        $this->assertSame('Method not allowed', $response->getContent());
    }

    public function test_handle_request_with_jsonp(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->any())->method('isMethod')->willReturnMap([
            ['GET', false],
            ['POST', true],
        ]);
        $request->expects($this->any())->method('getContent')->willReturn('d=2probe');
        $request->query = new \Symfony\Component\HttpFoundation\InputBag(['j' => '0']);
        $request->headers = $this->createMock(\Symfony\Component\HttpFoundation\HeaderBag::class);
        $request->headers->expects($this->any())->method('get')->with('Accept')->willReturn('text/plain');
        
        $_ENV['SOCKET_IO_MAX_PAYLOAD_SIZE'] = '100000';
        
        $response = $this->transport->handleRequest($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame("___eio[0]('ok');", $response->getContent());
    }

    public function test_send_with_valid_socket(): void
    {
        $socket = $this->createMock(Socket::class);
        $socket->expects($this->any())->method('isConnected')->willReturn(true);
        
        $this->socketRepository->expects($this->any())->method('findBySessionId')
            ->with('session-123')
            ->willReturn($socket);
        
        $this->entityManager->expects($this->exactly(2))->method('persist');
        $this->entityManager->expects($this->once())->method('flush');
        
        $this->transport->send('42["message","hello"]');
    }

    public function test_send_with_disconnected_socket(): void
    {
        $socket = $this->createMock(Socket::class);
        $socket->expects($this->any())->method('isConnected')->willReturn(false);
        
        $this->socketRepository->expects($this->any())->method('findBySessionId')
            ->with('session-123')
            ->willReturn($socket);
        
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        
        $this->transport->send('42["message","hello"]');
    }

    public function test_send_with_non_existent_socket(): void
    {
        $this->socketRepository->expects($this->any())->method('findBySessionId')
            ->with('session-123')
            ->willReturn(null);
        
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        
        $this->transport->send('42["message","hello"]');
    }

    public function test_send_with_non_message_packet(): void
    {
        $socket = $this->createMock(Socket::class);
        $socket->expects($this->any())->method('isConnected')->willReturn(true);
        
        $this->socketRepository->expects($this->any())->method('findBySessionId')
            ->with('session-123')
            ->willReturn($socket);
        
        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        
        // 发送ping包（类型2）而不是message包（类型4）
        $this->transport->send('2probe');
    }

    public function test_close_with_existing_socket(): void
    {
        $socket = $this->createMock(Socket::class);
        $delivery1 = $this->createMock(\SocketIoBundle\Entity\Delivery::class);
        $delivery2 = $this->createMock(\SocketIoBundle\Entity\Delivery::class);
        
        $this->socketRepository->expects($this->any())->method('findBySessionId')
            ->with('session-123')
            ->willReturn($socket);
        
        $this->deliveryService->expects($this->any())->method('getPendingDeliveries')
            ->with($socket)
            ->willReturn([$delivery1, $delivery2]);
        
        $socket->expects($this->once())->method('setConnected')->with(false);
        
        // 每个delivery都会调用setStatus和setError
        $delivery1->expects($this->once())->method('setStatus')
            ->with(\SocketIoBundle\Enum\MessageStatus::FAILED)
            ->willReturnSelf();
        $delivery1->expects($this->once())->method('setError')
            ->with('Connection closed')
            ->willReturnSelf();
            
        $delivery2->expects($this->once())->method('setStatus')
            ->with(\SocketIoBundle\Enum\MessageStatus::FAILED)
            ->willReturnSelf();
        $delivery2->expects($this->once())->method('setError')
            ->with('Connection closed')
            ->willReturnSelf();
        
        $this->entityManager->expects($this->once())->method('flush');
        
        $this->transport->close();
    }

    public function test_close_with_non_existent_socket(): void
    {
        $this->socketRepository->expects($this->any())->method('findBySessionId')
            ->with('session-123')
            ->willReturn(null);
        
        $this->deliveryService->expects($this->never())->method('getPendingDeliveries');
        $this->entityManager->expects($this->never())->method('flush');
        
        $this->transport->close();
    }

    public function test_is_expired_returns_false_for_recent_poll(): void
    {
        // 新创建的transport应该不会过期
        $this->assertFalse($this->transport->isExpired());
    }

    public function test_is_expired_returns_true_for_old_poll(): void
    {
        // 使用反射模拟旧的轮询时间
        $reflection = new \ReflectionClass($this->transport);
        $property = $reflection->getProperty('lastPollTime');
        $property->setAccessible(true);
        $property->setValue($this->transport, microtime(true) - 50); // 50秒前
        
        $this->assertTrue($this->transport->isExpired());
    }

    public function test_constructor_sets_all_properties(): void
    {
        $reflection = new \ReflectionClass($this->transport);
        
        $sessionIdProperty = $reflection->getProperty('sessionId');
        $sessionIdProperty->setAccessible(true);
        $this->assertSame('session-123', $sessionIdProperty->getValue($this->transport));
        
        $supportsBinaryProperty = $reflection->getProperty('supportsBinary');
        $supportsBinaryProperty->setAccessible(true);
        $this->assertFalse($supportsBinaryProperty->getValue($this->transport));
        
        $jsonpProperty = $reflection->getProperty('jsonp');
        $jsonpProperty->setAccessible(true);
        $this->assertFalse($jsonpProperty->getValue($this->transport));
    }

    public function test_methods_exist_and_are_accessible(): void
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

    public function test_private_methods_exist(): void
    {
        $reflection = new \ReflectionClass($this->transport);
        
        $privateMethods = [
            'handlePoll',
            'validateAndUpdateSocket',
            'handleFirstPoll',
            'handleLongPoll',
            'tryDeliverMessages',
            'buildMessagePayload',
            'createSocketPacket',
            'isSocketStillValid',
            'handlePollTimeout',
            'handlePost',
            'handlePacket',
            'encodePacket',
            'isBinary',
            'decodePayload',
            'decodeJsonpPayload',
            'createResponse'
        ];
        
        foreach ($privateMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName), "Method {$methodName} should exist");
            $this->assertTrue($reflection->getMethod($methodName)->isPrivate(), "Method {$methodName} should be private");
        }
    }
} 