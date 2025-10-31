<?php

namespace SocketIoBundle\Protocol;

use SocketIoBundle\Enum\SocketPacketType;

class SocketPacket
{
    public function __construct(
        private readonly SocketPacketType $type,
        private readonly ?string $namespace = null,
        private readonly ?int $id = null,
        private readonly ?string $data = null,
        private readonly bool $binary = false,
    ) {
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
        [$type, $binary] = self::parseTypeAndBinary($packet);
        $offset = 1;

        [$namespace, $offset] = self::parseNamespace($packet, $offset);
        [$id, $offset] = self::parseId($packet, $offset);
        $data = self::parseData($packet, $offset);

        return new self($type, $namespace, $id, $data, $binary);
    }

    /**
     * @return array{SocketPacketType, bool}
     */
    private static function parseTypeAndBinary(string $packet): array
    {
        $type = (int) $packet[0];
        $binary = false;

        if ($type > 3) {
            $type -= 3;
            $binary = true;
        }

        return [SocketPacketType::from($type), $binary];
    }

    /**
     * @return array{?string, int}
     */
    private static function parseNamespace(string $packet, int $offset): array
    {
        if (!isset($packet[$offset]) || '/' !== $packet[$offset]) {
            return [null, $offset];
        }

        $endIndex = strpos($packet, ',', $offset);
        if (false === $endIndex) {
            return [null, $offset];
        }

        $namespace = substr($packet, $offset, $endIndex - $offset);
        $newOffset = $endIndex + 1;

        return [$namespace, $newOffset];
    }

    /**
     * @return array{?int, int}
     */
    private static function parseId(string $packet, int $offset): array
    {
        if (!isset($packet[$offset]) || !is_numeric($packet[$offset])) {
            return [null, $offset];
        }

        $idStr = '';
        $currentOffset = $offset;
        while (isset($packet[$currentOffset]) && is_numeric($packet[$currentOffset])) {
            $idStr .= $packet[$currentOffset];
            ++$currentOffset;
        }

        return [(int) $idStr, $currentOffset];
    }

    private static function parseData(string $packet, int $offset): ?string
    {
        return isset($packet[$offset]) ? substr($packet, $offset) : null;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function createConnect(?string $namespace = null, ?array $data = null): self
    {
        $encodedData = null;
        if (null !== $data) {
            $encoded = json_encode($data);
            $encodedData = false !== $encoded ? $encoded : null;
        }

        return new self(SocketPacketType::CONNECT, $namespace, null, $encodedData);
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
