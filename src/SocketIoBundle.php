<?php

namespace SocketIoBundle;

use ChrisUllyott\FileSize;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SocketIoBundle extends Bundle
{
    public function boot(): void
    {
        parent::boot();

        $_ENV['SOCKET_IO_MAX_PAYLOAD_SIZE'] = (new FileSize('10M'))->as('B'); // 1MB
        $_ENV['SOCKET_IO_PING_INTERVAL'] = '25'; // 25s
        $_ENV['SOCKET_IO_PING_TIMEOUT'] = '20'; // 20s
    }
}
