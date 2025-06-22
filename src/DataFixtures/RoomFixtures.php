<?php

namespace SocketIoBundle\DataFixtures;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;

class RoomFixtures extends AppFixtures implements DependentFixtureInterface
{
    public const ROOM_REFERENCE_PREFIX = 'room_';
    public const ROOM_COUNT = 15;

    public function load(ObjectManager $manager): void
    {
        $rooms = [];

        // 创建房间
        for ($i = 0; $i < self::ROOM_COUNT; $i++) {
            $room = $this->createRoom();
            $manager->persist($room);
            $this->addReference(self::ROOM_REFERENCE_PREFIX . $i, $room);
            $rooms[] = $room;
        }

        // 创建特殊房间（常见房间名称）
        $specialRooms = [
            ['name' => 'chat', 'namespace' => '/'],
            ['name' => 'lobby', 'namespace' => '/'],
            ['name' => 'notifications', 'namespace' => '/'],
            ['name' => 'general', 'namespace' => '/chat'],
            ['name' => 'support', 'namespace' => '/admin'],
        ];

        foreach ($specialRooms as $index => $data) {
            $room = new Room($data['name'], $data['namespace']);
            $room->setMetadata($this->generateRoomMetadata());
            $manager->persist($room);
            $this->addReference(self::ROOM_REFERENCE_PREFIX . (self::ROOM_COUNT + $index), $room);
            $rooms[] = $room;
        }

        // 将Socket添加到房间
        for ($i = 0; $i < SocketFixtures::SOCKET_COUNT; $i++) {
            $socket = $this->getReference(SocketFixtures::SOCKET_REFERENCE_PREFIX . $i, Socket::class);

            // 每个Socket加入1-4个随机房间
            $roomCount = $this->faker->numberBetween(1, 4);

            // 随机选择房间
            shuffle($rooms);
            $selectedRooms = array_slice($rooms, 0, min($roomCount, count($rooms)));

            foreach ($selectedRooms as $room) {
                if ($room->getNamespace() === $socket->getNamespace() || $room->getNamespace() === '/') {
                    $socket->joinRoom($room);
                }
            }
        }

        $manager->flush();
    }

    private function createRoom(): Room
    {
        $namespace = $this->generateNamespace();
        $name = $this->generateRoomName();

        $room = new Room($name, $namespace);
        $room->setMetadata($this->generateRoomMetadata());

        // 设置创建时间
        $createdAt = $this->faker->dateTimeBetween('-60 days', '-1 day');
        $room->setCreateTime(\DateTimeImmutable::createFromMutable($createdAt));

        return $room;
    }

    private function generateRoomName(): string
    {
        $prefix = $this->faker->randomElement(['room', 'channel', 'group', 'team', '', '']);
        $name = $this->faker->word();

        if ($this->faker->boolean(30)) {
            $name .= '-' . $this->faker->numberBetween(1, 999);
        }

        return $prefix !== '' ? "{$prefix}-{$name}" : $name;
    }

    private function generateRoomMetadata(): array
    {
        $types = ['public', 'private', 'protected', 'ephemeral'];

        return [
            'type' => $this->faker->randomElement($types),
            'description' => $this->faker->sentence(),
            'maxUsers' => $this->faker->numberBetween(10, 1000),
            'tags' => $this->faker->words($this->faker->numberBetween(0, 5)),
            'created_by' => $this->faker->userName(),
            'settings' => [
                'persistent' => $this->faker->boolean(80),
                'autoDelete' => $this->faker->boolean(20),
                'joinable' => $this->faker->boolean(90),
            ]
        ];
    }

    public function getDependencies(): array
    {
        return [
            SocketFixtures::class,
        ];
    }
}
