<?php

namespace SocketIoBundle\Tests\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Event\SocketEvent;
use Tourze\PHPUnitSymfonyUnitTest\AbstractEventTestCase;

/**
 * @internal
 */
#[CoversClass(SocketEvent::class)]
final class SocketEventTest extends AbstractEventTestCase
{
    public function testConstructorWithAllParameters(): void
    {
        $name = 'connect';
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
        $data = ['key' => 'value', 'number' => 123];

        $event = new SocketEvent($name, $socket, $data);

        $this->assertSame($name, $event->getName());
        $this->assertSame($socket, $event->getSocket());
        $this->assertSame($data, $event->getData());
    }

    public function testConstructorWithNullSocket(): void
    {
        $name = 'disconnect';
        $socket = null;
        $data = ['reason' => 'client disconnect'];

        $event = new SocketEvent($name, $socket, $data);

        $this->assertSame($name, $event->getName());
        $this->assertNull($event->getSocket());
        $this->assertSame($data, $event->getData());
    }

    public function testConstructorWithDefaultEmptyData(): void
    {
        $name = 'heartbeat';
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

        $event = new SocketEvent($name, $socket);

        $this->assertSame($name, $event->getName());
        $this->assertSame($socket, $event->getSocket());
        $this->assertSame([], $event->getData());
    }

    public function testGetNamespaceWithSocketHavingNamespace(): void
    {
        $name = 'message';
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('test-session');
                $this->setSocketId('test-socket');
            }

            public function getNamespace(): string
            {
                return '/chat';
            }
        };

        $event = new SocketEvent($name, $socket);

        $this->assertSame('/chat', $event->getNamespace());
    }

    public function testGetNamespaceWithSocketHavingEmptyNamespace(): void
    {
        $name = 'message';
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('test-session');
                $this->setSocketId('test-socket');
            }

            public function getNamespace(): string
            {
                return '';
            }
        };

        $event = new SocketEvent($name, $socket);

        $this->assertSame('', $event->getNamespace());
    }

    public function testGetNamespaceWithNullSocket(): void
    {
        $name = 'message';
        $socket = null;

        $event = new SocketEvent($name, $socket);

        $this->assertSame('/', $event->getNamespace());
    }

    public function testConstructorWithComplexData(): void
    {
        $name = 'complexEvent';
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
        $data = [
            'user' => ['id' => 123, 'name' => 'John'],
            'message' => 'Hello World',
            'timestamp' => time(),
            'metadata' => ['ip' => '192.168.1.1', 'userAgent' => 'TestAgent'],
            'nested' => [
                'deep' => [
                    'value' => 'test',
                ],
            ],
        ];

        $event = new SocketEvent($name, $socket, $data);

        $this->assertSame($data, $event->getData());
        $this->assertSame('John', $event->getData()['user']['name']);
        $this->assertSame('test', $event->getData()['nested']['deep']['value']);
    }

    public function testConstructorWithEmptyStringName(): void
    {
        $name = '';
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

        $event = new SocketEvent($name, $socket);

        $this->assertSame('', $event->getName());
    }

    public function testConstructorWithSpecialCharactersInName(): void
    {
        $name = 'event-name_with.special@chars#123';
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

        $event = new SocketEvent($name, $socket);

        $this->assertSame($name, $event->getName());
    }

    public function testReadonlyProperties(): void
    {
        $name = 'test-readonly';
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
        $data = ['test' => 'data'];

        $event = new SocketEvent($name, $socket, $data);

        // 验证属性是否为readonly（通过反射）
        $reflection = new \ReflectionClass($event);

        $nameProperty = $reflection->getProperty('name');
        $this->assertTrue($nameProperty->isReadOnly());

        $socketProperty = $reflection->getProperty('socket');
        $this->assertTrue($socketProperty->isReadOnly());

        $dataProperty = $reflection->getProperty('data');
        $this->assertTrue($dataProperty->isReadOnly());
    }

    public function testConstructorSignature(): void
    {
        $reflection = new \ReflectionClass(SocketEvent::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(3, $constructor->getNumberOfParameters());
        $this->assertSame(2, $constructor->getNumberOfRequiredParameters());

        $parameters = $constructor->getParameters();
        $this->assertSame('name', $parameters[0]->getName());
        $this->assertSame('socket', $parameters[1]->getName());
        $this->assertSame('data', $parameters[2]->getName());

        // 检查默认值
        $this->assertTrue($parameters[2]->isDefaultValueAvailable());
        $this->assertSame([], $parameters[2]->getDefaultValue());
    }

    public function testClassNamespaceAndName(): void
    {
        $reflection = new \ReflectionClass(SocketEvent::class);

        $this->assertSame('SocketIoBundle\Event\SocketEvent', $reflection->getName());
        $this->assertSame('SocketIoBundle\Event', $reflection->getNamespaceName());
        $this->assertSame('SocketEvent', $reflection->getShortName());
    }

    public function testIsNotAbstractAndNotFinal(): void
    {
        $reflection = new \ReflectionClass(SocketEvent::class);

        $this->assertFalse($reflection->isAbstract());
        $this->assertTrue($reflection->isFinal());
        $this->assertTrue($reflection->isInstantiable());
    }

    public function testAllMethodsArePublic(): void
    {
        $reflection = new \ReflectionClass(SocketEvent::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $methodNames = array_map(fn ($method) => $method->getName(), $methods);

        $this->assertContains('getName', $methodNames);
        $this->assertContains('getSocket', $methodNames);
        $this->assertContains('getData', $methodNames);
        $this->assertContains('getNamespace', $methodNames);
    }

    public function testEventImmutability(): void
    {
        $name = 'immutable-test';
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
        $originalData = ['original' => 'data'];

        $event = new SocketEvent($name, $socket, $originalData);

        // 获取数据并修改
        $retrievedData = $event->getData();
        $retrievedData['modified'] = 'value';

        // 验证原始事件数据未被修改
        $this->assertSame($originalData, $event->getData());
        $this->assertArrayNotHasKey('modified', $event->getData());
    }
}
