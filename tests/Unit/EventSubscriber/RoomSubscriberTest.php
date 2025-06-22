<?php

namespace SocketIoBundle\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Event\SocketEvent;
use SocketIoBundle\EventSubscriber\RoomSubscriber;
use SocketIoBundle\Service\MessageService;
use SocketIoBundle\Service\RoomService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RoomSubscriberTest extends TestCase
{
    private RoomService $roomService;
    private MessageService $messageService;
    private RoomSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->roomService = $this->createMock(RoomService::class);
        $this->messageService = $this->createMock(MessageService::class);
        $this->subscriber = new RoomSubscriber($this->roomService, $this->messageService);
    }

    public function test_implements_event_subscriber_interface(): void
    {
        $this->assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    public function test_get_subscribed_events_returns_correct_events(): void
    {
        $events = RoomSubscriber::getSubscribedEvents();
        
        $this->assertArrayHasKey(SocketEvent::class, $events);
        $this->assertSame('onSocketEvent', $events[SocketEvent::class]);
    }

    public function test_on_socket_event_with_null_socket_returns_early(): void
    {
        $event = new SocketEvent('joinRoom', null, ['room1']);
        
        $this->roomService->expects($this->never())->method('joinRoom');
        $this->messageService->expects($this->never())->method('sendToSocket');
        
        $this->subscriber->onSocketEvent($event);
    }

    public function test_on_socket_event_with_unknown_event_name(): void
    {
        $socket = $this->createMock(Socket::class);
        $event = new SocketEvent('unknownEvent', $socket, []);
        
        $this->roomService->expects($this->never())->method('joinRoom');
        $this->roomService->expects($this->never())->method('leaveRoom');
        $this->roomService->expects($this->never())->method('getSocketRooms');
        
        $this->subscriber->onSocketEvent($event);
    }

    public function test_handle_join_room_with_valid_data(): void
    {
        $socket = $this->createMock(Socket::class);
        $roomName = 'chat-room';
        $event = new SocketEvent('joinRoom', $socket, [$roomName]);
        $rooms = ['room1', 'chat-room'];
        
        $this->roomService->expects($this->once())
            ->method('joinRoom')
            ->with($socket, $roomName);
        
        $this->roomService->expects($this->once())
            ->method('getSocketRooms')
            ->with($socket)
            ->willReturn($rooms);
        
        $this->messageService->expects($this->once())
            ->method('sendToSocket')
            ->with($socket, 'roomList', [$rooms]);
        
        $this->subscriber->onSocketEvent($event);
    }

    public function test_handle_join_room_with_missing_room_data(): void
    {
        $socket = $this->createMock(Socket::class);
        $event = new SocketEvent('joinRoom', $socket, []);
        
        $this->roomService->expects($this->never())->method('joinRoom');
        $this->messageService->expects($this->never())->method('sendToSocket');
        
        $this->subscriber->onSocketEvent($event);
    }

    public function test_handle_leave_room_with_valid_data(): void
    {
        $socket = $this->createMock(Socket::class);
        $roomName = 'chat-room';
        $event = new SocketEvent('leaveRoom', $socket, [['room' => $roomName]]);
        $rooms = ['room1'];
        
        $this->roomService->expects($this->once())
            ->method('leaveRoom')
            ->with($socket, $roomName);
        
        $this->roomService->expects($this->once())
            ->method('getSocketRooms')
            ->with($socket)
            ->willReturn($rooms);
        
        $this->messageService->expects($this->once())
            ->method('sendToSocket')
            ->with($socket, 'roomList', [$rooms]);
        
        $this->subscriber->onSocketEvent($event);
    }

    public function test_handle_leave_room_with_missing_room_data(): void
    {
        $socket = $this->createMock(Socket::class);
        $event = new SocketEvent('leaveRoom', $socket, [[]]);
        
        $this->roomService->expects($this->never())->method('leaveRoom');
        $this->messageService->expects($this->never())->method('sendToSocket');
        
        $this->subscriber->onSocketEvent($event);
    }

    public function test_handle_leave_room_with_empty_data(): void
    {
        $socket = $this->createMock(Socket::class);
        $event = new SocketEvent('leaveRoom', $socket, []);
        
        $this->roomService->expects($this->never())->method('leaveRoom');
        $this->messageService->expects($this->never())->method('sendToSocket');
        
        $this->subscriber->onSocketEvent($event);
    }

    public function test_handle_get_rooms(): void
    {
        $socket = $this->createMock(Socket::class);
        $event = new SocketEvent('getRooms', $socket, []);
        $rooms = ['room1', 'room2', 'room3'];
        
        $this->roomService->expects($this->once())
            ->method('getSocketRooms')
            ->with($socket)
            ->willReturn($rooms);
        
        $this->messageService->expects($this->once())
            ->method('sendToSocket')
            ->with($socket, 'roomList', [$rooms]);
        
        $this->subscriber->onSocketEvent($event);
    }

    public function test_constructor_sets_dependencies(): void
    {
        $reflection = new \ReflectionClass($this->subscriber);
        
        $roomServiceProperty = $reflection->getProperty('roomService');
        $roomServiceProperty->setAccessible(true);
        $this->assertSame($this->roomService, $roomServiceProperty->getValue($this->subscriber));
        
        $messageServiceProperty = $reflection->getProperty('messageService');
        $messageServiceProperty->setAccessible(true);
        $this->assertSame($this->messageService, $messageServiceProperty->getValue($this->subscriber));
    }

    public function test_constructor_signature(): void
    {
        $reflection = new \ReflectionClass(RoomSubscriber::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertSame(2, $constructor->getNumberOfParameters());
        $this->assertSame(2, $constructor->getNumberOfRequiredParameters());
        
        $parameters = $constructor->getParameters();
        $this->assertSame('roomService', $parameters[0]->getName());
        $this->assertSame('messageService', $parameters[1]->getName());
    }

    public function test_class_namespace_and_name(): void
    {
        $reflection = new \ReflectionClass(RoomSubscriber::class);
        
        $this->assertSame('SocketIoBundle\EventSubscriber\RoomSubscriber', $reflection->getName());
        $this->assertSame('SocketIoBundle\EventSubscriber', $reflection->getNamespaceName());
        $this->assertSame('RoomSubscriber', $reflection->getShortName());
    }

    public function test_is_not_abstract_and_not_final(): void
    {
        $reflection = new \ReflectionClass(RoomSubscriber::class);
        
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
        $this->assertTrue($reflection->isInstantiable());
    }

    public function test_private_methods_exist(): void
    {
        $reflection = new \ReflectionClass(RoomSubscriber::class);
        
        $this->assertTrue($reflection->hasMethod('handleJoinRoom'));
        $this->assertTrue($reflection->hasMethod('handleLeaveRoom'));
        $this->assertTrue($reflection->hasMethod('handleGetRooms'));
        
        $this->assertTrue($reflection->getMethod('handleJoinRoom')->isPrivate());
        $this->assertTrue($reflection->getMethod('handleLeaveRoom')->isPrivate());
        $this->assertTrue($reflection->getMethod('handleGetRooms')->isPrivate());
    }

    public function test_handle_join_room_with_numeric_room_name(): void
    {
        $socket = $this->createMock(Socket::class);
        $roomName = '123';
        $event = new SocketEvent('joinRoom', $socket, [$roomName]);
        $rooms = ['123'];
        
        $this->roomService->expects($this->once())
            ->method('joinRoom')
            ->with($socket, $roomName);
        
        $this->roomService->expects($this->once())
            ->method('getSocketRooms')
            ->with($socket)
            ->willReturn($rooms);
        
        $this->messageService->expects($this->once())
            ->method('sendToSocket')
            ->with($socket, 'roomList', [$rooms]);
        
        $this->subscriber->onSocketEvent($event);
    }

    public function test_handle_join_room_with_special_characters(): void
    {
        $socket = $this->createMock(Socket::class);
        $roomName = 'room-with_special.chars@123';
        $event = new SocketEvent('joinRoom', $socket, [$roomName]);
        $rooms = [$roomName];
        
        $this->roomService->expects($this->once())
            ->method('joinRoom')
            ->with($socket, $roomName);
        
        $this->roomService->expects($this->once())
            ->method('getSocketRooms')
            ->with($socket)
            ->willReturn($rooms);
        
        $this->messageService->expects($this->once())
            ->method('sendToSocket')
            ->with($socket, 'roomList', [$rooms]);
        
        $this->subscriber->onSocketEvent($event);
    }

    public function test_handle_leave_room_with_nested_room_data(): void
    {
        $socket = $this->createMock(Socket::class);
        $roomName = 'nested-room';
        $event = new SocketEvent('leaveRoom', $socket, [['room' => $roomName, 'extra' => 'data']]);
        $rooms = [];
        
        $this->roomService->expects($this->once())
            ->method('leaveRoom')
            ->with($socket, $roomName);
        
        $this->roomService->expects($this->once())
            ->method('getSocketRooms')
            ->with($socket)
            ->willReturn($rooms);
        
        $this->messageService->expects($this->once())
            ->method('sendToSocket')
            ->with($socket, 'roomList', [$rooms]);
        
        $this->subscriber->onSocketEvent($event);
    }
} 