<?php

namespace SocketIoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use SocketIoBundle\Exception\StatusException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(StatusException::class)]
final class StatusExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsRuntimeException(): void
    {
        $exception = new StatusException();
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testCanBeCreatedWithoutMessage(): void
    {
        $exception = new StatusException();
        $this->assertSame('', $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testCanBeCreatedWithMessage(): void
    {
        $message = 'Test status exception message';
        $exception = new StatusException($message);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame(0, $exception->getCode());
    }

    public function testCanBeCreatedWithMessageAndCode(): void
    {
        $message = 'Test status exception with code';
        $code = 500;
        $exception = new StatusException($message, $code);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($code, $exception->getCode());
    }

    public function testCanBeCreatedWithPreviousException(): void
    {
        $previous = new \InvalidArgumentException('Previous exception');
        $message = 'Status exception with previous';
        $exception = new StatusException($message, 0, $previous);

        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testClassNamespaceAndName(): void
    {
        $reflection = new \ReflectionClass(StatusException::class);

        $this->assertSame('SocketIoBundle\Exception\StatusException', $reflection->getName());
        $this->assertSame('SocketIoBundle\Exception', $reflection->getNamespaceName());
        $this->assertSame('StatusException', $reflection->getShortName());
    }

    public function testIsNotAbstractAndNotFinal(): void
    {
        $reflection = new \ReflectionClass(StatusException::class);

        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
        $this->assertTrue($reflection->isInstantiable());
    }

    public function testExceptionHierarchy(): void
    {
        $exception = new StatusException();

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(StatusException::class, $exception);
    }

    public function testCanBeThrownAndCaught(): void
    {
        $this->expectException(StatusException::class);
        $this->expectExceptionMessage('Test throwable');

        throw new StatusException('Test throwable');
    }

    public function testThrownExceptionMaintainsStackTrace(): void
    {
        try {
            throw new StatusException('Stack trace test');
        } catch (StatusException $e) {
            $this->assertNotEmpty($e->getTrace());
            $this->assertStringContainsString('testThrownExceptionMaintainsStackTrace', $e->getTraceAsString());
        }
    }
}
