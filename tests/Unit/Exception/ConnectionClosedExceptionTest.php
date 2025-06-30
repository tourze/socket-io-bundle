<?php

namespace SocketIoBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Exception\ConnectionClosedException;
use SocketIoBundle\Exception\SocketException;

class ConnectionClosedExceptionTest extends TestCase
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