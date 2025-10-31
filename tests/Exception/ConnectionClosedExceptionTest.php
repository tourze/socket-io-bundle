<?php

namespace SocketIoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use SocketIoBundle\Exception\ConnectionClosedException;
use SocketIoBundle\Exception\SocketException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(ConnectionClosedException::class)]
final class ConnectionClosedExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(ConnectionClosedException::class);
        $this->expectExceptionMessage('Connection closed');
        $this->expectExceptionCode(400);

        throw new ConnectionClosedException();
    }

    public function testExceptionExtendsSocketException(): void
    {
        $exception = new ConnectionClosedException();
        $this->assertInstanceOf(SocketException::class, $exception);
    }
}
