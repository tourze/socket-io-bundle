<?php

namespace SocketIoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use SocketIoBundle\Exception\InvalidTransportException;
use SocketIoBundle\Exception\StatusException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidTransportException::class)]
final class InvalidTransportExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsStatusException(): void
    {
        $sessionId = 'test-session-123';
        $exception = new InvalidTransportException($sessionId);

        // InvalidTransportException now extends RuntimeException directly
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testConstructorCreatesFormattedMessage(): void
    {
        $sessionId = 'socket-transport-test-789';
        $exception = new InvalidTransportException($sessionId);

        $expectedMessage = 'Invalid transport for socket: socket-transport-test-789';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testConstructorWithEmptySessionId(): void
    {
        $sessionId = '';
        $exception = new InvalidTransportException($sessionId);

        $expectedMessage = 'Invalid transport for socket: ';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testConstructorWithSpecialCharacters(): void
    {
        $sessionId = 'transport-!@#$%^&*()_+=[]{}|;:,.<>?';
        $exception = new InvalidTransportException($sessionId);

        $this->assertStringContainsString('transport-!@#$%^&*()_+=[]{}|;:,.<>?', $exception->getMessage());
        $this->assertStringContainsString('Invalid transport for socket:', $exception->getMessage());
    }

    public function testConstructorWithUnicodeCharacters(): void
    {
        $sessionId = 'transport-测试-🚀-αβγ';
        $exception = new InvalidTransportException($sessionId);

        $this->assertStringContainsString('transport-测试-🚀-αβγ', $exception->getMessage());
        $this->assertStringContainsString('Invalid transport for socket:', $exception->getMessage());
    }

    public function testConstructorWithNumericSessionId(): void
    {
        $sessionId = '98765432109876543210';
        $exception = new InvalidTransportException($sessionId);

        $expectedMessage = 'Invalid transport for socket: 98765432109876543210';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testConstructorWithWhitespaceSessionId(): void
    {
        $sessionId = '  transport with spaces  ';
        $exception = new InvalidTransportException($sessionId);

        $expectedMessage = 'Invalid transport for socket:   transport with spaces  ';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testConstructorWithNewlineInSessionId(): void
    {
        $sessionId = "transport\nwith\nnewlines";
        $exception = new InvalidTransportException($sessionId);

        $this->assertStringContainsString("transport\nwith\nnewlines", $exception->getMessage());
    }

    public function testConstructorSignatureValidation(): void
    {
        $reflection = new \ReflectionClass(InvalidTransportException::class);
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
        $exception = new InvalidTransportException($sessionId);

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        // InvalidTransportException now extends RuntimeException directly
        $this->assertInstanceOf(InvalidTransportException::class, $exception);
    }

    public function testCanBeThrownAndCaught(): void
    {
        $sessionId = 'throwable-test';

        $this->expectException(InvalidTransportException::class);
        $this->expectExceptionMessage('Invalid transport for socket: throwable-test');

        throw new InvalidTransportException($sessionId);
    }

    public function testMessagePatternConsistency(): void
    {
        $testCases = [
            'simple',
            'complex-transport-id-456',
            'transport_with_underscores',
            'transport-with-dashes',
            'MixedCaseTransport789',
        ];

        foreach ($testCases as $sessionId) {
            $exception = new InvalidTransportException($sessionId);
            $message = $exception->getMessage();

            $this->assertStringStartsWith('Invalid transport for socket: ', $message);
            $this->assertStringEndsWith($sessionId, $message);
        }

        // 单独测试空字符串情况
        $emptyException = new InvalidTransportException('');
        $this->assertSame('Invalid transport for socket: ', $emptyException->getMessage());
    }

    public function testClassNamespaceAndName(): void
    {
        $reflection = new \ReflectionClass(InvalidTransportException::class);

        $this->assertSame('SocketIoBundle\Exception\InvalidTransportException', $reflection->getName());
        $this->assertSame('SocketIoBundle\Exception', $reflection->getNamespaceName());
        $this->assertSame('InvalidTransportException', $reflection->getShortName());
    }

    public function testIsNotAbstractAndNotFinal(): void
    {
        $reflection = new \ReflectionClass(InvalidTransportException::class);

        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
        $this->assertTrue($reflection->isInstantiable());
    }

    public function testExceptionHasNoCodeByDefault(): void
    {
        $sessionId = 'code-test';
        $exception = new InvalidTransportException($sessionId);

        $this->assertSame(0, $exception->getCode());
    }

    public function testExceptionHasNoPreviousByDefault(): void
    {
        $sessionId = 'previous-test';
        $exception = new InvalidTransportException($sessionId);

        $this->assertNull($exception->getPrevious());
    }

    public function testMultipleInstancesHaveDifferentObjects(): void
    {
        $exception1 = new InvalidTransportException('transport-1');
        $exception2 = new InvalidTransportException('transport-2');

        $this->assertNotSame($exception1, $exception2);
        $this->assertNotSame($exception1->getMessage(), $exception2->getMessage());
    }

    public function testExceptionWithVeryLongSessionId(): void
    {
        $sessionId = str_repeat('t', 1000);
        $exception = new InvalidTransportException($sessionId);

        $this->assertStringContainsString($sessionId, $exception->getMessage());
        $this->assertStringStartsWith('Invalid transport for socket: ', $exception->getMessage());
    }

    public function testMessageDifferenceFromPingException(): void
    {
        $sessionId = 'compare-test';
        $transportException = new InvalidTransportException($sessionId);

        $this->assertStringContainsString('Invalid transport', $transportException->getMessage());
        $this->assertStringNotContainsString('Invalid last ping', $transportException->getMessage());
        $this->assertStringNotContainsString('ping', $transportException->getMessage());
    }

    public function testMessagePrefixConsistency(): void
    {
        $testSessions = ['test1', 'test2', 'test3'];

        foreach ($testSessions as $sessionId) {
            $exception = new InvalidTransportException($sessionId);
            $message = $exception->getMessage();

            $this->assertStringStartsWith('Invalid transport for socket: ', $message);
            $this->assertSame(30 + strlen($sessionId), strlen($message)); // "Invalid transport for socket: " has 30 characters
        }
    }

    public function testConstructorBehaviorWithTabAndCarriageReturn(): void
    {
        $sessionId = "session\twith\ttabs\rand\rcarriage\rreturns";
        $exception = new InvalidTransportException($sessionId);

        $this->assertStringContainsString("\t", $exception->getMessage());
        $this->assertStringContainsString("\r", $exception->getMessage());
        $this->assertStringContainsString($sessionId, $exception->getMessage());
    }

    public function testSessionIdPreservationInMessage(): void
    {
        $originalSessionId = 'original-session-id-123';
        $exception = new InvalidTransportException($originalSessionId);

        // 确保sessionId在消息中完全保持原样
        $this->assertStringEndsWith($originalSessionId, $exception->getMessage());
        $this->assertStringContainsString($originalSessionId, $exception->getMessage());
    }
}
