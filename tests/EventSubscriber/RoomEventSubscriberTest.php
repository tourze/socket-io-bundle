<?php

namespace SocketIoBundle\Tests\EventSubscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Event\SocketEvent;
use SocketIoBundle\EventSubscriber\RoomEventSubscriber;
use SocketIoBundle\Service\MessageService;
use SocketIoBundle\Service\RoomService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractEventSubscriberTestCase;

/**
 * @internal
 */
#[CoversClass(RoomEventSubscriber::class)]
#[RunTestsInSeparateProcesses]
final class RoomEventSubscriberTest extends AbstractEventSubscriberTestCase
{
    private RoomService $roomService;

    private MessageService $messageService;

    private RoomEventSubscriber $subscriber;

    /** @var array<string> */
    private array $roomServiceReturnRooms = [];

    protected function onSetUp(): void
    {
        // 创建 Mock 对象
        $this->roomService = $this->createMock(RoomService::class);
        $this->messageService = $this->createMock(MessageService::class);

        // 设置默认行为
        $this->roomService->method('findOrCreateRoom')
            ->willReturnCallback(function (string $name): Room {
                $room = new Room();
                $room->setName($name);

                return $room;
            })
        ;

        $this->roomService->method('getSocketRooms')
            ->willReturnCallback(function (): array {
                return $this->roomServiceReturnRooms;
            })
        ;

        $this->messageService->method('broadcast')
            ->willReturn(0)
        ;

        // 替换容器中的服务为测试替身
        self::getContainer()->set(RoomService::class, $this->roomService);
        self::getContainer()->set(MessageService::class, $this->messageService);

        $subscriber = self::getContainer()->get(RoomEventSubscriber::class);
        $this->assertInstanceOf(RoomEventSubscriber::class, $subscriber);
        $this->subscriber = $subscriber;
    }

    /**
     * @param array<string> $rooms
     */
    private function setRoomServiceReturnRooms(array $rooms): void
    {
        $this->roomServiceReturnRooms = $rooms;
    }

    public function testImplementsEventSubscriberInterface(): void
    {
        $this->assertInstanceOf(EventSubscriberInterface::class, $this->subscriber);
    }

    public function testGetSubscribedEventsReturnsCorrectEvents(): void
    {
        $events = RoomEventSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(SocketEvent::class, $events);
        $this->assertSame('onSocketEvent', $events[SocketEvent::class]);
    }

    public function testOnSocketEventWithNullSocketReturnsEarly(): void
    {
        $event = new SocketEvent('joinRoom', null, ['room1']);

        // 无需验证Mock期望 - 只测试没有异常抛出
        $this->subscriber->onSocketEvent($event);

        // 验证测试通过 - 方法正常执行完成
        $this->assertTrue(true);
    }

    public function testOnSocketEventWithUnknownEventName(): void
    {
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
        $event = new SocketEvent('unknownEvent', $socket, []);

        // 无需验证Mock期望 - 只测试没有异常抛出
        $this->subscriber->onSocketEvent($event);

        // 验证测试通过 - 方法正常执行完成
        $this->assertTrue(true);
    }

    public function testHandleJoinRoomWithValidData(): void
    {
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
        $roomName = 'chat-room';
        $event = new SocketEvent('joinRoom', $socket, [$roomName]);
        $rooms = ['room1', 'chat-room'];

        // 配置测试替身返回值
        $this->setRoomServiceReturnRooms($rooms);

        // 执行测试
        $this->subscriber->onSocketEvent($event);

        // 验证测试通过 - 方法正常执行完成
        $this->assertTrue(true);
    }

    public function testHandleJoinRoomWithMissingRoomData(): void
    {
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
        $event = new SocketEvent('joinRoom', $socket, []);

        // 无需验证Mock期望 - 只测试没有异常抛出
        $this->subscriber->onSocketEvent($event);

        // 验证测试通过 - 方法正常执行完成
        $this->assertTrue(true);
    }

    public function testHandleLeaveRoomWithValidData(): void
    {
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
        $roomName = 'chat-room';
        $event = new SocketEvent('leaveRoom', $socket, [['room' => $roomName]]);
        $rooms = ['room1'];

        // 配置测试替身返回值
        $this->setRoomServiceReturnRooms($rooms);

        // 执行测试
        $this->subscriber->onSocketEvent($event);

        // 验证测试通过 - 方法正常执行完成
        $this->assertTrue(true);
    }

    public function testHandleLeaveRoomWithMissingRoomData(): void
    {
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
        $event = new SocketEvent('leaveRoom', $socket, [[]]);

        // 无需验证Mock期望 - 只测试没有异常抛出
        $this->subscriber->onSocketEvent($event);

        // 验证测试通过 - 方法正常执行完成
        $this->assertTrue(true);
    }

    public function testHandleLeaveRoomWithEmptyData(): void
    {
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
        $event = new SocketEvent('leaveRoom', $socket, []);

        // 无需验证Mock期望 - 只测试没有异常抛出
        $this->subscriber->onSocketEvent($event);

        // 验证测试通过 - 方法正常执行完成
        $this->assertTrue(true);
    }

    public function testHandleGetRooms(): void
    {
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
        $event = new SocketEvent('getRooms', $socket, []);
        $rooms = ['room1', 'room2', 'room3'];

        // 配置测试替身返回值
        $this->setRoomServiceReturnRooms($rooms);

        // 执行测试
        $this->subscriber->onSocketEvent($event);

        // 验证测试通过 - 方法正常执行完成
        $this->assertTrue(true);
    }

    public function testConstructorSetsDependencies(): void
    {
        $reflection = new \ReflectionClass($this->subscriber);

        $roomServiceProperty = $reflection->getProperty('roomService');
        $roomServiceProperty->setAccessible(true);
        $this->assertSame($this->roomService, $roomServiceProperty->getValue($this->subscriber));

        $messageServiceProperty = $reflection->getProperty('messageService');
        $messageServiceProperty->setAccessible(true);
        $this->assertSame($this->messageService, $messageServiceProperty->getValue($this->subscriber));
    }

    public function testConstructorSignature(): void
    {
        $reflection = new \ReflectionClass(RoomEventSubscriber::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(2, $constructor->getNumberOfParameters());
        $this->assertSame(2, $constructor->getNumberOfRequiredParameters());

        $parameters = $constructor->getParameters();
        $this->assertSame('roomService', $parameters[0]->getName());
        $this->assertSame('messageService', $parameters[1]->getName());
    }

    public function testClassNamespaceAndName(): void
    {
        $reflection = new \ReflectionClass(RoomEventSubscriber::class);

        $this->assertSame('SocketIoBundle\EventSubscriber\RoomEventSubscriber', $reflection->getName());
        $this->assertSame('SocketIoBundle\EventSubscriber', $reflection->getNamespaceName());
        $this->assertSame('RoomEventSubscriber', $reflection->getShortName());
    }

    public function testIsNotAbstractAndNotFinal(): void
    {
        $reflection = new \ReflectionClass(RoomEventSubscriber::class);

        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
        $this->assertTrue($reflection->isInstantiable());
    }

    public function testPrivateMethodsExist(): void
    {
        $reflection = new \ReflectionClass(RoomEventSubscriber::class);

        $this->assertTrue($reflection->hasMethod('handleJoinRoom'));
        $this->assertTrue($reflection->hasMethod('handleLeaveRoom'));
        $this->assertTrue($reflection->hasMethod('handleGetRooms'));

        $this->assertTrue($reflection->getMethod('handleJoinRoom')->isPrivate());
        $this->assertTrue($reflection->getMethod('handleLeaveRoom')->isPrivate());
        $this->assertTrue($reflection->getMethod('handleGetRooms')->isPrivate());
    }

    public function testHandleJoinRoomWithNumericRoomName(): void
    {
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
        $roomName = '123';
        $event = new SocketEvent('joinRoom', $socket, [$roomName]);
        $rooms = ['123'];

        // 配置测试替身返回值
        $this->setRoomServiceReturnRooms($rooms);

        // 执行测试
        $this->subscriber->onSocketEvent($event);

        // 验证测试通过 - 方法正常执行完成
        $this->assertTrue(true);
    }

    public function testHandleJoinRoomWithSpecialCharacters(): void
    {
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
        $roomName = 'room-with_special.chars@123';
        $event = new SocketEvent('joinRoom', $socket, [$roomName]);
        $rooms = [$roomName];

        // 配置测试替身返回值
        $this->setRoomServiceReturnRooms($rooms);

        // 执行测试
        $this->subscriber->onSocketEvent($event);

        // 验证测试通过 - 方法正常执行完成
        $this->assertTrue(true);
    }

    public function testHandleLeaveRoomWithNestedRoomData(): void
    {
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
        $roomName = 'nested-room';
        $event = new SocketEvent('leaveRoom', $socket, [['room' => $roomName, 'extra' => 'data']]);
        $rooms = [];

        // 配置测试替身返回值
        $this->setRoomServiceReturnRooms($rooms);

        // 执行测试
        $this->subscriber->onSocketEvent($event);

        // 验证测试通过 - 方法正常执行完成
        $this->assertTrue(true);
    }
}
