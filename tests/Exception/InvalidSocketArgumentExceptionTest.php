<?php

namespace SocketIoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use SocketIoBundle\Exception\InvalidSocketArgumentException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(InvalidSocketArgumentException::class)]
final class InvalidSocketArgumentExceptionTest extends AbstractExceptionTestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidSocketArgumentException::class);

        $concreteException = new class('Test message') extends InvalidSocketArgumentException {};
        throw $concreteException;
    }

    public function testExceptionExtendsInvalidArgumentException(): void
    {
        $exception = new class extends InvalidSocketArgumentException {};
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }
}
