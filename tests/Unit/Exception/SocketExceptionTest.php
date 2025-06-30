<?php

namespace SocketIoBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Exception\SocketException;

class SocketExceptionTest extends TestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(SocketException::class);
        
        throw new SocketException('Test message');
    }

    public function testExceptionExtendsRuntimeException(): void
    {
        $exception = new SocketException();
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}