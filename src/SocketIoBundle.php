<?php

namespace SocketIoBundle;

use ChrisUllyott\FileSize;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\DoctrineIndexedBundle\DoctrineIndexedBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\EasyAdminMenuBundle\EasyAdminMenuBundle;
use Tourze\RoutingAutoLoaderBundle\RoutingAutoLoaderBundle;

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
            DoctrineBundle::class => ['all' => true],
            DoctrineSnowflakeBundle::class => ['all' => true],
            DoctrineIndexedBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            EasyAdminMenuBundle::class => ['all' => true],
            RoutingAutoLoaderBundle::class => ['all' => true],
        ];
    }
}
