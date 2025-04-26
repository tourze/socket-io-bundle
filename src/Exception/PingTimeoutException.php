<?php

namespace SocketIoBundle\Exception;

class PingTimeoutException extends StatusException
{
    public function __construct(
        string $sessionId,
        int $timeout,
        \DateTimeInterface $lastPingTime,
        \DateTimeInterface $nowTime,
    ) {
        parent::__construct("Ping timeout for socket: {$sessionId}, timeout: {$timeout}s, lastPingTime: {$lastPingTime->format('Y-m-d H:i:s')}, nowTime: {$nowTime->format('Y-m-d H:i:s')}");
    }
}
