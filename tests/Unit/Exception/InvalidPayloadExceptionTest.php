<?php

namespace SocketIoBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Exception\InvalidPayloadException;
use SocketIoBundle\Exception\InvalidSocketArgumentException;

class InvalidPayloadExceptionTest extends TestCase
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