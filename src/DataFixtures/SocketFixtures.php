<?php

namespace SocketIoBundle\DataFixtures;

use Doctrine\Persistence\ObjectManager;
use SocketIoBundle\Entity\Socket;

class SocketFixtures extends AppFixtures
{
    public const SOCKET_REFERENCE_PREFIX = 'socket_';
    public const SOCKET_COUNT = 20;

    public function load(ObjectManager $manager): void
    {
        for ($i = 0; $i < self::SOCKET_COUNT; ++$i) {
            $socket = $this->createSocket();
            $manager->persist($socket);
            $this->addReference(self::SOCKET_REFERENCE_PREFIX . $i, $socket);
        }

        $manager->flush();
    }

    private function createSocket(): Socket
    {
        $socketId = $this->generateSocketId();
        $sessionId = $this->generateSessionId();
        $socket = new Socket();
        $socket->setSessionId($sessionId);
        $socket->setSocketId($socketId);

        // 设置基本属性
        $socket->setNamespace($this->generateNamespace());

        // 随机设置一些客户端ID（有些连接可能没有客户端ID）
        if ($this->faker->boolean(70)) {
            $socket->setClientId($this->faker->userName());
        }

        // 设置握手数据
        $socket->setHandshake($this->generateHandshakeData());

        // 设置各种时间
        $createTime = $this->faker->dateTimeBetween('-30 days', '-1 day');
        $socket->setCreateTime(\DateTimeImmutable::createFromMutable($createTime));

        $lastPingTime = \DateTimeImmutable::createFromMutable($createTime);
        $lastPingTime = $lastPingTime->modify('+' . $this->faker->numberBetween(1, 1000) . ' minutes');
        $socket->setLastPingTime($lastPingTime);

        $lastActiveTime = clone $lastPingTime;
        $lastActiveTime->modify('+' . $this->faker->numberBetween(0, 120) . ' minutes');
        $socket->updateLastActiveTime(); // 使用当前时间

        // 轮询次数
        $socket->incrementPollCount(); // 初始为1
        for ($i = 0; $i < $this->faker->numberBetween(0, 50); ++$i) {
            $socket->incrementPollCount();
        }

        // 连接状态 - 大多数是已连接状态
        $socket->setConnected($this->faker->boolean(80));

        // 设置传输类型
        $transportElement = $this->faker->randomElement(['polling', 'websocket']);
        $socket->setTransport(is_string($transportElement) ? $transportElement : 'polling');

        return $socket;
    }

    /**
     * @return array<string, mixed>
     */
    private function generateHandshakeData(): array
    {
        return [
            'headers' => [
                'user-agent' => $this->faker->userAgent(),
                'accept-language' => $this->faker->randomElement(['zh-CN,zh;q=0.9,en;q=0.8', 'en-US,en;q=0.8', 'ja-JP,ja;q=0.9']),
                'origin' => $this->faker->randomElement(['https://app.local', 'https://localhost:3000', 'https://app.domain.com']),
                'referer' => $this->faker->url(),
            ],
            'address' => [
                'remoteAddress' => $this->faker->ipv4(),
                'remotePort' => $this->faker->numberBetween(30000, 65000),
            ],
            'query' => [
                'EIO' => '4',
                'transport' => 'polling',
                't' => $this->faker->regexify('[a-zA-Z0-9]{10}'),
            ],
            'time' => $this->faker->dateTimeThisMonth()->format('c'),
            'secure' => $this->faker->boolean(70),
        ];
    }
}
