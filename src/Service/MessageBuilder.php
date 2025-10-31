<?php

namespace SocketIoBundle\Service;

use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Protocol\SocketPacket;

class MessageBuilder
{
    public function createMessage(Socket $socket, SocketPacket $socketPacket): Message
    {
        $decodedData = $this->decodeSocketData($socketPacket->getData());

        $message = new Message();
        $message->setEvent($this->extractEvent($decodedData));
        $message->setData($this->convertToStringKeyedArray($this->extractData($decodedData)));
        $message->setSender($socket);
        $message->setMetadata($this->createMessageMetadata($socketPacket));

        return $message;
    }

    /**
     * @return array<mixed>
     */
    private function decodeSocketData(?string $socketData): array
    {
        if (null === $socketData) {
            return [];
        }

        $decoded = json_decode($socketData, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<mixed> $decodedData
     */
    private function extractEvent(array $decodedData): string
    {
        if (count($decodedData) > 0) {
            $firstElement = $decodedData[0];

            if (is_string($firstElement)) {
                return $firstElement;
            }
            if (is_scalar($firstElement)) {
                return (string) $firstElement;
            }
        }

        return '';
    }

    /**
     * @param array<mixed> $decodedData
     * @return array<mixed>
     */
    private function extractData(array $decodedData): array
    {
        return count($decodedData) > 1 ? array_slice($decodedData, 1) : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function createMessageMetadata(SocketPacket $socketPacket): array
    {
        return [
            'namespace' => $socketPacket->getNamespace(),
            'messageId' => $socketPacket->getId(),
        ];
    }

    /**
     * 将混合键类型的数组转换为字符串键数组
     * @param array<mixed> $data
     * @return array<string, mixed>
     */
    private function convertToStringKeyedArray(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $stringKey = is_string($key) ? $key : (string) $key;
            $result[$stringKey] = $value;
        }

        return $result;
    }

    public function createDelivery(Socket $socket, Message $message): Delivery
    {
        $delivery = new Delivery();
        $delivery->setMessage($message);
        $delivery->setSocket($socket);
        $delivery->setStatus(MessageStatus::PENDING);

        return $delivery;
    }

    /**
     * @param Delivery[] $deliveries
     * @return array{string, Delivery[]}
     */
    public function buildMessagePayload(array $deliveries, PayloadProcessor $payloadProcessor, int $maxPayloadSize): array
    {
        $encodedPackets = [];
        $payloadSize = 0;
        $processedDeliveries = [];

        foreach ($deliveries as $delivery) {
            $packet = $this->createSocketPacket($delivery->getMessage());
            $encodedPacket = $payloadProcessor->encodePacket($packet->encode());

            $packetSize = strlen($encodedPacket) + (0 === count($encodedPackets) ? 0 : 1);
            if ($payloadSize + $packetSize > $maxPayloadSize) {
                break;
            }

            $encodedPackets[] = $encodedPacket;
            $payloadSize += $packetSize;
            $processedDeliveries[] = $delivery;
        }

        $payload = $payloadProcessor->buildPayload($encodedPackets);

        return [$payload, $processedDeliveries];
    }

    public function createSocketPacket(Message $message): SocketPacket
    {
        $encodedData = json_encode([$message->getEvent(), ...$message->getData()]);
        if (false === $encodedData) {
            throw new \RuntimeException('Failed to encode message data');
        }

        return SocketPacket::createEvent($encodedData);
    }
}
