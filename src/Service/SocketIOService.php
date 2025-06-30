<?php

namespace SocketIoBundle\Service;

use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Event\SocketEvent;
use SocketIoBundle\Exception\StatusException;
use SocketIoBundle\Protocol\EnginePacket;
use SocketIoBundle\Repository\SocketRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Socket.IO 核心服务
 *
 * 负责管理 Socket.IO 的核心功能，包括：
 * - 连接握手和管理
 * - 事件处理和路由
 * - 消息广播
 * - 命名空间管理
 * - 系统维护
 */
class SocketIOService
{
    public function __construct(
        private readonly SocketRepository $socketRepository,
        private readonly SocketService $socketService,
        private readonly DeliveryService $deliveryService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * 初始化服务，执行必要的清理工作
     */
    public function initialize(): void
    {
        $this->socketRepository->cleanupInactiveConnections();
    }

    public function handleRequest(Request $request): Response
    {
        // 随机触发？
        // $this->socketRepository->cleanupInactiveConnections();

        try {
            $sid = $request->query->get('sid');

            // 处理握手请求
            if (null === $sid) {
                return $this->handleHandshake($request);
            }

            // 处理已存在的连接
            $socket = $this->socketRepository->findBySessionId($sid);
            if ($socket !== null) {
                try {
                    $this->socketService->checkActive($socket);
                    $transport = $this->socketService->getTransport($socket);
                    if ($transport !== null) {
                        return $transport->handleRequest($request);
                    }

                    return new Response('Transport error', Response::HTTP_INTERNAL_SERVER_ERROR);
                } catch (StatusException $e) {
                    $this->socketService->disconnect($socket);

                    return new Response('Session expired: ' . $e->getMessage(), Response::HTTP_GONE);
                }
            }

            return new Response('Invalid session', Response::HTTP_BAD_REQUEST);
        } catch (\Throwable $e) {
            return new Response($e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * 系统维护
     */
    public function cleanup(): void
    {
        // 清理过期的socket连接
        $sockets = $this->socketRepository->findActiveConnections();
        foreach ($sockets as $socket) {
            try {
                $this->socketService->checkActive($socket);
            } catch (StatusException $e) {
                $this->socketService->disconnect($socket);
            }
        }

        // 清理消息队列
        $this->deliveryService->cleanupQueues();

        // 清理数据库中的无效连接
        $this->socketRepository->cleanupInactiveConnections();
    }

    private function handleHandshake(Request $request): Response
    {
        // 生成唯一会话ID
        $sessionId = $this->socketService->generateUniqueId();
        $socketId = $this->socketService->generateUniqueId();

        // 创建Socket连接
        $socket = $this->socketService->createConnection($sessionId, $socketId);

        // 触发连接事件
        $this->dispatchEvent('socket.connect', $socket);

        // 准备握手数据
        $handshake = [
            'sid' => $sessionId,
            'upgrades' => [], // TODO 目前我们只实现了HTTP轮询
            'pingInterval' => ($_ENV['SOCKET_IO_PING_INTERVAL'] ?? 25) * 1000, // 这里返回的是毫秒
            'pingTimeout' => ($_ENV['SOCKET_IO_PING_TIMEOUT'] ?? 5) * 1000,
            'maxPayload' => intval($_ENV['SOCKET_IO_MAX_PAYLOAD_SIZE'] ?? 1048576),
        ];

        // 发送握手响应
        $response = new Response(EnginePacket::createOpen($handshake)->encode());
        $response->headers->set('Content-Type', 'application/octet-stream');

        return $response;
    }

    /**
     * 分发Socket.IO事件
     */
    private function dispatchEvent(string $event, ?Socket $socket, array $data = []): void
    {
        $socketEvent = new SocketEvent($event, $socket, $data);
        $this->eventDispatcher->dispatch($socketEvent);
    }
}
