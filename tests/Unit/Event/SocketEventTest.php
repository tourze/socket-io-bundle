<?php

namespace SocketIoBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Event\SocketEvent;
use Symfony\Contracts\EventDispatcher\Event;

class SocketEventTest extends TestCase
{
    public function test_extends_event(): void
    {
        $name = 'test-event';
        $socket = null;
        $data = [];
        
        $event = new SocketEvent($name, $socket, $data);
        
        $this->assertInstanceOf(Event::class, $event);
    }

    public function test_constructor_with_all_parameters(): void
    {
        $name = 'connect';
        $socket = $this->createMock(Socket::class);
        $data = ['key' => 'value', 'number' => 123];
        
        $event = new SocketEvent($name, $socket, $data);
        
        $this->assertSame($name, $event->getName());
        $this->assertSame($socket, $event->getSocket());
        $this->assertSame($data, $event->getData());
    }

    public function test_constructor_with_null_socket(): void
    {
        $name = 'disconnect';
        $socket = null;
        $data = ['reason' => 'client disconnect'];
        
        $event = new SocketEvent($name, $socket, $data);
        
        $this->assertSame($name, $event->getName());
        $this->assertNull($event->getSocket());
        $this->assertSame($data, $event->getData());
    }

    public function test_constructor_with_default_empty_data(): void
    {
        $name = 'heartbeat';
        $socket = $this->createMock(Socket::class);
        
        $event = new SocketEvent($name, $socket);
        
        $this->assertSame($name, $event->getName());
        $this->assertSame($socket, $event->getSocket());
        $this->assertSame([], $event->getData());
    }

    public function test_get_namespace_with_socket_having_namespace(): void
    {
        $name = 'message';
        $socket = $this->createMock(Socket::class);
        $socket->method('getNamespace')->willReturn('/chat');
        
        $event = new SocketEvent($name, $socket);
        
        $this->assertSame('/chat', $event->getNamespace());
    }

    public function test_get_namespace_with_socket_having_empty_namespace(): void
    {
        $name = 'message';
        $socket = $this->createMock(Socket::class);
        $socket->method('getNamespace')->willReturn('');
        
        $event = new SocketEvent($name, $socket);
        
        $this->assertSame('', $event->getNamespace());
    }

    public function test_get_namespace_with_null_socket(): void
    {
        $name = 'message';
        $socket = null;
        
        $event = new SocketEvent($name, $socket);
        
        $this->assertSame('/', $event->getNamespace());
    }

    public function test_constructor_with_complex_data(): void
    {
        $name = 'complexEvent';
        $socket = $this->createMock(Socket::class);
        $data = [
            'user' => ['id' => 123, 'name' => 'John'],
            'message' => 'Hello World',
            'timestamp' => time(),
            'metadata' => ['ip' => '192.168.1.1', 'userAgent' => 'TestAgent'],
            'nested' => [
                'deep' => [
                    'value' => 'test'
                ]
            ]
        ];
        
        $event = new SocketEvent($name, $socket, $data);
        
        $this->assertSame($data, $event->getData());
        $this->assertSame('John', $event->getData()['user']['name']);
        $this->assertSame('test', $event->getData()['nested']['deep']['value']);
    }

    public function test_constructor_with_empty_string_name(): void
    {
        $name = '';
        $socket = $this->createMock(Socket::class);
        
        $event = new SocketEvent($name, $socket);
        
        $this->assertSame('', $event->getName());
    }

    public function test_constructor_with_special_characters_in_name(): void
    {
        $name = 'event-name_with.special@chars#123';
        $socket = $this->createMock(Socket::class);
        
        $event = new SocketEvent($name, $socket);
        
        $this->assertSame($name, $event->getName());
    }

    public function test_readonly_properties(): void
    {
        $name = 'test-readonly';
        $socket = $this->createMock(Socket::class);
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

    public function test_constructor_signature(): void
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

    public function test_class_namespace_and_name(): void
    {
        $reflection = new \ReflectionClass(SocketEvent::class);
        
        $this->assertSame('SocketIoBundle\Event\SocketEvent', $reflection->getName());
        $this->assertSame('SocketIoBundle\Event', $reflection->getNamespaceName());
        $this->assertSame('SocketEvent', $reflection->getShortName());
    }

    public function test_is_not_abstract_and_not_final(): void
    {
        $reflection = new \ReflectionClass(SocketEvent::class);
        
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
        $this->assertTrue($reflection->isInstantiable());
    }

    public function test_all_methods_are_public(): void
    {
        $reflection = new \ReflectionClass(SocketEvent::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        $methodNames = array_map(fn($method) => $method->getName(), $methods);
        
        $this->assertContains('getName', $methodNames);
        $this->assertContains('getSocket', $methodNames);
        $this->assertContains('getData', $methodNames);
        $this->assertContains('getNamespace', $methodNames);
    }

    public function test_event_immutability(): void
    {
        $name = 'immutable-test';
        $socket = $this->createMock(Socket::class);
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