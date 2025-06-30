<?php

namespace SocketIoBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\SocketIoBundle;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SocketIoBundleTest extends TestCase
{
    public function testBundleCanBeInstantiated(): void
    {
        $bundle = new SocketIoBundle();
        $this->assertInstanceOf(SocketIoBundle::class, $bundle);
    }

    public function testBundleCanBeBuilt(): void
    {
        $bundle = new SocketIoBundle();
        $container = new ContainerBuilder();
        
        $bundle->build($container);
        
        $this->assertInstanceOf(ContainerBuilder::class, $container);
    }
}