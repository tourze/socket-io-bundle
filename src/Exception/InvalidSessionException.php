<?php

namespace SocketIoBundle\Exception;

/**
 * 会话无效异常
 */
class InvalidSessionException extends SocketException
{
    public function __construct(string $message = 'Invalid session', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}