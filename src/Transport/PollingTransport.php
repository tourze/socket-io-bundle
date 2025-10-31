<?php

namespace SocketIoBundle\Transport;

use Doctrine\ORM\EntityManagerInterface;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\EnginePacketType;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Exception\InvalidSessionException;
use SocketIoBundle\Protocol\EnginePacket;
use SocketIoBundle\Protocol\SocketPacket;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Service\HttpRequestHandler;
use SocketIoBundle\Service\MessageBuilder;
use SocketIoBundle\Service\PollingStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PollingTransport implements TransportInterface
{
    private const POLLING_TIMEOUT = 20; // seconds

    private string $sessionId;

    private float $lastPollTime;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SocketRepository $socketRepository,
        private readonly DeliveryService $deliveryService,
        private readonly Socket $socket,
        private readonly HttpRequestHandler $httpRequestHandler,
        private readonly MessageBuilder $messageBuilder,
        private readonly PollingStrategy $pollingStrategy,
        string $sessionId,
    ) {
        $this->sessionId = $sessionId;
        $this->lastPollTime = microtime(true);
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setPacketHandler(callable $handler): void
    {
        $this->httpRequestHandler->setPacketHandler($handler);
    }

    public function handleRequest(Request $request): Response
    {
        $this->httpRequestHandler->initializeRequestSettings($request);

        return match ($request->getMethod()) {
            'GET' => $this->handlePoll($request),
            'POST' => $this->httpRequestHandler->handlePost($request),
            default => new Response('Method not allowed', Response::HTTP_METHOD_NOT_ALLOWED),
        };
    }

    public function send(string $data): void
    {
        $socket = $this->getValidConnectedSocket();
        if (null === $socket) {
            return;
        }

        $socketPacket = $this->parseIncomingData($data);
        if (null === $socketPacket) {
            return;
        }

        $this->createAndPersistMessage($socket, $socketPacket);
    }

    public function close(): void
    {
        $socket = $this->socketRepository->findBySessionId($this->sessionId);
        if (null === $socket) {
            return;
        }

        $this->disconnectSocket($socket);
        $this->failPendingDeliveries($socket);
        $this->em->flush();
    }

    private function disconnectSocket(Socket $socket): void
    {
        $socket->setConnected(false);
    }

    private function failPendingDeliveries(Socket $socket): void
    {
        $pendingDeliveries = $this->deliveryService->getPendingDeliveries($socket);
        foreach ($pendingDeliveries as $delivery) {
            $delivery->setStatus(MessageStatus::FAILED);
            $delivery->setError('Connection closed');
        }
    }

    public function isExpired(): bool
    {
        return (microtime(true) - $this->lastPollTime) > (self::POLLING_TIMEOUT * 2);
    }

    private function handlePoll(Request $request): Response
    {
        $this->lastPollTime = microtime(true);
        $socket = $this->validateAndUpdateSocket();

        if (1 === $socket->getPollCount()) {
            return $this->handleFirstPoll();
        }

        return $this->handleLongPoll($socket);
    }

    private function validateAndUpdateSocket(): Socket
    {
        $socket = $this->socketRepository->findBySessionId($this->sessionId);
        if (null === $socket) {
            throw new InvalidSessionException('Invalid session', Response::HTTP_BAD_REQUEST);
        }

        $socket->incrementPollCount();
        $this->em->flush();

        return $socket;
    }

    private function handleFirstPoll(): Response
    {
        $socketId = $this->socket->getSocketId();
        if (null === $socketId) {
            throw new \InvalidArgumentException('Socket ID cannot be null for first poll response');
        }

        return $this->httpRequestHandler->createFirstPollResponse($socketId);
    }

    private function handleLongPoll(Socket $socket): Response
    {
        $pingInterval = $_ENV['SOCKET_IO_PING_INTERVAL'] ?? '60000';
        $maxPayloadSize = $_ENV['SOCKET_IO_MAX_PAYLOAD_SIZE'] ?? '1048576';

        // 确保环境变量是标量类型
        if (!is_scalar($pingInterval)) {
            $pingInterval = '60000';
        }
        if (!is_scalar($maxPayloadSize)) {
            $maxPayloadSize = '1048576';
        }

        $pingIntervalHalf = intval($pingInterval) / 2;

        return $this->pollingStrategy->shouldSendPing($socket, intval($pingIntervalHalf))
            ? $this->pollingStrategy->handlePollTimeout($socket)
            : $this->pollingStrategy->waitForMessagesOrTimeout(
                $socket,
                intval($pingInterval),
                intval($maxPayloadSize)
            );
    }

    private function getValidConnectedSocket(): ?Socket
    {
        $socket = $this->socketRepository->findBySessionId($this->sessionId);

        return (null !== $socket && $socket->isConnected()) ? $socket : null;
    }

    private function parseIncomingData(string $data): ?SocketPacket
    {
        $enginePacket = EnginePacket::decode($data);
        if (EnginePacketType::MESSAGE !== $enginePacket->getType()) {
            return null;
        }

        $packetData = $enginePacket->getData();
        if (null === $packetData) {
            return null;
        }

        return SocketPacket::decode($packetData);
    }

    private function createAndPersistMessage(Socket $socket, SocketPacket $socketPacket): void
    {
        $message = $this->messageBuilder->createMessage($socket, $socketPacket);
        $this->em->persist($message);

        $delivery = $this->messageBuilder->createDelivery($socket, $message);
        $this->em->persist($delivery);

        $this->em->flush();
    }
}
