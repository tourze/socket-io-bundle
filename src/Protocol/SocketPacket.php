<?php

namespace SocketIoBundle\Protocol;

use SocketIoBundle\Enum\SocketPacketType;

class SocketPacket
{
    private SocketPacketType $type;

    private ?string $namespace;

    private ?int $id;

    private ?string $data;

    private bool $binary;

    public function __construct(
        SocketPacketType $type,
        ?string $namespace = null,
        ?int $id = null,
        ?string $data = null,
        bool $binary = false,
    ) {
        $this->type = $type;
        $this->namespace = $namespace;
        $this->id = $id;
        $this->data = $data;
        $this->binary = $binary;
    }

    public function getType(): SocketPacketType
    {
        return $this->type;
    }

    public function getNamespace(): ?string
    {
        return $this->namespace;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function isBinary(): bool
    {
        return $this->binary;
    }

    public function encode(): string
    {
        $str = (string) $this->type->value;

        if ($this->binary) {
            $str = (string) ($this->type->value + 3);
        }

        if (null !== $this->namespace && '/' !== $this->namespace) {
            $str .= $this->namespace . ',';
        }

        if (null !== $this->id) {
            $str .= (string) $this->id;
        }

        if (null !== $this->data) {
            $str .= $this->data;
        }

        return $str;
    }

    public static function decode(string $packet): self
    {
        $type = (int) $packet[0];
        $binary = false;
        if ($type > 3) {
            $type -= 3;
            $binary = true;
        }
        $type = SocketPacketType::from($type);

        $offset = 1;
        $namespace = null;
        $id = null;
        $data = null;

        if (isset($packet[$offset]) && '/' === $packet[$offset]) {
            $endIndex = strpos($packet, ',', $offset);
            if (false !== $endIndex) {
                $namespace = substr($packet, $offset, $endIndex - $offset);
                $offset = $endIndex + 1;
            }
        }

        if (isset($packet[$offset]) && is_numeric($packet[$offset])) {
            $idStr = '';
            while (isset($packet[$offset]) && is_numeric($packet[$offset])) {
                $idStr .= $packet[$offset];
                ++$offset;
            }
            $id = (int) $idStr;
        }

        if (isset($packet[$offset])) {
            $data = substr($packet, $offset);
        }

        return new self($type, $namespace, $id, $data, $binary);
    }

    public static function createConnect(?string $namespace = null, ?array $data = null): self
    {
        return new self(SocketPacketType::CONNECT, $namespace, null, $data ? json_encode($data) : null);
    }

    public static function createDisconnect(?string $namespace = null): self
    {
        return new self(SocketPacketType::DISCONNECT, $namespace);
    }

    public static function createEvent(string $data, ?string $namespace = null, ?int $id = null, bool $binary = false): self
    {
        return new self(SocketPacketType::EVENT, $namespace, $id, $data, $binary);
    }

    public static function createAck(string $data, ?string $namespace = null, ?int $id = null, bool $binary = false): self
    {
        return new self(SocketPacketType::ACK, $namespace, $id, $data, $binary);
    }

    public static function createError(string $data, ?string $namespace = null): self
    {
        return new self(SocketPacketType::ERROR, $namespace, null, $data);
    }

    public static function createBinaryEvent(string $data, ?string $namespace = null, ?int $id = null): self
    {
        return new self(SocketPacketType::EVENT, $namespace, $id, $data, true);
    }

    public static function createBinaryAck(string $data, ?string $namespace = null, ?int $id = null): self
    {
        return new self(SocketPacketType::ACK, $namespace, $id, $data, true);
    }
}
