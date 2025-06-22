<?php

namespace SocketIoBundle\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use SocketIoBundle\Controller\SocketController;
use SocketIoBundle\Service\SocketIOService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SocketControllerTest extends TestCase
{
    private SocketController $controller;
    private SocketIOService $socketIOService;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->socketIOService = $this->createMock(SocketIOService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->controller = new SocketController(
            $this->socketIOService,
            $this->logger
        );
    }

    public function test_extends_abstract_controller(): void
    {
        $this->assertInstanceOf(AbstractController::class, $this->controller);
    }

    public function test_constructor_sets_dependencies(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        
        $socketIOProperty = $reflection->getProperty('socketIO');
        $socketIOProperty->setAccessible(true);
        $this->assertSame($this->socketIOService, $socketIOProperty->getValue($this->controller));
        
        $loggerProperty = $reflection->getProperty('logger');
        $loggerProperty->setAccessible(true);
        $this->assertSame($this->logger, $loggerProperty->getValue($this->controller));
        
        // messageService 属性不存在于当前控制器实现中
    }

    public function test_handle_options_request_returns_cors_response(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->once())->method('isMethod')->with('OPTIONS')->willReturn(true);
        
        $response = $this->controller->__invoke($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('GET, POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertSame('Content-Type', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_handle_normal_request_delegates_to_socket_io_service(): void
    {
        $request = $this->createMock(Request::class);
        $request->expects($this->once())->method('isMethod')->with('OPTIONS')->willReturn(false);
        
        $expectedResponse = new Response('socket.io response');
        $this->socketIOService->expects($this->once())
            ->method('handleRequest')
            ->with($request)
            ->willReturn($expectedResponse);
        
        $response = $this->controller->__invoke($request);
        
        $this->assertSame($expectedResponse, $response);
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function test_handle_exception_logs_error_and_returns_error_response(): void
    {
        $request = new Request(['test' => 'value'], [], [], [], [], [], 'request body');
        $request->setMethod('GET');
        
        $exception = new \RuntimeException('Test exception');
        $this->socketIOService->expects($this->once())
            ->method('handleRequest')
            ->with($request)
            ->willThrowException($exception);
        
        $this->logger->expects($this->once())
            ->method('error')
            ->with('SocketIO Exception', [
                'exception' => $exception,
                'query' => ['test' => 'value'],
                'body' => 'request body',
            ]);
        
        $response = $this->controller->__invoke($request);
        
        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        
        $responseData = json_decode($response->getContent(), true);
        $this->assertSame(['error' => 'Test exception'], $responseData);
    }


    public function test_handle_method_has_correct_route_attribute(): void
    {
        $reflection = new \ReflectionMethod($this->controller, '__invoke');
        $attributes = $reflection->getAttributes();
        
        $routeAttribute = null;
        foreach ($attributes as $attribute) {
            if ($attribute->getName() === 'Symfony\Component\Routing\Attribute\Route') {
                $routeAttribute = $attribute;
                break;
            }
        }
        
        $this->assertNotNull($routeAttribute);
        $arguments = $routeAttribute->getArguments();
        $this->assertSame('/socket.io/', $arguments[0]);
        $this->assertSame('socket_io_endpoint', $arguments['name']);
        $this->assertSame(['GET', 'POST', 'OPTIONS'], $arguments['methods']);
    }


    public function test_private_methods_exist(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        
        $this->assertTrue($reflection->hasMethod('createCorsResponse'));
        $this->assertTrue($reflection->hasMethod('addCorsHeaders'));
        
        $this->assertTrue($reflection->getMethod('createCorsResponse')->isPrivate());
        $this->assertTrue($reflection->getMethod('addCorsHeaders')->isPrivate());
    }

    public function test_cors_headers_are_correctly_set(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('addCorsHeaders');
        $method->setAccessible(true);
        
        $response = new Response();
        $method->invoke($this->controller, $response);
        
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('GET, POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertSame('Content-Type', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function test_create_cors_response_returns_response_with_cors_headers(): void
    {
        $reflection = new \ReflectionClass($this->controller);
        $method = $reflection->getMethod('createCorsResponse');
        $method->setAccessible(true);
        
        $response = $method->invoke($this->controller);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('GET, POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
    }
} 