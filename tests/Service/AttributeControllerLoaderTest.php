<?php

namespace SocketIoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Service\AttributeControllerLoader;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Routing\RouteCollection;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses]
final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
    }

    private function getAttributeControllerLoader(): AttributeControllerLoader
    {
        return self::getService(AttributeControllerLoader::class);
    }

    public function testClassExists(): void
    {
        $loader = $this->getAttributeControllerLoader();
        $this->assertInstanceOf(AttributeControllerLoader::class, $loader);
    }

    public function testImplementsLoaderInterface(): void
    {
        $loader = $this->getAttributeControllerLoader();
        $this->assertInstanceOf(LoaderInterface::class, $loader);
    }

    public function testSupportsReturnsFalse(): void
    {
        $loader = $this->getAttributeControllerLoader();
        $this->assertFalse($loader->supports('any_resource', 'any_type'));
    }

    public function testLoadReturnsAutoloadResult(): void
    {
        $loader = $this->getAttributeControllerLoader();
        $result = $loader->load('any_resource', 'any_type');

        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    public function testAutoloadReturnsRouteCollection(): void
    {
        $loader = $this->getAttributeControllerLoader();
        $result = $loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    public function testAutoloadAddsControllerRoutes(): void
    {
        $loader = $this->getAttributeControllerLoader();
        $result = $loader->autoload();

        $this->assertInstanceOf(RouteCollection::class, $result);

        // 验证路由集合不为空
        $this->assertGreaterThan(0, $result->count());
    }

    public function testAutoconfigureTagIsPresent(): void
    {
        $reflectionClass = new \ReflectionClass(AttributeControllerLoader::class);
        $attributes = $reflectionClass->getAttributes(AutoconfigureTag::class);

        $this->assertCount(1, $attributes);

        $attribute = $attributes[0]->newInstance();
        $this->assertInstanceOf(AutoconfigureTag::class, $attribute);
    }
}
