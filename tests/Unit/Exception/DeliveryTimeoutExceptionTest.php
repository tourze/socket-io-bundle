<?php

namespace SocketIoBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Exception\DeliveryTimeoutException;
use SocketIoBundle\Exception\StatusException;

class DeliveryTimeoutExceptionTest extends TestCase
{
    public function test_extends_status_exception(): void
    {
        $sessionId = 'test-session-123';
        $timeout = 30;
        $lastDeliverTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:01:00');
        
        $exception = new DeliveryTimeoutException($sessionId, $timeout, $lastDeliverTime, $nowTime);
        
        $this->assertInstanceOf(StatusException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function test_constructor_creates_formatted_message(): void
    {
        $sessionId = 'socket-delivery-456';
        $timeout = 120;
        $lastDeliverTime = new \DateTime('2023-06-20 09:15:30');
        $nowTime = new \DateTime('2023-06-20 09:17:30');
        
        $exception = new DeliveryTimeoutException($sessionId, $timeout, $lastDeliverTime, $nowTime);
        
        $expectedMessage = "Delivery timeout for socket: socket-delivery-456, timeout: 120s, lastDeliverTime: 2023-06-20 09:15:30, nowTime: 2023-06-20 09:17:30";
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function test_constructor_with_different_datetime_formats(): void
    {
        $sessionId = 'delivery-socket';
        $timeout = 75;
        $lastDeliverTime = \DateTime::createFromFormat('Y-m-d H:i:s', '2023-11-30 18:45:12');
        $nowTime = \DateTime::createFromFormat('Y-m-d H:i:s', '2023-11-30 18:46:30');
        
        $exception = new DeliveryTimeoutException($sessionId, $timeout, $lastDeliverTime, $nowTime);
        
        $this->assertStringContainsString('delivery-socket', $exception->getMessage());
        $this->assertStringContainsString('timeout: 75s', $exception->getMessage());
        $this->assertStringContainsString('2023-11-30 18:45:12', $exception->getMessage());
        $this->assertStringContainsString('2023-11-30 18:46:30', $exception->getMessage());
    }

    public function test_constructor_with_datetimeimmutable(): void
    {
        $sessionId = 'immutable-delivery-test';
        $timeout = 25;
        $lastDeliverTime = new \DateTimeImmutable('2023-03-15 14:20:00');
        $nowTime = new \DateTimeImmutable('2023-03-15 14:20:45');
        
        $exception = new DeliveryTimeoutException($sessionId, $timeout, $lastDeliverTime, $nowTime);
        
        $this->assertStringContainsString('immutable-delivery-test', $exception->getMessage());
        $this->assertStringContainsString('timeout: 25s', $exception->getMessage());
        $this->assertStringContainsString('2023-03-15 14:20:00', $exception->getMessage());
        $this->assertStringContainsString('2023-03-15 14:20:45', $exception->getMessage());
    }

    public function test_constructor_with_edge_case_values(): void
    {
        $sessionId = '';
        $timeout = 0;
        $lastDeliverTime = new \DateTime('1970-01-01 00:00:00');
        $nowTime = new \DateTime('1970-01-01 00:00:02');
        
        $exception = new DeliveryTimeoutException($sessionId, $timeout, $lastDeliverTime, $nowTime);
        
        $this->assertStringContainsString('timeout: 0s', $exception->getMessage());
        $this->assertStringContainsString('1970-01-01 00:00:00', $exception->getMessage());
        $this->assertStringContainsString('1970-01-01 00:00:02', $exception->getMessage());
    }

    public function test_constructor_with_negative_timeout(): void
    {
        $sessionId = 'negative-delivery-timeout';
        $timeout = -5;
        $lastDeliverTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:03');
        
        $exception = new DeliveryTimeoutException($sessionId, $timeout, $lastDeliverTime, $nowTime);
        
        $this->assertStringContainsString('timeout: -5s', $exception->getMessage());
    }

    public function test_constructor_with_large_timeout_value(): void
    {
        $sessionId = 'large-delivery-timeout';
        $timeout = 86400; // 1 day in seconds
        $lastDeliverTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:01');
        
        $exception = new DeliveryTimeoutException($sessionId, $timeout, $lastDeliverTime, $nowTime);
        
        $this->assertStringContainsString('timeout: 86400s', $exception->getMessage());
    }

    public function test_constructor_with_special_characters_in_session_id(): void
    {
        $sessionId = 'delivery-socket-<>?:"|{}_+';
        $timeout = 40;
        $lastDeliverTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:40');
        
        $exception = new DeliveryTimeoutException($sessionId, $timeout, $lastDeliverTime, $nowTime);
        
        $this->assertStringContainsString('delivery-socket-<>?:"|{}_+', $exception->getMessage());
    }

    public function test_constructor_signature_validation(): void
    {
        $reflection = new \ReflectionClass(DeliveryTimeoutException::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertSame(4, $constructor->getNumberOfParameters());
        $this->assertSame(4, $constructor->getNumberOfRequiredParameters());
        
        $parameters = $constructor->getParameters();
        $this->assertSame('sessionId', $parameters[0]->getName());
        $this->assertSame('timeout', $parameters[1]->getName());
        $this->assertSame('lastDeliverTime', $parameters[2]->getName());
        $this->assertSame('nowTime', $parameters[3]->getName());
    }

    public function test_exception_hierarchy(): void
    {
        $sessionId = 'hierarchy-delivery-test';
        $timeout = 30;
        $lastDeliverTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:30');
        
        $exception = new DeliveryTimeoutException($sessionId, $timeout, $lastDeliverTime, $nowTime);
        
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(StatusException::class, $exception);
        $this->assertInstanceOf(DeliveryTimeoutException::class, $exception);
    }

    public function test_can_be_thrown_and_caught(): void
    {
        $sessionId = 'throwable-delivery-test';
        $timeout = 30;
        $lastDeliverTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:30');
        
        $this->expectException(DeliveryTimeoutException::class);
        $this->expectExceptionMessage('Delivery timeout for socket: throwable-delivery-test');
        
        throw new DeliveryTimeoutException($sessionId, $timeout, $lastDeliverTime, $nowTime);
    }

    public function test_message_difference_from_ping_timeout(): void
    {
        $sessionId = 'compare-test';
        $timeout = 30;
        $lastTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:30');
        
        $deliveryException = new DeliveryTimeoutException($sessionId, $timeout, $lastTime, $nowTime);
        
        $this->assertStringContainsString('Delivery timeout', $deliveryException->getMessage());
        $this->assertStringContainsString('lastDeliverTime', $deliveryException->getMessage());
        $this->assertStringNotContainsString('Ping timeout', $deliveryException->getMessage());
        $this->assertStringNotContainsString('lastPingTime', $deliveryException->getMessage());
    }

    public function test_different_datetime_formatting_consistency(): void
    {
        $sessionId = 'format-test';
        $timeout = 30;
        $lastDeliverTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:30');
        
        $exception = new DeliveryTimeoutException($sessionId, $timeout, $lastDeliverTime, $nowTime);
        $message = $exception->getMessage();
        
        // 检查日期格式是否为 Y-m-d H:i:s 格式
        $this->assertMatchesRegularExpression('/lastDeliverTime: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $message);
        $this->assertMatchesRegularExpression('/nowTime: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $message);
    }
} 