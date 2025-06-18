<?php

namespace SocketIoBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\SocketPacketType;
use SocketIoBundle\Event\SocketEvent;
use SocketIoBundle\Exception\InvalidPingException;
use SocketIoBundle\Exception\InvalidTransportException;
use SocketIoBundle\Exception\PingTimeoutException;
use SocketIoBundle\Protocol\EnginePacket;
use SocketIoBundle\Protocol\SocketPacket;
use SocketIoBundle\Repository\RoomRepository;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Transport\PollingTransport;
use SocketIoBundle\Transport\TransportInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Socket连接管理服务
 *
 * 负责管理 Socket.io 的连接生命周期，包括：
 * - 创建和维护连接
 * - 客户端身份绑定
 * - 连接状态更新
 * - 连接清理
 */
class SocketService
{
    /** @var array<string, TransportInterface> */
    private array $activeTransports = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SocketRepository $socketRepository,
        private readonly RoomRepository $roomRepository,
        private readonly RoomService $roomService,
        private readonly DeliveryService $deliveryService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * 创建新的Socket连接
     */
    public function createConnection(
        string $sessionId,
        string $socketId,
        string $transport = 'polling',
        string $namespace = '/',
    ): Socket {
        // 创建或更新Socket实体
        $socket = $this->socketRepository->findBySessionId($sessionId) ?? new Socket($sessionId, $socketId);
        $socket->setTransport($transport)
            ->setNamespace($namespace)
            ->setConnected(true)
            ->updatePingTime();

        $this->em->persist($socket);
        $this->em->flush();

        return $socket;
    }

    /**
     * 更新连接的最后活动时间
     *
     * @param Socket $socket 连接实体
     */
    public function updatePing(Socket $socket): void
    {
        $socket->updatePingTime();
        $this->em->flush();
    }

    /**
     * 断开连接
     *
     * @param Socket $socket 要断开的连接
     */
    public function disconnect(Socket $socket): void
    {
        // 从所有房间移除
        $this->roomService->leaveAllRooms($socket);

        $socket->setConnected(false);
        $this->em->flush();

        // 分发断开连接事件
        $this->eventDispatcher->dispatch(new SocketEvent('socket.disconnect', $socket));
    }

    /**
     * 清理不活跃的连接
     *
     * 删除所有已断开或超时的连接
     */
    public function cleanupInactiveConnections(): void
    {
        $this->socketRepository->cleanupInactiveConnections();
    }

    /**
     * 绑定客户端标识
     *
     * @param Socket $socket   连接实体
     * @param string $clientId 客户端标识
     */
    public function bindClientId(Socket $socket, string $clientId): void
    {
        $socket->setClientId($clientId);
        $this->em->flush();
    }

    /**
     * 根据客户端标识查找连接
     *
     * @param string $clientId 客户端标识
     *
     * @return Socket|null 找到的连接实体或null
     */
    public function findByClientId(string $clientId): ?Socket
    {
        return $this->socketRepository->findByClientId($clientId);
    }

    /**
     * 查找命名空间下的所有活跃连接
     *
     * @param string $namespace 命名空间
     *
     * @return Socket[] 连接数组
     */
    public function findActiveConnectionsByNamespace(string $namespace): array
    {
        return $this->socketRepository->findActiveConnectionsByNamespace($namespace);
    }

    /**
     * 获取连接的传输层实例
     *
     * 如果传输层不存在，会根据连接配置自动初始化
     */
    public function getTransport(Socket $socket): ?TransportInterface
    {
        $sessionId = $socket->getSessionId();

        // 如果传输层已存在且未过期，直接返回
        if (isset($this->activeTransports[$sessionId]) && !$this->activeTransports[$sessionId]->isExpired()) {
            return $this->activeTransports[$sessionId];
        }

        // 如果传输层不存在或已过期，创建新的传输层
        $transport = match ($socket->getTransport()) {
            'polling' => new PollingTransport($this->em, $this->socketRepository, $this->deliveryService, $socket, $sessionId),
            // 未来可以在这里添加其他传输类型的支持
            default => null,
        };

        if ($transport) {
            $this->activeTransports[$sessionId] = $transport;
            $transport->setPacketHandler(fn ($packet) => $this->handlePacket($socket, $packet));
        }

        return $transport;
    }

    /**
     * 发送ping包到客户端
     */
    public function sendPing(Socket $socket): void
    {
        $transport = $this->getTransport($socket);
        if ($transport) {
            $transport->send(EnginePacket::createPing()->encode());
        }
    }

    /**
     * 检查连接是否活跃，如果不活跃则抛出对应异常
     *
     * @param Socket $socket  连接实体
     * @param int    $timeout 超时时间（秒）
     *
     * @throws InvalidTransportException 当传输层无效时
     * @throws InvalidPingException      当最后ping时间无效时
     * @throws PingTimeoutException      当ping超时时
     */
    public function checkActive(Socket $socket, int $timeout = 30): void
    {
        $transport = $this->getTransport($socket);
        if (!$transport || $transport->isExpired()) {
            throw new InvalidTransportException($socket->getSessionId());
        }

        $lastPingTime = $socket->getLastPingTime();
        if (!$lastPingTime) {
            throw new InvalidPingException($socket->getSessionId());
        }

        $now = new \DateTime();
        $lastDeliverTime = $socket->getLastDeliverTime();

        // 检查最后一次 ping 时间和投递时间
        $pingDiff = $now->getTimestamp() - $lastPingTime->getTimestamp();
        $deliverDiff = $lastDeliverTime ? $now->getTimestamp() - $lastDeliverTime->getTimestamp() : 0;

        // 只有当 ping 和投递都超时时才抛出异常
        if ($pingDiff > $timeout && $deliverDiff > $timeout * 2) {
            throw new PingTimeoutException($socket->getSessionId(), $timeout, $lastPingTime, $now);
        }
    }

    /**
     * 生成符合Socket.IO规范的会话ID
     *
     * 生成一个20字符的随机标识符，确保其唯一性
     * 参考: https://socket.io/docs/v4/server-socket-instance/#socketid
     *
     * @return string 生成的会话ID
     */
    public function generateUniqueId(): string
    {
        do {
            // 生成10字节(20个字符)的随机数据
            $randomBytes = random_bytes(10);
            // 转换为16进制字符串，确保长度为20个字符
            $id = bin2hex($randomBytes);
            // 检查是否已存在
            $exists = $this->socketRepository->findBySessionId($id);
        } while ($exists);

        return $id;
    }

    /**
     * 处理收到的数据包
     */
    private function handlePacket(Socket $socket, SocketPacket $packet): void
    {
        // 更新最后活动时间
        $socket->updatePingTime();
        $this->em->flush();

        // 处理事件
        if (SocketPacketType::EVENT === $packet->getType()) {
            $data = json_decode($packet->getData(), true);
            if (!(bool) $data) {
                return;
            }
            $event = array_shift($data);

            // 分发事件
            $this->eventDispatcher->dispatch(new SocketEvent($event, $socket, $data));
        } elseif (SocketPacketType::DISCONNECT === $packet->getType()) {
            $this->disconnect($socket);

            // 分发断开连接事件
            $this->eventDispatcher->dispatch(new SocketEvent('socket.disconnect', $socket));
        }
    }
}
