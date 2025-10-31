<?php

namespace SocketIoBundle\Service;

use SocketIoBundle\Exception\InvalidPayloadException;

class PayloadProcessor
{
    private const PAYLOAD_SEPARATOR = "\x1e";

    public function __construct(
        private readonly bool $supportsBinary = false,
        private readonly bool $jsonp = false,
        private readonly ?string $jsonpIndex = null,
    ) {
    }

    public function encodePacket(string $packet): string
    {
        return $this->isBinary($packet)
            ? $this->encodeBinaryPacket($packet)
            : $this->encodeTextPacket($packet);
    }

    private function isBinary(string $data): bool
    {
        // 检查是否包含非打印字符(除了空格、制表符、换行)
        return 1 === preg_match('/[^\x20-\x7E\t\r\n]/', $data);
    }

    private function encodeBinaryPacket(string $packet): string
    {
        if ($this->supportsBinary && !$this->jsonp) {
            return $packet;
        }

        return 'b' . base64_encode($packet);
    }

    private function encodeTextPacket(string $packet): string
    {
        return $this->jsonp
            ? '___eio[' . $this->jsonpIndex . "]('" . addslashes($packet) . "');"
            : $packet;
    }

    /**
     * @return array<string>
     */
    public function decodePayload(string $payload): array
    {
        if ('' === $payload) {
            return [];
        }

        $packets = [];
        $chunks = explode(self::PAYLOAD_SEPARATOR, $payload);

        foreach ($chunks as $chunk) {
            $decodedChunk = $this->processChunk($chunk);
            if (null !== $decodedChunk) {
                $packets[] = $decodedChunk;
            }
        }

        return $packets;
    }

    private function processChunk(string $chunk): ?string
    {
        if ('' === $chunk) {
            return null;
        }

        if ('b' === $chunk[0]) {
            return $this->decodeBinaryChunk($chunk);
        }

        return $chunk;
    }

    private function decodeBinaryChunk(string $chunk): string
    {
        $decoded = base64_decode(substr($chunk, 1), true);
        if (false === $decoded) {
            throw new InvalidPayloadException('Invalid base64 payload');
        }

        return $decoded;
    }

    /**
     * @param array<string> $encodedPackets
     */
    public function buildPayload(array $encodedPackets): string
    {
        return implode(self::PAYLOAD_SEPARATOR, $encodedPackets);
    }

    public function decodeJsonpPayload(string $content): string
    {
        $result = preg_match('/d=(.*)/', $content, $matches);
        if (false !== $result && $result > 0 && isset($matches[1])) {
            return urldecode($matches[1]);
        }
        throw new InvalidPayloadException('Invalid JSONP payload');
    }
}
