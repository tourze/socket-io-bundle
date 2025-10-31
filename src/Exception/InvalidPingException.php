<?php

namespace SocketIoBundle\Exception;

class InvalidPingException extends \RuntimeException
{
    public function __construct(string $sessionId)
    {
        parent::__construct("Invalid last ping for socket: {$sessionId}");
    }
}
