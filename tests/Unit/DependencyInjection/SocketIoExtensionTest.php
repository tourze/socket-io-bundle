<?php

namespace SocketIoBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\DependencyInjection\SocketIoExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class SocketIoExtensionTest extends TestCase
{
    public function testExtensionCanBeInstantiated(): void
    {
        $extension = new SocketIoExtension();
        $this->assertInstanceOf(SocketIoExtension::class, $extension);
    }

    public function testExtensionCanLoadConfiguration(): void
    {
        $extension = new SocketIoExtension();
        $container = new ContainerBuilder();
        
        $extension->load([], $container);
        
        $this->assertInstanceOf(ContainerBuilder::class, $container);
    }
}