<?php

namespace SocketIoBundle\Transport;

use Doctrine\ORM\EntityManagerInterface;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\EnginePacketType;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Protocol\EnginePacket;
use SocketIoBundle\Protocol\SocketPacket;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use SocketIoBundle\Exception\InvalidSessionException;
use SocketIoBundle\Exception\ConnectionClosedException;
use SocketIoBundle\Exception\InvalidPayloadException;

class PollingTransport implements TransportInterface
{
    private const POLLING_TIMEOUT = 20; // seconds

    private string $sessionId;

    private float $lastPollTime;

    private bool $supportsBinary;

    /** @var callable|null */
    private $packetHandler;

    private bool $jsonp = false;

    private ?string $jsonpIndex = null;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SocketRepository $socketRepository,
        private readonly DeliveryService $deliveryService,
        private readonly Socket $socket,
        string $sessionId,
    ) {
        $this->sessionId = $sessionId;
        $this->lastPollTime = microtime(true);
        $this->supportsBinary = false; // Default to base64 encoding
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setPacketHandler(callable $handler): void
    {
        $this->packetHandler = $handler;
    }

    public function handleRequest(Request $request): Response
    {
        // Check for JSONP
        $this->jsonpIndex = $request->query->get('j');
        $this->jsonp = null !== $this->jsonpIndex;

        // Update content type based on request
        $this->supportsBinary = !$this->jsonp && 'application/octet-stream' === $request->headers->get('Accept');

        if ($request->isMethod('GET')) {
            return $this->handlePoll($request);
        } elseif ($request->isMethod('POST')) {
            return $this->handlePost($request);
        }

        return new Response('Method not allowed', Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function send(string $data): void
    {
        // 获取当前socket
        $socket = $this->socketRepository->findBySessionId($this->sessionId);
        if ($socket === null || !$socket->isConnected()) {
            return;
        }

        // 解码Engine.IO数据包
        $enginePacket = EnginePacket::decode($data);
        if (EnginePacketType::MESSAGE !== $enginePacket->getType()) {
            return;
        }

        // 解码Socket.IO数据包
        $socketPacket = SocketPacket::decode($enginePacket->getData());

        // 创建消息记录
        $message = new Message();
        $message->setEvent($socketPacket->getData() !== null ? json_decode($socketPacket->getData(), true)[0] : '')
            ->setData($socketPacket->getData() !== null ? array_slice(json_decode($socketPacket->getData(), true), 1) : [])
            ->setSender($socket)
            ->setMetadata([
                'namespace' => $socketPacket->getNamespace(),
                'messageId' => $socketPacket->getId(),
            ]);
        $this->em->persist($message);

        // 创建投递记录
        $delivery = new Delivery();
        $delivery->setMessage($message)
            ->setSocket($socket)
            ->setStatus(MessageStatus::PENDING);
        $this->em->persist($delivery);

        $this->em->flush();
    }

    public function close(): void
    {
        // 获取当前socket
        $socket = $this->socketRepository->findBySessionId($this->sessionId);
        if ($socket === null) {
            return;
        }

        // 标记连接为断开状态
        $socket->setConnected(false);

        // 清理未投递的消息
        $pendingDeliveries = $this->deliveryService->getPendingDeliveries($socket);
        foreach ($pendingDeliveries as $delivery) {
            $delivery->setStatus(MessageStatus::FAILED)
                ->setError('Connection closed');
        }

        $this->em->flush();

        // 移除传输层实例
        $this->packetHandler = null;
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
        if ($socket === null) {
            throw new InvalidSessionException('Invalid session', Response::HTTP_BAD_REQUEST);
        }

        $socket->incrementPollCount();
        $this->em->flush();

        return $socket;
    }

    private function handleFirstPoll(): Response
    {
        // 要注意，下面返回的sid，其实不是URL上面的SID(SessionId)，其实指的是SocketId;
        $socketPacket = SocketPacket::createConnect('/', ['sid' => $this->socket->getSocketId()]);
        $enginePacket = EnginePacket::createMessage($socketPacket->encode());

        return $this->createResponse($enginePacket->encode());
    }

    private function handleLongPoll(Socket $socket): Response
    {
        $startTime = time();
        $lastPingTime = $socket->getLastPingTime();
        $now = new \DateTime();

        // 如果上次 ping 时间超过了 ping interval 的一半，优先发送 ping
        if ($lastPingTime !== null && ($now->getTimestamp() - $lastPingTime->getTimestamp()) > intval($_ENV['SOCKET_IO_PING_INTERVAL'] / 2)) {
            return $this->handlePollTimeout($socket);
        }

        while ((time() - $startTime) < intval($_ENV['SOCKET_IO_PING_INTERVAL'])) {
            $response = $this->tryDeliverMessages($socket);
            if ($response !== null) {
                // 检查是否需要发送 ping
                $now = new \DateTime();
                $lastPingTime = $socket->getLastPingTime();
                if ($lastPingTime !== null && ($now->getTimestamp() - $lastPingTime->getTimestamp()) > intval($_ENV['SOCKET_IO_PING_INTERVAL'] / 2)) {
                    // 先发送消息，下一次轮询时发送 ping
                    $socket->updateLastActiveTime();
                    $this->em->flush();

                    return $response;
                }

                return $response;
            }

            usleep(100000); // 100ms

            if (!$this->isSocketStillValid($socket)) {
                throw new ConnectionClosedException('Connection closed', Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->handlePollTimeout($socket);
    }

    private function tryDeliverMessages(Socket $socket): ?Response
    {
        $deliveries = $this->deliveryService->getPendingDeliveries($socket);
        if (empty($deliveries)) {
            return null;
        }

        [$payload, $processedDeliveries] = $this->buildMessagePayload($deliveries);

        foreach ($processedDeliveries as $delivery) {
            $this->deliveryService->markDelivered($delivery);
        }

        // 更新最后投递时间
        $socket->updateDeliverTime();
        $this->em->flush();

        return $this->createResponse(EnginePacket::createMessage($payload)->encode());
    }

    /**
     * @param Delivery[] $deliveries
     */
    private function buildMessagePayload(array $deliveries): array
    {
        $payload = '';
        $payloadSize = 0;
        $processedDeliveries = [];

        foreach ($deliveries as $delivery) {
            $packet = $this->createSocketPacket($delivery->getMessage());
            $encodedPacket = $this->encodePacket($packet->encode());

            $packetSize = strlen($encodedPacket) + ($payload !== '' ? 1 : 0);
            if ($payloadSize + $packetSize > intval($_ENV['SOCKET_IO_MAX_PAYLOAD_SIZE'])) {
                break;
            }

            if ($payload !== '') {
                $payload .= "\x1e";
            }
            $payload .= $encodedPacket;
            $payloadSize += $packetSize;
            $processedDeliveries[] = $delivery;
        }

        return [$payload, $processedDeliveries];
    }

    private function createSocketPacket(Message $message): SocketPacket
    {
        return SocketPacket::createEvent(
            json_encode([$message->getEvent(), ...$message->getData()])
        );
    }

    private function isSocketStillValid(Socket $socket): bool
    {
        $this->em->refresh($socket);

        return $socket->isConnected();
    }

    private function handlePollTimeout(Socket $socket): Response
    {
        $socket->updatePingTime();
        $this->em->flush();

        return $this->createResponse(EnginePacket::createPing()->encode());
    }

    private function handlePost(Request $request): Response
    {
        $content = $request->getContent();

        // Handle JSONP
        if ($this->jsonp) {
            $content = $this->decodeJsonpPayload($content);
        }

        if (strlen($content) > intval($_ENV['SOCKET_IO_MAX_PAYLOAD_SIZE'])) {
            return new Response('Payload too large', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        try {
            $packets = $this->decodePayload($content);
            foreach ($packets as $packet) {
                $this->handlePacket($packet);
            }

            $response = new Response('ok');
            if ($this->jsonp) {
                $response->setContent('___eio[' . $this->jsonpIndex . "]('ok');");
            }

            return $response;
        } catch (\Throwable $e) {
            return new Response(
                $this->jsonp ? '___eio[' . $this->jsonpIndex . "]('" . $e->getMessage() . "');" : $e->getMessage(),
                Response::HTTP_BAD_REQUEST
            );
        }
    }

    private function handlePacket(string|int $packet): void
    {
        $enginePacket = EnginePacket::decode((string) $packet);

        if (EnginePacketType::MESSAGE === $enginePacket->getType()
            && null !== $this->packetHandler
            && null !== $enginePacket->getData()) {
            $socketPacket = SocketPacket::decode($enginePacket->getData());
            ($this->packetHandler)($socketPacket);
        } elseif (EnginePacketType::CLOSE === $enginePacket->getType()) {
            // 正常关闭连接，不需要特殊处理
            // 客户端会在收到响应后自行关闭
        }
    }

    private function encodePacket(string $packet): string
    {
        // 检查是否为二进制数据
        if ($this->isBinary($packet)) {
            if ($this->supportsBinary && !$this->jsonp) {
                return $packet;
            }

            // 二进制数据需要base64编码并添加'b'前缀
            return 'b' . base64_encode($packet);
        }

        // 普通文本数据
        if ($this->jsonp) {
            return '___eio[' . $this->jsonpIndex . "]('" . addslashes($packet) . "');";
        }

        return $packet;
    }

    private function isBinary(string $data): bool
    {
        // 检查是否包含非打印字符(除了空格、制表符、换行)
        return 1 === preg_match('/[^\x20-\x7E\t\r\n]/', $data);
    }

    private function decodePayload(string $payload): array
    {
        if (empty($payload)) {
            return [];
        }

        $packets = [];
        $chunks = explode("\x1e", $payload);

        foreach ($chunks as $chunk) {
            if (empty($chunk)) {
                continue;
            }

            // 检查是否为base64编码的二进制数据
            if ('b' === $chunk[0]) {
                $decoded = base64_decode(substr($chunk, 1));
                if (false === $decoded) {
                    throw new InvalidPayloadException('Invalid base64 payload');
                }
                $packets[] = $decoded;
            } else {
                $packets[] = $chunk;
            }
        }

        return $packets;
    }

    private function decodeJsonpPayload(string $content): string
    {
        if (preg_match('/d=(.*)/', $content, $matches)) {
            return urldecode($matches[1]);
        }
        throw new InvalidPayloadException('Invalid JSONP payload');
    }

    private function createResponse(string $enginePackage): Response
    {
        $response = new Response($enginePackage);

        if ($this->jsonp) {
            $response->headers->set('Content-Type', 'text/javascript; charset=UTF-8');
        } else {
            $response->headers->set('Content-Type',
                $this->supportsBinary ? 'application/octet-stream' : 'text/plain; charset=UTF-8');
        }

        return $response;
    }
}
