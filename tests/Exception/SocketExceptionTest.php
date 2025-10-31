<?php

namespace SocketIoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use SocketIoBundle\Exception\SocketException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(SocketException::class)]
final class SocketExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(SocketException::class);

        $concreteException = new class('Test message') extends SocketException {};
        throw $concreteException;
    }

    public function testExceptionExtendsRuntimeException(): void
    {
        $exception = new class extends SocketException {};
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
