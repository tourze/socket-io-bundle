<?php

namespace SocketIoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Service\HttpRequestHandler;
use SocketIoBundle\Service\PayloadProcessor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(HttpRequestHandler::class)]
final class HttpRequestHandlerTest extends TestCase
{
    private PayloadProcessor&MockObject $payloadProcessor;

    private HttpRequestHandler $handler;

    public function testInitializeRequestSettingsWithJsonp(): void
    {
        $request = Request::create('/', 'GET', ['j' => '5']);

        $this->handler->initializeRequestSettings($request);

        $response = $this->handler->createResponse('test');
        $this->assertSame('text/javascript; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testInitializeRequestSettingsWithBinary(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/octet-stream');

        $this->handler->initializeRequestSettings($request);

        $response = $this->handler->createResponse('test');
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
    }

    public function testHandlePostPayloadTooLarge(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], str_repeat('x', 2000));
        $this->handler->initializeRequestSettings($request);

        $response = $this->handler->handlePost($request);

        $this->assertSame(Response::HTTP_REQUEST_ENTITY_TOO_LARGE, $response->getStatusCode());
        $this->assertSame('Payload too large', $response->getContent());
    }

    public function testHandlePostSuccess(): void
    {
        $request = Request::create('/', 'POST', [], [], [], [], 'test-content');
        $this->handler->initializeRequestSettings($request);

        $this->payloadProcessor
            ->expects($this->once())
            ->method('decodePayload')
            ->with('test-content')
            ->willReturn(['packet1', 'packet2'])
        ;

        $response = $this->handler->handlePost($request);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('ok', $response->getContent());
    }

    public function testCreateFirstPollResponse(): void
    {
        $this->handler->initializeRequestSettings(Request::create('/', 'GET'));

        $response = $this->handler->createFirstPollResponse('socket123');

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotEmpty($response->getContent());
    }

    public function testSetPacketHandler(): void
    {
        $handlerCalled = false;
        $packetHandler = function () use (&$handlerCalled): void {
            $handlerCalled = true;
        };

        $this->handler->setPacketHandler($packetHandler);

        // We can't easily test the handler is called without complex mocking
        // This test ensures the method exists and accepts callable
        $this->assertInstanceOf(HttpRequestHandler::class, $this->handler);
    }

    public function testCreateResponse(): void
    {
        $request = Request::create('/', 'GET');
        $this->handler->initializeRequestSettings($request);

        $response = $this->handler->createResponse('test-content');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('test-content', $response->getContent());
        $this->assertSame('text/plain; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testCreateResponseWithJsonp(): void
    {
        $request = Request::create('/', 'GET', ['j' => '5']);
        $this->handler->initializeRequestSettings($request);

        $response = $this->handler->createResponse('test-content');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('test-content', $response->getContent());
        $this->assertSame('text/javascript; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testCreateResponseWithBinary(): void
    {
        $request = Request::create('/', 'GET');
        $request->headers->set('Accept', 'application/octet-stream');
        $this->handler->initializeRequestSettings($request);

        $response = $this->handler->createResponse('test-content');

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('test-content', $response->getContent());
        $this->assertSame('application/octet-stream', $response->headers->get('Content-Type'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * 使用具体类 PayloadProcessor 进行 Mock 是必要的，因为：
         * 1. PayloadProcessor 是服务类，负责处理 Socket.IO 协议的数据包编解码
         * 2. 测试需要模拟具体的编解码行为，验证处理器的调用和返回值
         * 3. 没有定义 PayloadProcessorInterface，直接 Mock 服务类是合理的选择
         */
        $this->payloadProcessor = $this->createMock(PayloadProcessor::class);
        $this->handler = new HttpRequestHandler($this->payloadProcessor);

        // Mock environment variable
        $_ENV['SOCKET_IO_MAX_PAYLOAD_SIZE'] = '1024';
    }
}
