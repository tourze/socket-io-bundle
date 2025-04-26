<?php

namespace SocketIoBundle\Exception;

class DeliveryTimeoutException extends StatusException
{
    public function __construct(
        string $sessionId,
        int $timeout,
        \DateTimeInterface $lastDeliverTime,
        \DateTimeInterface $nowTime,
    ) {
        parent::__construct("Delivery timeout for socket: {$sessionId}, timeout: {$timeout}s, lastDeliverTime: {$lastDeliverTime->format('Y-m-d H:i:s')}, nowTime: {$nowTime->format('Y-m-d H:i:s')}");
    }
}
