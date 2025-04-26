<?php

use SocketIoBundle\Server\Socket;
use SocketIoBundle\Server\SocketIO;
use Workerman\Worker;

require_once __DIR__ . '/../../vendor/autoload.php';

// Listen port 2021 for socket.io client
$io = new SocketIO(2021);
$io->on('connection', function (Socket $socket) use ($io) {
    $socket->on('hello', function (array $msg) use ($io, $socket) {
        dump($msg, time());
        $socket->emit('res1', $msg);
        $io->emit('res2', $msg);
    });
});

Worker::runAll();
