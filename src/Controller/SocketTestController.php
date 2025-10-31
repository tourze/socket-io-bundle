<?php

namespace SocketIoBundle\Controller;

use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use SocketIoBundle\Service\MessageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[WithMonologChannel(channel: 'socket_io')]
final class SocketTestController extends AbstractController
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly MessageService $messageService,
    ) {
    }

    #[Route(path: '/socket.io/test', name: 'socket_io_test', methods: ['GET'])]
    public function __invoke(): Response
    {
        $time = microtime(true);

        try {
            // 发送随机消息
            $messageData = [
                'message1' => $time . '-' . bin2hex(random_bytes(8)),
                'message2' => $time . '-' . bin2hex(random_bytes(8)),
            ];
            $activeSockets = $this->messageService->broadcast('random2', $messageData);

            $jsonContent = json_encode([
                'success' => true,
                'message' => 'Random message sent to ' . $activeSockets . ' active clients',
                'data' => $messageData,
            ]);

            return new Response(false !== $jsonContent ? $jsonContent : '{}', 200, ['Content-Type' => 'application/json']);
        } catch (\Throwable $e) {
            $this->logger->error('Test message sending failed', [
                'exception' => $e->getMessage(),
            ]);

            $jsonContent = json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);

            return new Response(false !== $jsonContent ? $jsonContent : '{}', 500, ['Content-Type' => 'application/json']);
        }
    }
}
