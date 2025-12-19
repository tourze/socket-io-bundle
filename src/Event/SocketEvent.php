<?php

namespace SocketIoBundle\Event;

use SocketIoBundle\Entity\Socket;
use Symfony\Contracts\EventDispatcher\Event;

final class SocketEvent extends Event
{
    /**
     * @param array<mixed> $data
     */
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

    /**
     * @return array<mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function getNamespace(): string
    {
        return $this->socket?->getNamespace() ?? '/';
    }
}
