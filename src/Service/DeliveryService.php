<?php

namespace SocketIoBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Protocol\SocketPacket;
use SocketIoBundle\Repository\DeliveryRepository;
use SocketIoBundle\Repository\RoomRepository;
use SocketIoBundle\Repository\SocketRepository;

/**
 * 消息投递服务
 *
 * 负责管理消息的投递过程，包括：
 * - 消息队列管理
 * - 投递状态跟踪
 * - 重试机制
 * - 失败处理
 * - 投递记录清理
 */
class DeliveryService
{
    private const MAX_RETRIES = 3;

    private const MAX_QUEUE_SIZE = 1000;

    private const QUEUE_CLEANUP_AGE = 300; // 5 minutes

    /** @var array<string, array<array{packet: SocketPacket, senderId: string, timestamp: float}>> */
    private array $queues = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeliveryRepository $deliveryRepository,
        private readonly RoomRepository $roomRepository,
        private readonly SocketRepository $socketRepository,
    ) {
    }

    /**
     * 将消息加入队列
     */
    public function enqueue(string $roomName, SocketPacket $packet, string $senderId): void
    {
        // 创建房间队列
        if (!isset($this->queues[$roomName])) {
            $this->queues[$roomName] = [];
        }

        // 添加消息到队列
        $this->queues[$roomName][] = [
            'packet' => $packet,
            'senderId' => $senderId,
            'timestamp' => microtime(true),
        ];

        // 如果队列过大，移除旧消息
        if (count($this->queues[$roomName]) > self::MAX_QUEUE_SIZE) {
            array_shift($this->queues[$roomName]);
        }

        // 持久化消息
        $this->persistMessage($roomName, $packet, $senderId);
    }

    /**
     * 从队列获取消息
     */
    public function dequeue(string $roomName, float $since = 0): array
    {
        if (!isset($this->queues[$roomName])) {
            // 使用实体管理器查询消息
            $room = $this->roomRepository->findByName($roomName);
            if (!$room) {
                return [];
            }
            
            $qb = $this->em->createQueryBuilder();
            $qb->select('m')
               ->from(Message::class, 'm')
               ->innerJoin('m.rooms', 'r')
               ->where('r.id = :roomId')
               ->andWhere('m.createTime > :since')
               ->setParameter('roomId', $room->getId())
               ->setParameter('since', new \DateTimeImmutable('@' . intval($since)));
               
            $messages = $qb->getQuery()->getResult();
            
            $result = [];
            foreach ($messages as $message) {
                $result[] = [
                    'packet' => new SocketPacket(
                        null, 
                        $message->getEvent(), 
                        json_encode(array_merge([$message->getEvent()], $message->getData()))
                    ),
                    'senderId' => $message->getSender() ? $message->getSender()->getSocketId() : null,
                    'timestamp' => $message->getCreateTime()->getTimestamp(),
                ];
            }
            
            return $result;
        }

        // 获取指定时间之后的消息
        return array_filter(
            $this->queues[$roomName],
            fn ($msg) => $msg['timestamp'] > $since
        );
    }

    /**
     * 清理旧消息队列
     */
    public function cleanupQueues(): void
    {
        // 清理内存中的队列
        $now = microtime(true);
        foreach ($this->queues as $roomName => &$queue) {
            $queue = array_filter(
                $queue,
                fn ($msg) => ($now - $msg['timestamp']) <= self::QUEUE_CLEANUP_AGE
            );
        }
        
        // 清理数据库中的旧投递记录
        // 这里使用已有的 cleanupOldDeliveries 方法
        $this->cleanupDeliveries();
    }

    /**
     * 获取连接的待投递消息
     *
     * @param Socket $socket 连接实体
     *
     * @return Delivery[] 待投递的消息数组
     */
    public function getPendingDeliveries(Socket $socket): array
    {
        return $this->deliveryRepository->findPendingDeliveries($socket);
    }

    /**
     * 标记消息已投递
     *
     * @param Delivery $delivery 投递记录
     */
    public function markDelivered(Delivery $delivery): void
    {
        $delivery->setStatus(MessageStatus::DELIVERED);
        $this->em->flush();
    }

    /**
     * 重试投递消息
     *
     * 如果超过最大重试次数，会标记为失败
     *
     * @param Delivery $delivery 投递记录
     *
     * @return bool 是否可以继续重试
     */
    public function retry(Delivery $delivery): bool
    {
        if ($delivery->getRetries() >= self::MAX_RETRIES) {
            $delivery->setStatus(MessageStatus::FAILED)
                ->setError('Max retries exceeded');
            $this->em->flush();

            return false;
        }

        $delivery->incrementRetries();
        $this->em->flush();

        return true;
    }

    /**
     * 标记投递失败
     *
     * @param Delivery $delivery 投递记录
     * @param string   $error    错误信息
     */
    public function markFailed(Delivery $delivery, string $error): void
    {
        $delivery->setStatus(MessageStatus::FAILED)
            ->setError($error);
        $this->em->flush();
    }

    /**
     * 清理旧的投递记录
     *
     * @param int $days 保留天数
     *
     * @return int 删除的记录数
     */
    public function cleanupDeliveries(int $days = 7): int
    {
        return $this->deliveryRepository->cleanupOldDeliveries($days);
    }

    /**
     * 持久化消息到数据库
     */
    private function persistMessage(string $roomName, SocketPacket $packet, string $senderId): void
    {
        $room = $this->roomRepository->findByName($roomName);
        if (!$room) {
            return;
        }

        // 创建消息记录
        $message = new Message();
        $message->setEvent($packet->getData() ? json_decode($packet->getData(), true)[0] : '')
            ->setData($packet->getData() ? array_slice(json_decode($packet->getData(), true), 1) : [])
            ->setSender($this->socketRepository->findBySessionId($senderId))
            ->setMetadata([
                'namespace' => $packet->getNamespace(),
                'messageId' => $packet->getId(),
            ]);
        $message->addRoom($room);
        $this->em->persist($message);

        // 创建投递记录
        foreach ($room->getSockets() as $socket) {
            if ($socket->getSessionId() !== $senderId) {
                $delivery = new Delivery();
                $delivery->setMessage($message)
                    ->setSocket($socket)
                    ->setStatus(MessageStatus::PENDING);
                $this->em->persist($delivery);
            }
        }

        $this->em->flush();
    }
}
