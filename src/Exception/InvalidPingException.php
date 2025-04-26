<?php

namespace SocketIoBundle\Exception;

class InvalidPingException extends StatusException
{
    public function __construct(string $sessionId)
    {
        parent::__construct("Invalid last ping for socket: {$sessionId}");
    }
}
