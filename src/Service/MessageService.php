<?php

namespace SocketIoBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Repository\DeliveryRepository;
use SocketIoBundle\Repository\MessageRepository;
use SocketIoBundle\Repository\SocketRepository;

/**
 * 消息管理服务
 *
 * 负责管理 Socket.io 的消息功能，包括：
 * - 消息的创建和发送
 * - 消息投递状态管理
 * - 消息历史记录
 * - 消息清理
 */
class MessageService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $messageRepository,
        private readonly DeliveryRepository $deliveryRepository,
        private readonly RoomService $roomService,
        private readonly SocketRepository $socketRepository,
    ) {
    }

    /**
     * 广播消息到所有连接
     */
    public function broadcast(string $event, array $data, ?Socket $sender = null): int
    {
        $message = $this->createMessage($event, $data, $sender);
        $activeSockets = $this->socketRepository->findActiveConnections();

        $c = 0;
        foreach ($activeSockets as $socket) {
            $this->dispatchMessageToSocket($message, $socket);
            ++$c;
        }

        return $c;
    }

    /**
     * 发送消息到多个房间
     */
    public function sendToRooms(array $rooms, string $event, array $data, ?Socket $sender = null): void
    {
        $message = $this->createMessage($event, $data, $sender);

        foreach ($rooms as $room) {
            $message->addRoom($room instanceof Room ? $room : $this->roomService->findOrCreateRoom($room));
        }

        $sockets = [];
        foreach ($message->getRooms() as $room) {
            foreach ($room->getSockets() as $socket) {
                if (isset($sockets[$socket->getSocketId()])) {
                    continue;
                }
                if ($socket === $message->getSender()) {
                    continue;
                }
                $sockets[$socket->getSocketId()] = $socket;
            }
        }

        foreach ($sockets as $socket) {
            $this->dispatchMessageToSocket($message, $socket);
        }

        $this->em->flush();
    }

    public function sendToSocket(Socket $socket, string $event, array $data, ?Socket $sender = null): void
    {
        $message = $this->createMessage($event, $data, $sender);
        $this->dispatchMessageToSocket($message, $socket);
    }

    /**
     * 发送指定消息实例到指定连接
     *
     * @param Message $message 消息实体
     * @param Socket  $socket  目标连接
     */
    public function dispatchMessageToSocket(Message $message, Socket $socket): void
    {
        if (!$socket->isConnected()) {
            return;
        }

        $this->createDelivery($message, $socket);
        $this->em->flush();
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
     * 标记消息投递失败
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
     * 获取待投递的消息
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
     * 获取房间的消息历史
     *
     * @param Room     $room   房间实体
     * @param int      $limit  返回数量限制
     * @param int|null $before 指定消息ID之前的消息
     *
     * @return Message[] 消息历史数组
     */
    public function getMessageHistory(Room $room, int $limit = 50, ?int $before = null): array
    {
        return $this->messageRepository->findRoomMessages($room, $limit, $before);
    }

    /**
     * 清理旧消息
     *
     * @param int $days 保留天数
     */
    public function cleanupOldMessages(int $days = 30): void
    {
        $this->messageRepository->cleanupOldMessages($days);
    }

    /**
     * 清理旧的投递记录
     *
     * @param int $days 保留天数
     */
    public function cleanupOldDeliveries(int $days = 7): void
    {
        $this->deliveryRepository->cleanupOldDeliveries($days);
    }

    /**
     * 创建新消息
     *
     * @param string      $event  事件名称
     * @param array       $data   消息数据
     * @param Socket|null $sender 发送者连接
     *
     * @return Message 新创建的消息实体
     */
    private function createMessage(string $event, array $data, ?Socket $sender = null): Message
    {
        $message = new Message();
        $message->setEvent($event)
            ->setData($data)
            ->setSender($sender);

        $this->em->persist($message);
        $this->em->flush();

        return $message;
    }

    /**
     * 创建消息投递记录
     *
     * @param Message $message 消息实体
     * @param Socket  $socket  目标连接
     *
     * @return Delivery 新创建的投递记录
     */
    private function createDelivery(Message $message, Socket $socket): Delivery
    {
        $delivery = new Delivery();
        $delivery->setMessage($message)
            ->setSocket($socket)
            ->setStatus(MessageStatus::PENDING);

        $this->em->persist($delivery);

        return $delivery;
    }
}
