<?php

namespace SocketIoBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Exception\InvalidPingException;
use SocketIoBundle\Exception\StatusException;

class InvalidPingExceptionTest extends TestCase
{
    public function test_extends_status_exception(): void
    {
        $sessionId = 'test-session-123';
        $exception = new InvalidPingException($sessionId);
        
        $this->assertInstanceOf(StatusException::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function test_constructor_creates_formatted_message(): void
    {
        $sessionId = 'socket-ping-test-456';
        $exception = new InvalidPingException($sessionId);
        
        $expectedMessage = "Invalid last ping for socket: socket-ping-test-456";
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function test_constructor_with_empty_session_id(): void
    {
        $sessionId = '';
        $exception = new InvalidPingException($sessionId);
        
        $expectedMessage = "Invalid last ping for socket: ";
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function test_constructor_with_special_characters(): void
    {
        $sessionId = 'socket-!@#$%^&*()_+=[]{}|;:,.<>?';
        $exception = new InvalidPingException($sessionId);
        
        $this->assertStringContainsString('socket-!@#$%^&*()_+=[]{}|;:,.<>?', $exception->getMessage());
        $this->assertStringContainsString('Invalid last ping for socket:', $exception->getMessage());
    }

    public function test_constructor_with_unicode_characters(): void
    {
        $sessionId = 'socket-测试-🔥-αβγ';
        $exception = new InvalidPingException($sessionId);
        
        $this->assertStringContainsString('socket-测试-🔥-αβγ', $exception->getMessage());
        $this->assertStringContainsString('Invalid last ping for socket:', $exception->getMessage());
    }

    public function test_constructor_with_numeric_session_id(): void
    {
        $sessionId = '12345678901234567890';
        $exception = new InvalidPingException($sessionId);
        
        $expectedMessage = "Invalid last ping for socket: 12345678901234567890";
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function test_constructor_with_whitespace_session_id(): void
    {
        $sessionId = '  session with spaces  ';
        $exception = new InvalidPingException($sessionId);
        
        $expectedMessage = "Invalid last ping for socket:   session with spaces  ";
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function test_constructor_with_newline_in_session_id(): void
    {
        $sessionId = "session\nwith\nnewlines";
        $exception = new InvalidPingException($sessionId);
        
        $this->assertStringContainsString("session\nwith\nnewlines", $exception->getMessage());
    }

    public function test_constructor_signature_validation(): void
    {
        $reflection = new \ReflectionClass(InvalidPingException::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        $this->assertSame(1, $constructor->getNumberOfParameters());
        $this->assertSame(1, $constructor->getNumberOfRequiredParameters());
        
        $parameters = $constructor->getParameters();
        $this->assertSame('sessionId', $parameters[0]->getName());
        
        // 检查参数类型
        $parameterType = $parameters[0]->getType();
        $this->assertNotNull($parameterType);
        $this->assertSame('string', $parameterType->getName());
    }

    public function test_exception_hierarchy(): void
    {
        $sessionId = 'hierarchy-test';
        $exception = new InvalidPingException($sessionId);
        
        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(StatusException::class, $exception);
        $this->assertInstanceOf(InvalidPingException::class, $exception);
    }

    public function test_can_be_thrown_and_caught(): void
    {
        $sessionId = 'throwable-test';
        
        $this->expectException(InvalidPingException::class);
        $this->expectExceptionMessage('Invalid last ping for socket: throwable-test');
        
        throw new InvalidPingException($sessionId);
    }

    public function test_message_pattern_consistency(): void
    {
        $testCases = [
            'simple',
            'complex-session-id-123',
            'socket_with_underscores',
            'socket-with-dashes',
            'MixedCase123'
        ];
        
        foreach ($testCases as $sessionId) {
            $exception = new InvalidPingException($sessionId);
            $message = $exception->getMessage();
            
            $this->assertStringStartsWith('Invalid last ping for socket: ', $message);
            $this->assertStringEndsWith($sessionId, $message);
        }
        
        // 单独测试空字符串情况
        $emptyException = new InvalidPingException('');
        $this->assertSame('Invalid last ping for socket: ', $emptyException->getMessage());
    }

    public function test_class_namespace_and_name(): void
    {
        $reflection = new \ReflectionClass(InvalidPingException::class);
        
        $this->assertSame('SocketIoBundle\Exception\InvalidPingException', $reflection->getName());
        $this->assertSame('SocketIoBundle\Exception', $reflection->getNamespaceName());
        $this->assertSame('InvalidPingException', $reflection->getShortName());
    }

    public function test_is_not_abstract_and_not_final(): void
    {
        $reflection = new \ReflectionClass(InvalidPingException::class);
        
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
        $this->assertTrue($reflection->isInstantiable());
    }

    public function test_exception_has_no_code_by_default(): void
    {
        $sessionId = 'code-test';
        $exception = new InvalidPingException($sessionId);
        
        $this->assertSame(0, $exception->getCode());
    }

    public function test_exception_has_no_previous_by_default(): void
    {
        $sessionId = 'previous-test';
        $exception = new InvalidPingException($sessionId);
        
        $this->assertNull($exception->getPrevious());
    }

    public function test_multiple_instances_have_different_objects(): void
    {
        $exception1 = new InvalidPingException('session-1');
        $exception2 = new InvalidPingException('session-2');
        
        $this->assertNotSame($exception1, $exception2);
        $this->assertNotSame($exception1->getMessage(), $exception2->getMessage());
    }

    public function test_exception_with_very_long_session_id(): void
    {
        $sessionId = str_repeat('a', 1000);
        $exception = new InvalidPingException($sessionId);
        
        $this->assertStringContainsString($sessionId, $exception->getMessage());
        $this->assertStringStartsWith('Invalid last ping for socket: ', $exception->getMessage());
    }
} 