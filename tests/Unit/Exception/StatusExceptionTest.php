<?php

namespace SocketIoBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Exception\StatusException;

class StatusExceptionTest extends TestCase
{
    public function test_extends_runtime_exception(): void
    {
        $exception = new StatusException();
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function test_can_be_created_without_message(): void
    {
        $exception = new StatusException();
        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function test_can_be_created_with_message(): void
    {
        $message = 'Test status exception message';
        $exception = new StatusException($message);
        
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function test_can_be_created_with_message_and_code(): void
    {
        $message = 'Test status exception with code';
        $code = 500;
        $exception = new StatusException($message, $code);
        
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    public function test_can_be_created_with_previous_exception(): void
    {
        $previous = new \InvalidArgumentException('Previous exception');
        $message = 'Status exception with previous';
        $exception = new StatusException($message, 0, $previous);
        
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function test_class_namespace_and_name(): void
    {
        $reflection = new \ReflectionClass(StatusException::class);
        
        $this->assertSame('SocketIoBundle\Exception\StatusException', $reflection->getName());
        $this->assertSame('SocketIoBundle\Exception', $reflection->getNamespaceName());
        $this->assertSame('StatusException', $reflection->getShortName());
    }

    public function test_is_not_abstract_and_not_final(): void
    {
        $reflection = new \ReflectionClass(StatusException::class);
        
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
        $this->assertTrue($reflection->isInstantiable());
    }

    public function test_exception_hierarchy(): void
    {
        $exception = new StatusException();
        
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(StatusException::class, $exception);
    }

    public function test_can_be_thrown_and_caught(): void
    {
        $this->expectException(StatusException::class);
        $this->expectExceptionMessage('Test throwable');
        
        throw new StatusException('Test throwable');
    }

    public function test_thrown_exception_maintains_stack_trace(): void
    {
        try {
            throw new StatusException('Stack trace test');
        } catch (StatusException $e) {
            $this->assertNotEmpty($e->getTrace());
            $this->assertStringContainsString('test_thrown_exception_maintains_stack_trace', $e->getTraceAsString());
        }
    }
} 