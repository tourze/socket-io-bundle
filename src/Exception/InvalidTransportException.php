<?php

namespace SocketIoBundle\Exception;

class InvalidTransportException extends \RuntimeException
{
    public function __construct(string $sessionId)
    {
        parent::__construct("Invalid transport for socket: {$sessionId}");
    }
}
