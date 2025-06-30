<?php

namespace SocketIoBundle\Exception;

/**
 * 无效载荷数据异常
 */
class InvalidPayloadException extends InvalidSocketArgumentException
{
    public function __construct(string $message = 'Invalid payload', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}