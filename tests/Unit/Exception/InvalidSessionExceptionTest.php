<?php

namespace SocketIoBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Exception\InvalidSessionException;
use SocketIoBundle\Exception\SocketException;

class InvalidSessionExceptionTest extends TestCase
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