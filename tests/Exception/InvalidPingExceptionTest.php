<?php

namespace SocketIoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use SocketIoBundle\Exception\InvalidPingException;
use SocketIoBundle\Exception\StatusException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidPingException::class)]
final class InvalidPingExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsStatusException(): void
    {
        $sessionId = 'test-session-123';
        $exception = new InvalidPingException($sessionId);

        // InvalidPingException now extends RuntimeException directly
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testConstructorCreatesFormattedMessage(): void
    {
        $sessionId = 'socket-ping-test-456';
        $exception = new InvalidPingException($sessionId);

        $expectedMessage = 'Invalid last ping for socket: socket-ping-test-456';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testConstructorWithEmptySessionId(): void
    {
        $sessionId = '';
        $exception = new InvalidPingException($sessionId);

        $expectedMessage = 'Invalid last ping for socket: ';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testConstructorWithSpecialCharacters(): void
    {
        $sessionId = 'socket-!@#$%^&*()_+=[]{}|;:,.<>?';
        $exception = new InvalidPingException($sessionId);

        $this->assertStringContainsString('socket-!@#$%^&*()_+=[]{}|;:,.<>?', $exception->getMessage());
        $this->assertStringContainsString('Invalid last ping for socket:', $exception->getMessage());
    }

    public function testConstructorWithUnicodeCharacters(): void
    {
        $sessionId = 'socket-测试-🔥-αβγ';
        $exception = new InvalidPingException($sessionId);

        $this->assertStringContainsString('socket-测试-🔥-αβγ', $exception->getMessage());
        $this->assertStringContainsString('Invalid last ping for socket:', $exception->getMessage());
    }

    public function testConstructorWithNumericSessionId(): void
    {
        $sessionId = '12345678901234567890';
        $exception = new InvalidPingException($sessionId);

        $expectedMessage = 'Invalid last ping for socket: 12345678901234567890';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testConstructorWithWhitespaceSessionId(): void
    {
        $sessionId = '  session with spaces  ';
        $exception = new InvalidPingException($sessionId);

        $expectedMessage = 'Invalid last ping for socket:   session with spaces  ';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testConstructorWithNewlineInSessionId(): void
    {
        $sessionId = "session\nwith\nnewlines";
        $exception = new InvalidPingException($sessionId);

        $this->assertStringContainsString("session\nwith\nnewlines", $exception->getMessage());
    }

    public function testConstructorSignatureValidation(): void
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
        $this->assertSame('string', (string) $parameterType);
    }

    public function testExceptionHierarchy(): void
    {
        $sessionId = 'hierarchy-test';
        $exception = new InvalidPingException($sessionId);

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        // InvalidPingException now extends RuntimeException directly
        $this->assertInstanceOf(InvalidPingException::class, $exception);
    }

    public function testCanBeThrownAndCaught(): void
    {
        $sessionId = 'throwable-test';

        $this->expectException(InvalidPingException::class);
        $this->expectExceptionMessage('Invalid last ping for socket: throwable-test');

        throw new InvalidPingException($sessionId);
    }

    public function testMessagePatternConsistency(): void
    {
        $testCases = [
            'simple',
            'complex-session-id-123',
            'socket_with_underscores',
            'socket-with-dashes',
            'MixedCase123',
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

    public function testClassNamespaceAndName(): void
    {
        $reflection = new \ReflectionClass(InvalidPingException::class);

        $this->assertSame('SocketIoBundle\Exception\InvalidPingException', $reflection->getName());
        $this->assertSame('SocketIoBundle\Exception', $reflection->getNamespaceName());
        $this->assertSame('InvalidPingException', $reflection->getShortName());
    }

    public function testIsNotAbstractAndNotFinal(): void
    {
        $reflection = new \ReflectionClass(InvalidPingException::class);

        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
        $this->assertTrue($reflection->isInstantiable());
    }

    public function testExceptionHasNoCodeByDefault(): void
    {
        $sessionId = 'code-test';
        $exception = new InvalidPingException($sessionId);

        $this->assertSame(0, $exception->getCode());
    }

    public function testExceptionHasNoPreviousByDefault(): void
    {
        $sessionId = 'previous-test';
        $exception = new InvalidPingException($sessionId);

        $this->assertNull($exception->getPrevious());
    }

    public function testMultipleInstancesHaveDifferentObjects(): void
    {
        $exception1 = new InvalidPingException('session-1');
        $exception2 = new InvalidPingException('session-2');

        $this->assertNotSame($exception1, $exception2);
        $this->assertNotSame($exception1->getMessage(), $exception2->getMessage());
    }

    public function testExceptionWithVeryLongSessionId(): void
    {
        $sessionId = str_repeat('a', 1000);
        $exception = new InvalidPingException($sessionId);

        $this->assertStringContainsString($sessionId, $exception->getMessage());
        $this->assertStringStartsWith('Invalid last ping for socket: ', $exception->getMessage());
    }
}
