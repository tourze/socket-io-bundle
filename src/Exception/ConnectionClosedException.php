<?php

namespace SocketIoBundle\Exception;

/**
 * 连接已关闭异常
 */
class ConnectionClosedException extends SocketException
{
    public function __construct(string $message = 'Connection closed', int $code = 400, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
