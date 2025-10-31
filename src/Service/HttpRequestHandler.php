<?php

namespace SocketIoBundle\Service;

use SocketIoBundle\Enum\EnginePacketType;
use SocketIoBundle\Protocol\EnginePacket;
use SocketIoBundle\Protocol\SocketPacket;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class HttpRequestHandler
{
    private bool $jsonp = false;

    private ?string $jsonpIndex = null;

    private bool $supportsBinary = false;

    /** @var callable|null */
    private $packetHandler;

    public function __construct(
        private readonly PayloadProcessor $payloadProcessor,
    ) {
    }

    public function setPacketHandler(callable $handler): void
    {
        $this->packetHandler = $handler;
    }

    public function initializeRequestSettings(Request $request): void
    {
        $jsonpValue = $request->query->get('j');
        $this->jsonpIndex = is_string($jsonpValue) ? $jsonpValue : null;
        $this->jsonp = null !== $this->jsonpIndex;
        $this->supportsBinary = !$this->jsonp && 'application/octet-stream' === $request->headers->get('Accept');
    }

    public function handlePost(Request $request): Response
    {
        $content = $this->preparePostContent($request);

        if ($this->isPayloadTooLarge($content)) {
            return new Response('Payload too large', Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return $this->executePostProcessing($content);
    }

    private function preparePostContent(Request $request): string
    {
        $content = $request->getContent();

        return $this->jsonp ? $this->payloadProcessor->decodeJsonpPayload($content) : $content;
    }

    private function isPayloadTooLarge(string $content): bool
    {
        $maxSize = $_ENV['SOCKET_IO_MAX_PAYLOAD_SIZE'] ?? '1048576';

        if (!is_scalar($maxSize)) {
            $maxSize = '1048576';
        }

        return strlen($content) > intval($maxSize);
    }

    private function executePostProcessing(string $content): Response
    {
        try {
            $this->processPackets($content);

            return $this->createPostResponse();
        } catch (\Throwable $e) {
            return $this->createPostErrorResponse($e);
        }
    }

    private function processPackets(string $content): void
    {
        $packets = $this->payloadProcessor->decodePayload($content);
        foreach ($packets as $packet) {
            $this->handlePacket($packet);
        }
    }

    private function handlePacket(string|int $packet): void
    {
        $enginePacket = EnginePacket::decode((string) $packet);

        match ($enginePacket->getType()) {
            EnginePacketType::MESSAGE => $this->handleMessagePacket($enginePacket),
            EnginePacketType::CLOSE => $this->handleClosePacket(),
            default => null,
        };
    }

    private function handleMessagePacket(EnginePacket $enginePacket): void
    {
        if (null === $this->packetHandler || null === $enginePacket->getData()) {
            return;
        }

        $socketPacket = SocketPacket::decode($enginePacket->getData());
        ($this->packetHandler)($socketPacket);
    }

    private function handleClosePacket(): void
    {
        // 正常关闭连接，不需要特殊处理
        // 客户端会在收到响应后自行关闭
    }

    private function createPostResponse(): Response
    {
        $response = new Response('ok');
        if ($this->jsonp) {
            $response->setContent('___eio[' . $this->jsonpIndex . "]('ok');");
        }

        return $response;
    }

    private function createPostErrorResponse(\Throwable $e): Response
    {
        $message = $this->jsonp
            ? '___eio[' . $this->jsonpIndex . "]('" . $e->getMessage() . "');"
            : $e->getMessage();

        return new Response($message, Response::HTTP_BAD_REQUEST);
    }

    public function createFirstPollResponse(string $socketId): Response
    {
        // 要注意，下面返回的sid，其实不是URL上面的SID(SessionId)，其实指的是SocketId;
        $socketPacket = SocketPacket::createConnect('/', ['sid' => $socketId]);
        $enginePacket = EnginePacket::createMessage($socketPacket->encode());

        return $this->createResponse($enginePacket->encode());
    }

    public function createResponse(string $enginePackage): Response
    {
        $response = new Response($enginePackage);
        $response->headers->set('Content-Type', $this->getContentType());

        return $response;
    }

    private function getContentType(): string
    {
        if ($this->jsonp) {
            return 'text/javascript; charset=UTF-8';
        }

        return $this->supportsBinary
            ? 'application/octet-stream'
            : 'text/plain; charset=UTF-8';
    }
}
