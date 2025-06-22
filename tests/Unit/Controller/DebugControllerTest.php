<?php

namespace SocketIoBundle\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Controller\DebugController;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class DebugControllerTest extends TestCase
{
    private DebugController $controller;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = $this->createMock(Environment::class);
        $this->controller = new DebugController();
        
        // 使用反射设置Twig环境
        $reflection = new \ReflectionClass($this->controller);
        $property = $reflection->getProperty('container');
        $property->setAccessible(true);
        
        $container = $this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->expects($this->any())->method('get')->with('twig')->willReturn($this->twig);
        $container->expects($this->any())->method('has')->with('twig')->willReturn(true);
        
        $property->setValue($this->controller, $container);
    }

    public function test_extends_abstract_controller(): void
    {
        $this->assertInstanceOf(AbstractController::class, $this->controller);
    }

    public function test_invoke_method_can_be_called(): void
    {
        $this->twig->expects($this->once())
            ->method('render')
            ->with('@SocketIo/debug.html.twig', [])
            ->willReturn('<html><body>Debug Page</body></html>');

        $response = $this->controller->__invoke();
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<html><body>Debug Page</body></html>', $response->getContent());
    }

    public function test_invoke_method_is_public(): void
    {
        $reflection = new \ReflectionMethod($this->controller, '__invoke');
        $this->assertTrue($reflection->isPublic());
    }

    public function test_invoke_method_returns_response(): void
    {
        $this->twig->expects($this->once())
            ->method('render')
            ->with('@SocketIo/debug.html.twig', [])
            ->willReturn('<html><body>Debug Page</body></html>');

        $response = $this->controller->__invoke();

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('<html><body>Debug Page</body></html>', $response->getContent());
    }

    public function test_invoke_method_renders_correct_template(): void
    {
        $this->twig->expects($this->once())
            ->method('render')
            ->with('@SocketIo/debug.html.twig', [])
            ->willReturn('debug content');

        $this->controller->__invoke();
    }

    public function test_invoke_method_returns_200_status(): void
    {
        $this->twig->expects($this->once())
            ->method('render')
            ->with('@SocketIo/debug.html.twig', [])
            ->willReturn('debug content');

        $response = $this->controller->__invoke();

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function test_invoke_method_has_route_attribute(): void
    {
        $reflection = new \ReflectionMethod($this->controller, '__invoke');
        $attributes = $reflection->getAttributes();
        
        $this->assertNotEmpty($attributes);
        
        $routeAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Symfony\Component\Routing\Attribute\Route') {
                $routeAttribute = $attribute;
                break;
            }
        }
        
        $this->assertNotNull($routeAttribute, 'Route attribute should be present');
        
        $arguments = $routeAttribute->getArguments();
        $this->assertSame('/socket-io/debug', $arguments[0]);
        $this->assertSame('socket_io_debug', $arguments['name']);
    }

    public function test_controller_class_structure(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        
        $this->assertSame('SocketIoBundle\Controller\DebugController', $reflection->getName());
        $this->assertTrue($reflection->isSubclassOf(AbstractController::class));
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
    }

    public function test_invoke_method_signature(): void
    {
        $reflection = new \ReflectionMethod($this->controller, '__invoke');
        
        $this->assertSame('__invoke', $reflection->getName());
        $this->assertSame(0, $reflection->getNumberOfParameters());
        $this->assertSame(0, $reflection->getNumberOfRequiredParameters());
        
        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('Symfony\Component\HttpFoundation\Response', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }
} 