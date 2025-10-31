<?php

namespace SocketIoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use SocketIoBundle\Exception\InvalidPayloadException;
use SocketIoBundle\Exception\InvalidSocketArgumentException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidPayloadException::class)]
final class InvalidPayloadExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Invalid payload');

        throw new InvalidPayloadException();
    }

    public function testExceptionExtendsInvalidSocketArgumentException(): void
    {
        $exception = new InvalidPayloadException();
        $this->assertInstanceOf(InvalidSocketArgumentException::class, $exception);
    }
}
