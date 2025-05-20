<?php

namespace SocketIoBundle\DataFixtures;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;

class DeliveryFixtures extends AppFixtures implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // 为每条消息创建投递记录
        $totalMessages = MessageFixtures::MESSAGE_COUNT + 10; // 普通消息 + 系统消息

        for ($i = 0; $i < $totalMessages; $i++) {
            /** @var Message $message */
            $message = $this->getReference(MessageFixtures::MESSAGE_REFERENCE_PREFIX . $i, Message::class);

            // 获取与该消息关联的房间里的所有Socket
            $targetSockets = $this->getSocketsForMessage($message);

            // 为找到的每个Socket创建一个投递记录
            foreach ($targetSockets as $socket) {
                $delivery = $this->createDelivery($message, $socket);
                $manager->persist($delivery);
            }
        }

        $manager->flush();
    }

    /**
     * 获取消息应该被投递到的所有Socket
     */
    private function getSocketsForMessage(Message $message): array
    {
        $targetSockets = [];
        $socketIdsMap = []; // 用于去重

        $rooms = $message->getRooms();

        if ($rooms->isEmpty()) {
            // 如果消息没有关联到任何房间，则跳过
            return [];
        }

        // 遍历消息关联的所有房间
        foreach ($rooms as $room) {
            // 获取该房间中的所有Socket
            $sockets = $room->getSockets();

            foreach ($sockets as $socket) {
                $socketId = $socket->getId();

                // 避免重复添加相同的Socket
                if (!isset($socketIdsMap[$socketId])) {
                    $socketIdsMap[$socketId] = true;
                    $targetSockets[] = $socket;
                }
            }
        }

        return $targetSockets;
    }

    /**
     * 创建一条投递记录
     */
    private function createDelivery(Message $message, Socket $socket): Delivery
    {
        $delivery = new Delivery();
        $delivery->setMessage($message);
        $delivery->setSocket($socket);

        // 设置状态 - 大部分是已投递，一部分是失败，少量是待处理
        $statusRandom = $this->faker->numberBetween(1, 100);
        if ($statusRandom <= 80) {
            $delivery->setStatus(MessageStatus::DELIVERED);
            // 设置投递时间
            $deliveredAt = clone $message->getCreateTime();
            $deliveredAt = new \DateTime('@' . ($deliveredAt->getTimestamp() + $this->faker->numberBetween(1, 60)));
            $socket->setLastDeliverTime($deliveredAt);
        } elseif ($statusRandom <= 95) {
            $delivery->setStatus(MessageStatus::FAILED);
            $delivery->setError($this->generateError());
        } else {
            $delivery->setStatus(MessageStatus::PENDING);
        }

        // 设置重试次数
        if (!$delivery->isDelivered()) {
            $retries = $this->faker->numberBetween(0, 3);
            for ($i = 0; $i < $retries; $i++) {
                $delivery->incrementRetries();
            }
        }

        // 设置创建时间和更新时间
        $createTime = clone $message->getCreateTime();
        $delivery->setCreateTime($createTime);

        return $delivery;
    }

    /**
     * 生成随机错误消息
     */
    private function generateError(): string
    {
        $errors = [
            'Connection closed',
            'Socket disconnected',
            'Timeout waiting for ack',
            'Client unreachable',
            'Namespace mismatch',
            'Rate limit exceeded',
            'Permission denied',
            'Message too large',
            'Invalid payload format',
            'Internal server error',
            'Network error',
            'Client error: Invalid transport',
            'Transport error: cannot deliver message',
            'Client abort'
        ];

        $index = array_rand($errors);
        return $errors[$index];
    }

    public function getDependencies(): array
    {
        return [
            SocketFixtures::class,
            RoomFixtures::class,
            MessageFixtures::class,
        ];
    }
} 