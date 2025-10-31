<?php

namespace SocketIoBundle\DataFixtures;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;

class MessageFixtures extends AppFixtures implements DependentFixtureInterface
{
    public const MESSAGE_REFERENCE_PREFIX = 'message_';
    public const MESSAGE_COUNT = 50;

    public function load(ObjectManager $manager): void
    {
        $this->createUserMessages($manager);
        $this->createSystemMessages($manager);
        $manager->flush();
    }

    private function createUserMessages(ObjectManager $manager): void
    {
        for ($i = 0; $i < self::MESSAGE_COUNT; ++$i) {
            $sender = $this->getRandomSocket();
            $message = $this->createMessage($sender);
            $this->assignRandomRooms($message, $sender);

            $manager->persist($message);
            $this->addReference(self::MESSAGE_REFERENCE_PREFIX . $i, $message);
        }
    }

    private function createSystemMessages(ObjectManager $manager): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $message = $this->createSystemMessage();
            $this->assignRandomRoomsForSystemMessage($message);

            $manager->persist($message);
            $this->addReference(self::MESSAGE_REFERENCE_PREFIX . (self::MESSAGE_COUNT + $i), $message);
        }
    }

    private function getRandomSocket(): Socket
    {
        $senderIndex = $this->faker->numberBetween(0, SocketFixtures::SOCKET_COUNT - 1);

        return $this->getReference(SocketFixtures::SOCKET_REFERENCE_PREFIX . $senderIndex, Socket::class);
    }

    private function assignRandomRooms(Message $message, Socket $sender): void
    {
        $roomIndices = $this->getRandomRoomIndices(1, 3);

        foreach ($roomIndices as $roomIndex) {
            $room = $this->getReference(RoomFixtures::ROOM_REFERENCE_PREFIX . (string) $roomIndex, Room::class);

            if ($room->getNamespace() === $sender->getNamespace() || '/' === $room->getNamespace()) {
                $message->addRoom($room);
            }
        }
    }

    private function assignRandomRoomsForSystemMessage(Message $message): void
    {
        $roomIndices = $this->getRandomRoomIndices(1, 2);

        foreach ($roomIndices as $roomIndex) {
            $room = $this->getReference(RoomFixtures::ROOM_REFERENCE_PREFIX . (string) $roomIndex, Room::class);
            $message->addRoom($room);
        }
    }

    /**
     * @return array<int>
     */
    private function getRandomRoomIndices(int $minCount, int $maxCount): array
    {
        $totalRooms = RoomFixtures::ROOM_COUNT + 5;
        $roomCount = $this->faker->numberBetween($minCount, $maxCount);

        $allRoomIndices = range(0, $totalRooms - 1);
        shuffle($allRoomIndices);

        $roomIndices = [];
        for ($j = 0; $j < min($roomCount, count($allRoomIndices)); ++$j) {
            $roomIndices[] = $allRoomIndices[$j];
        }

        return $roomIndices;
    }

    private function createMessage(Socket $sender): Message
    {
        $message = new Message();
        $message->setEvent($this->generateEventName());
        $message->setData($this->generateMessageData());
        $message->setSender($sender);

        // 设置一些可选的元数据
        if ($this->faker->boolean(70)) {
            $message->setMetadata($this->generateMessageMetadata());
        }

        // 设置创建时间
        $createTime = $this->faker->dateTimeBetween('-30 days', 'now');
        $message->setCreateTime(\DateTimeImmutable::createFromMutable($createTime));

        return $message;
    }

    private function createSystemMessage(): Message
    {
        $message = new Message();

        // 系统消息通常有特定的事件类型
        $systemEvents = [
            'system:notification', 'system:broadcast', 'system:alert',
            'system:maintenance', 'system:status', 'system:update',
        ];

        $eventElement = $this->faker->randomElement($systemEvents);
        $message->setEvent(is_string($eventElement) ? $eventElement : 'system:notification');
        $message->setData($this->generateSystemMessageData());
        $message->setSender(null); // 系统消息没有发送者

        // 设置元数据
        $message->setMetadata([
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'system' => true,
            'broadcast' => $this->faker->boolean(80),
            'persistent' => $this->faker->boolean(50),
        ]);

        // 设置创建时间
        $createTime = $this->faker->dateTimeBetween('-30 days', 'now');
        $message->setCreateTime(\DateTimeImmutable::createFromMutable($createTime));

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateMessageData(): array
    {
        $type = $this->faker->randomElement(['text', 'notification', 'status', 'action', 'data']);

        switch ($type) {
            case 'text':
                return [
                    'type' => 'text',
                    'content' => $this->faker->sentence(),
                ];

            case 'notification':
                return [
                    'type' => 'notification',
                    'title' => $this->faker->words(3, true),
                    'body' => $this->faker->sentence(),
                    'icon' => $this->faker->randomElement(['info', 'success', 'warning', 'error']),
                    'autoClose' => $this->faker->boolean(70),
                ];

            case 'status':
                return [
                    'type' => 'status',
                    'online' => $this->faker->boolean(80),
                    'lastSeen' => $this->faker->dateTimeThisMonth()->format('c'),
                    'activity' => $this->faker->randomElement(['active', 'idle', 'away', 'offline']),
                ];

            case 'action':
                return [
                    'type' => 'action',
                    'action' => $this->faker->randomElement(['join', 'leave', 'typing', 'read', 'clicked']),
                    'target' => $this->faker->word(),
                    'timestamp' => $this->faker->unixTime(),
                ];

            case 'data':
            default:
                return [
                    'type' => 'data',
                    'payload' => $this->generateJsonData(3),
                ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function generateSystemMessageData(): array
    {
        $types = ['alert', 'broadcast', 'notification', 'maintenance'];
        $type = $this->faker->randomElement($types);

        switch ($type) {
            case 'alert':
                return [
                    'type' => 'alert',
                    'title' => '系统提醒',
                    'message' => $this->faker->sentence(),
                    'level' => $this->faker->randomElement(['info', 'warning', 'error', 'critical']),
                    'timestamp' => time(),
                ];

            case 'broadcast':
                return [
                    'type' => 'broadcast',
                    'title' => '系统公告',
                    'content' => $this->faker->paragraph(),
                    'sender' => 'System',
                    'persistent' => $this->faker->boolean(),
                ];

            case 'notification':
                return [
                    'type' => 'notification',
                    'title' => $this->faker->words(3, true),
                    'body' => $this->faker->sentence(),
                    'action' => $this->faker->randomElement(['reload', 'redirect', 'update', null]),
                    'icon' => 'system',
                ];

            case 'maintenance':
            default:
                return [
                    'type' => 'maintenance',
                    'scheduled' => $this->faker->boolean(80),
                    'startTime' => $this->faker->dateTimeBetween('now', '+2 days')->format('c'),
                    'duration' => $this->faker->numberBetween(5, 120) . ' minutes',
                    'message' => '系统将进行维护，期间服务可能不可用',
                ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function generateMessageMetadata(): array
    {
        return [
            'clientTimestamp' => $this->faker->unixTime(),
            'clientId' => $this->faker->regexify('[a-z0-9]{8}'),
            'priority' => $this->faker->randomElement([1, 2, 3, 4, 5]),
            'deliveryAttempts' => $this->faker->numberBetween(0, 3),
            'userAgent' => $this->faker->userAgent(),
            'ip' => $this->faker->ipv4(),
        ];
    }

    public function getDependencies(): array
    {
        return [
            SocketFixtures::class,
            RoomFixtures::class,
        ];
    }
}
