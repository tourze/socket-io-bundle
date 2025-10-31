<?php

namespace SocketIoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use SocketIoBundle\Exception\InvalidSessionException;
use SocketIoBundle\Exception\SocketException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidSessionException::class)]
final class InvalidSessionExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidSessionException::class);
        $this->expectExceptionMessage('Invalid session');
        $this->expectExceptionCode(400);

        throw new InvalidSessionException();
    }

    public function testExceptionExtendsSocketException(): void
    {
        $exception = new InvalidSessionException();
        $this->assertInstanceOf(SocketException::class, $exception);
    }
}
