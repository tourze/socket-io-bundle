<?php

namespace SocketIoBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use SocketIoBundle\DependencyInjection\SocketIoExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(SocketIoExtension::class)]
final class SocketIoExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    public function testGetConfigDirReturnsCorrectPath(): void
    {
        $extension = new SocketIoExtension();
        $reflection = new \ReflectionClass($extension);
        $method = $reflection->getMethod('getConfigDir');
        $method->setAccessible(true);

        $configDir = $method->invoke($extension);
        $this->assertIsString($configDir);

        $this->assertStringEndsWith('/Resources/config', $configDir);
        $this->assertDirectoryExists($configDir);
    }

    public function testExtensionCanLoadConfiguration(): void
    {
        $extension = new SocketIoExtension();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        $extension->load([], $container);

        $this->assertInstanceOf(ContainerBuilder::class, $container);
    }

    public function testLoadMethodLoadsServicesConfiguration(): void
    {
        $extension = new SocketIoExtension();
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');

        $definitionsBefore = count($container->getDefinitions());

        $extension->load([], $container);

        $definitionsAfter = count($container->getDefinitions());

        $this->assertGreaterThan($definitionsBefore, $definitionsAfter, 'load() method should add service definitions to the container');
    }
}
