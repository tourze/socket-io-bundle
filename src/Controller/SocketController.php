<?php

namespace SocketIoBundle\Controller;

use Psr\Log\LoggerInterface;
use SocketIoBundle\Service\SocketIOService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SocketController extends AbstractController
{
    public function __construct(
        private readonly SocketIOService $socketIO,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route(path: '/socket.io/', name: 'socket_io_endpoint', methods: ['GET', 'POST', 'OPTIONS'])]
    public function __invoke(Request $request): Response
    {
        try {
            // 处理CORS预检请求
            if ($request->isMethod('OPTIONS')) {
                return $this->createCorsResponse();
            }

            // 处理Socket.IO请求
            $response = $this->socketIO->handleRequest($request);

            // 确保所有响应都有CORS头
            $this->addCorsHeaders($response);

            return $response;
        } catch (\Throwable $e) {
            $this->logger->error('SocketIO Exception', [
                'exception' => $e,
                'query' => $request->query->all(),
                'body' => $request->getContent(),
            ]);

            // 错误处理
            $response = new Response(
                json_encode(['error' => $e->getMessage()]),
                Response::HTTP_INTERNAL_SERVER_ERROR,
                ['Content-Type' => 'application/json']
            );
            $this->addCorsHeaders($response);

            return $response;
        }
    }

    private function createCorsResponse(): Response
    {
        $response = new Response();
        $this->addCorsHeaders($response);

        return $response;
    }

    private function addCorsHeaders(Response $response): void
    {
        $response->headers->set('Access-Control-Allow-Origin', '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
    }
}