<?php

namespace SocketIoBundle\Transport;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface TransportInterface
{
    public function getSessionId(): string;

    public function handleRequest(Request $request): Response;

    public function send(string $data): void;

    public function close(): void;

    public function isExpired(): bool;

    public function setPacketHandler(callable $handler): void;
}
