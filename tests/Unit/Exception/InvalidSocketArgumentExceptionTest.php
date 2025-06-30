<?php

namespace SocketIoBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Exception\InvalidSocketArgumentException;

class InvalidSocketArgumentExceptionTest extends TestCase
{
    public function testExceptionCanBeThrown(): void
    {
        $this->expectException(InvalidSocketArgumentException::class);
        
        throw new InvalidSocketArgumentException('Test message');
    }

    public function testExceptionExtendsInvalidArgumentException(): void
    {
        $exception = new InvalidSocketArgumentException();
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }
}