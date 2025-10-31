<?php

namespace SocketIoBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use SocketIoBundle\Exception\PingTimeoutException;
use SocketIoBundle\Exception\StatusException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(PingTimeoutException::class)]
final class PingTimeoutExceptionTest extends AbstractExceptionTestCase
{
    public function testExtendsStatusException(): void
    {
        $sessionId = 'test-session-123';
        $timeout = 30;
        $lastPingTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:01:00');

        $exception = new PingTimeoutException($sessionId, $timeout, $lastPingTime, $nowTime);

        // PingTimeoutException now extends RuntimeException directly
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testConstructorCreatesFormattedMessage(): void
    {
        $sessionId = 'socket-abc-123';
        $timeout = 60;
        $lastPingTime = new \DateTime('2023-05-15 14:30:25');
        $nowTime = new \DateTime('2023-05-15 14:32:25');

        $exception = new PingTimeoutException($sessionId, $timeout, $lastPingTime, $nowTime);

        $expectedMessage = 'Ping timeout for socket: socket-abc-123, timeout: 60s, lastPingTime: 2023-05-15 14:30:25, nowTime: 2023-05-15 14:32:25';
        $this->assertSame($expectedMessage, $exception->getMessage());
    }

    public function testConstructorWithDifferentDatetimeFormats(): void
    {
        $sessionId = 'test-socket';
        $timeout = 45;
        $lastPingTime = \DateTime::createFromFormat('Y-m-d H:i:s', '2023-12-25 23:59:59');
        $nowTime = \DateTime::createFromFormat('Y-m-d H:i:s', '2023-12-26 00:01:00');

        $this->assertInstanceOf(\DateTimeInterface::class, $lastPingTime);
        $this->assertInstanceOf(\DateTimeInterface::class, $nowTime);

        $exception = new PingTimeoutException($sessionId, $timeout, $lastPingTime, $nowTime);

        $this->assertStringContainsString('test-socket', $exception->getMessage());
        $this->assertStringContainsString('timeout: 45s', $exception->getMessage());
        $this->assertStringContainsString('2023-12-25 23:59:59', $exception->getMessage());
        $this->assertStringContainsString('2023-12-26 00:01:00', $exception->getMessage());
    }

    public function testConstructorWithDatetimeimmutable(): void
    {
        $sessionId = 'immutable-test';
        $timeout = 15;
        $lastPingTime = new \DateTimeImmutable('2023-01-01 12:00:00');
        $nowTime = new \DateTimeImmutable('2023-01-01 12:00:30');

        $exception = new PingTimeoutException($sessionId, $timeout, $lastPingTime, $nowTime);

        $this->assertStringContainsString('immutable-test', $exception->getMessage());
        $this->assertStringContainsString('timeout: 15s', $exception->getMessage());
        $this->assertStringContainsString('2023-01-01 12:00:00', $exception->getMessage());
        $this->assertStringContainsString('2023-01-01 12:00:30', $exception->getMessage());
    }

    public function testConstructorWithEdgeCaseValues(): void
    {
        $sessionId = '';
        $timeout = 0;
        $lastPingTime = new \DateTime('1970-01-01 00:00:00');
        $nowTime = new \DateTime('1970-01-01 00:00:01');

        $exception = new PingTimeoutException($sessionId, $timeout, $lastPingTime, $nowTime);

        $this->assertStringContainsString('timeout: 0s', $exception->getMessage());
        $this->assertStringContainsString('1970-01-01 00:00:00', $exception->getMessage());
        $this->assertStringContainsString('1970-01-01 00:00:01', $exception->getMessage());
    }

    public function testConstructorWithNegativeTimeout(): void
    {
        $sessionId = 'negative-timeout-test';
        $timeout = -10;
        $lastPingTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:05');

        $exception = new PingTimeoutException($sessionId, $timeout, $lastPingTime, $nowTime);

        $this->assertStringContainsString('timeout: -10s', $exception->getMessage());
    }

    public function testConstructorWithLargeTimeoutValue(): void
    {
        $sessionId = 'large-timeout-test';
        $timeout = 999999;
        $lastPingTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:01');

        $exception = new PingTimeoutException($sessionId, $timeout, $lastPingTime, $nowTime);

        $this->assertStringContainsString('timeout: 999999s', $exception->getMessage());
    }

    public function testConstructorWithSpecialCharactersInSessionId(): void
    {
        $sessionId = 'test-socket-!@#$%^&*()';
        $timeout = 30;
        $lastPingTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:30');

        $exception = new PingTimeoutException($sessionId, $timeout, $lastPingTime, $nowTime);

        $this->assertStringContainsString('test-socket-!@#$%^&*()', $exception->getMessage());
    }

    public function testConstructorSignatureValidation(): void
    {
        $reflection = new \ReflectionClass(PingTimeoutException::class);
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor);
        $this->assertSame(4, $constructor->getNumberOfParameters());
        $this->assertSame(4, $constructor->getNumberOfRequiredParameters());

        $parameters = $constructor->getParameters();
        $this->assertSame('sessionId', $parameters[0]->getName());
        $this->assertSame('timeout', $parameters[1]->getName());
        $this->assertSame('lastPingTime', $parameters[2]->getName());
        $this->assertSame('nowTime', $parameters[3]->getName());
    }

    public function testExceptionHierarchy(): void
    {
        $sessionId = 'hierarchy-test';
        $timeout = 30;
        $lastPingTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:30');

        $exception = new PingTimeoutException($sessionId, $timeout, $lastPingTime, $nowTime);

        $this->assertInstanceOf(\Exception::class, $exception);
        $this->assertInstanceOf(\Throwable::class, $exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
        // PingTimeoutException now extends RuntimeException directly
        $this->assertInstanceOf(PingTimeoutException::class, $exception);
    }

    public function testCanBeThrownAndCaught(): void
    {
        $sessionId = 'throwable-test';
        $timeout = 30;
        $lastPingTime = new \DateTime('2023-01-01 10:00:00');
        $nowTime = new \DateTime('2023-01-01 10:00:30');

        $this->expectException(PingTimeoutException::class);
        $this->expectExceptionMessage('Ping timeout for socket: throwable-test');

        throw new PingTimeoutException($sessionId, $timeout, $lastPingTime, $nowTime);
    }
}
