<?php

namespace SocketIoBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Exception\ConnectionClosedException;
use SocketIoBundle\Protocol\EnginePacket;
use Symfony\Component\HttpFoundation\Response;

class PollingStrategy
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeliveryService $deliveryService,
        private readonly MessageBuilder $messageBuilder,
        private readonly PayloadProcessor $payloadProcessor,
    ) {
    }

    public function waitForMessagesOrTimeout(Socket $socket, int $pingInterval, int $maxPayloadSize): Response
    {
        $startTime = time();

        while ($this->shouldContinueWaiting($startTime, $pingInterval)) {
            $response = $this->tryDeliverMessages($socket, $maxPayloadSize);

            if (null !== $response) {
                return $this->handleMessageResponse($socket, $response);
            }

            $this->waitBetweenPolls();
            $this->validateSocketConnection($socket);
        }

        return $this->handlePollTimeout($socket);
    }

    private function shouldContinueWaiting(int $startTime, int $pingInterval): bool
    {
        return (time() - $startTime) < $pingInterval;
    }

    private function tryDeliverMessages(Socket $socket, int $maxPayloadSize): ?Response
    {
        $deliveries = $this->deliveryService->getPendingDeliveries($socket);
        if (0 === count($deliveries)) {
            return null;
        }

        [$payload, $processedDeliveries] = $this->messageBuilder->buildMessagePayload(
            $deliveries,
            $this->payloadProcessor,
            $maxPayloadSize
        );

        foreach ($processedDeliveries as $delivery) {
            $this->deliveryService->markDelivered($delivery);
        }

        // 更新最后投递时间
        $socket->updateDeliverTime();
        $this->em->flush();

        return $this->createEngineResponse(EnginePacket::createMessage($payload)->encode());
    }

    private function createEngineResponse(string $enginePackage): Response
    {
        return new Response($enginePackage);
    }

    private function handleMessageResponse(Socket $socket, Response $response): Response
    {
        $pingInterval = $_ENV['SOCKET_IO_PING_INTERVAL'] ?? '60000';

        if (!is_scalar($pingInterval)) {
            $pingInterval = '60000';
        }

        $pingIntervalHalf = intval($pingInterval) / 2;
        if ($this->shouldSendPing($socket, intval($pingIntervalHalf))) {
            $socket->updateLastActiveTime();
            $this->em->flush();
        }

        return $response;
    }

    public function shouldSendPing(Socket $socket, int $pingIntervalHalf): bool
    {
        $lastPingTime = $socket->getLastPingTime();
        $now = new \DateTime();

        return null !== $lastPingTime
               && ($now->getTimestamp() - $lastPingTime->getTimestamp()) > $pingIntervalHalf;
    }

    private function waitBetweenPolls(): void
    {
        usleep(100000); // 100ms
    }

    private function validateSocketConnection(Socket $socket): void
    {
        if (!$this->isSocketStillValid($socket)) {
            throw new ConnectionClosedException('Connection closed', Response::HTTP_BAD_REQUEST);
        }
    }

    private function isSocketStillValid(Socket $socket): bool
    {
        $this->em->refresh($socket);

        return $socket->isConnected();
    }

    public function handlePollTimeout(Socket $socket): Response
    {
        $socket->updatePingTime();
        $this->em->flush();

        return $this->createEngineResponse(EnginePacket::createPing()->encode());
    }
}
