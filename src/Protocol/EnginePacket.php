<?php

namespace SocketIoBundle\Protocol;

use SocketIoBundle\Enum\EnginePacketType;

class EnginePacket
{
    public function __construct(
        private readonly EnginePacketType $type,
        private readonly ?string $data = null,
    ) {
    }

    public function getType(): EnginePacketType
    {
        return $this->type;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function encode(): string
    {
        $str = (string) $this->type->value;
        if (null !== $this->data) {
            $str .= $this->data;
        }

        return $str;
    }

    public static function decode(string $packet): self
    {
        $type = EnginePacketType::from((int) $packet[0]);
        $subStr = substr($packet, 1);
        $data = '' !== $subStr ? $subStr : null;

        return new self($type, $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function createOpen(array $data): self
    {
        $encodedData = json_encode($data);
        if (false === $encodedData) {
            throw new \InvalidArgumentException('Failed to encode data to JSON');
        }

        return new self(EnginePacketType::OPEN, $encodedData);
    }

    public static function createClose(): self
    {
        return new self(EnginePacketType::CLOSE);
    }

    public static function createPing(?string $data = null): self
    {
        return new self(EnginePacketType::PING, $data);
    }

    public static function createPong(?string $data = null): self
    {
        return new self(EnginePacketType::PONG, $data);
    }

    public static function createMessage(string $data): self
    {
        return new self(EnginePacketType::MESSAGE, $data);
    }

    public static function createUpgrade(): self
    {
        return new self(EnginePacketType::UPGRADE);
    }

    public static function createNoop(): self
    {
        return new self(EnginePacketType::NOOP);
    }
}
