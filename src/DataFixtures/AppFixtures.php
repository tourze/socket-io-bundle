<?php

namespace SocketIoBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

abstract class AppFixtures extends Fixture
{
    protected Generator $faker;

    public function __construct()
    {
        $this->faker = Factory::create('zh_CN');
    }

    abstract public function load(ObjectManager $manager): void;

    /**
     * 生成随机JSON数据
     */
    protected function generateJsonData(int $fieldsCount = 5): array
    {
        $data = [];
        for ($i = 0; $i < $fieldsCount; $i++) {
            $key = $this->faker->word();
            $type = $this->faker->numberBetween(1, 4);

            $value = match ($type) {
                1 => $this->faker->word(),
                2 => $this->faker->numberBetween(1, 1000),
                3 => $this->faker->boolean(),
                4 => [
                    $this->faker->word() => $this->faker->word(),
                    $this->faker->word() => $this->faker->numberBetween(1, 100)
                ],
                default => $this->faker->word() // 处理其他情况
            };

            $data[$key] = $value;
        }

        return $data;
    }

    /**
     * 生成Socket.IO事件名
     */
    protected function generateEventName(): string
    {
        $eventTypes = [
            'connection', 'disconnect', 'message', 'chat',
            'join', 'leave', 'notification', 'update',
            'sync', 'ping', 'pong', 'error'
        ];

        $prefix = $this->faker->randomElement(['server', 'client', 'room', 'user', 'system', '']);
        $suffix = $this->faker->randomElement($eventTypes);

        return $prefix !== '' ? "{$prefix}:{$suffix}" : $suffix;
    }

    /**
     * 生成Socket.ID命名空间
     */
    protected function generateNamespace(): string
    {
        $namespaces = ['/', '/chat', '/notification', '/admin', '/game', '/api'];
        return $this->faker->randomElement($namespaces);
    }

    /**
     * 生成伪随机 Socket.IO ID，格式类似实际Socket.IO格式
     */
    protected function generateSocketId(): string
    {
        return $this->faker->regexify('[a-zA-Z0-9]{20}');
    }

    /**
     * 生成伪随机会话ID
     */
    protected function generateSessionId(): string
    {
        return $this->faker->uuid();
    }
}
