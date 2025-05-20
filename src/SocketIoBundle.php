<?php

namespace SocketIoBundle;

use ChrisUllyott\FileSize;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;

class SocketIoBundle extends Bundle implements BundleDependencyInterface
{
    public function boot(): void
    {
        parent::boot();

        $_ENV['SOCKET_IO_MAX_PAYLOAD_SIZE'] = (new FileSize('10M'))->as('B'); // 1MB
        $_ENV['SOCKET_IO_PING_INTERVAL'] = '25'; // 25s
        $_ENV['SOCKET_IO_PING_TIMEOUT'] = '20'; // 20s
    }

    public static function getBundleDependencies(): array
    {
        return [
            \Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle::class => ['all' => true],
            \Tourze\DoctrineEntityCheckerBundle\DoctrineEntityCheckerBundle::class => ['all' => true],
            \Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle::class => ['all' => true],
        ];
    }
}
