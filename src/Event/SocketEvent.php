<?php

namespace SocketIoBundle\Event;

use SocketIoBundle\Entity\Socket;
use Symfony\Contracts\EventDispatcher\Event;

class SocketEvent extends Event
{
    public function __construct(
        private readonly string $name,
        private readonly ?Socket $socket,
        private readonly array $data = [],
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSocket(): ?Socket
    {
        return $this->socket;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getNamespace(): string
    {
        return $this->socket?->getNamespace() ?? '/';
    }
}
