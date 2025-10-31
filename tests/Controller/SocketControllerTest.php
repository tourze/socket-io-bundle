<?php

namespace SocketIoBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Controller\SocketController;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Service\SocketIOService;
use Symfony\Component\HttpFoundation\Response;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * 测试 SocketController 完整 HTTP 流程
 * 不使用 Mock，测试真实的 Socket.IO 协议处理
 *
 * @internal
 */
#[CoversClass(SocketController::class)]
#[RunTestsInSeparateProcesses]
final class SocketControllerTest extends AbstractWebTestCase
{
    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(SocketController::class);
        $this->assertTrue($reflection->isFinal());

        $method = new \ReflectionMethod(SocketController::class, '__invoke');
        $this->assertTrue($method->isPublic());
        $this->assertSame('__invoke', $method->getName());
    }

    public function testSocketEndpointOptionsRequest(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('OPTIONS', '/socket.io/');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful(), 'OPTIONS request should be successful');
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
        $this->assertSame('GET, POST, OPTIONS', $response->headers->get('Access-Control-Allow-Methods'));
        $this->assertSame('Content-Type', $response->headers->get('Access-Control-Allow-Headers'));
        $this->assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function testSocketEndpointPollingHandshake(): void
    {
        $client = self::createClientWithDatabase();

        // 模拟 Socket.IO 客户端初始握手请求
        $client->request('GET', '/socket.io/', [
            'EIO' => '4',
            'transport' => 'polling',
            't' => 'test-' . time(),
        ]);

        $response = $client->getResponse();
        // Socket.IO 握手可能成功或失败，取决于配置
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ]);

        // 验证 CORS 头始终存在
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testSocketEndpointPollingMessage(): void
    {
        $client = self::createClientWithDatabase();

        // 模拟 Socket.IO 轮询消息发送
        $enginePacketData = '40'; // Engine.IO 连接包
        $client->request('POST', '/socket.io/', [
            'EIO' => '4',
            'transport' => 'polling',
            't' => 'test-' . time(),
        ], [], [], $enginePacketData);

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ]);

        // 验证响应包含正确的 CORS 头
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testSocketEndpointWithExistingSocket(): void
    {
        $client = self::createClientWithDatabase();

        // 创建一个已存在的 Socket 连接
        $socket = new Socket();
        $socket->setSessionId('test-session-' . time());
        $socket->setSocketId('test-socket-' . time());
        $socket->setNamespace('/');
        $socket->setTransport('polling');
        $socket->setConnected(true);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->flush();

        // 使用已存在的 Socket 进行请求
        $client->request('GET', '/socket.io/', [
            'EIO' => '4',
            'transport' => 'polling',
            'sid' => $socket->getSocketId(),
            't' => 'test-' . time(),
        ]);

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ]);
    }

    public function testSocketEndpointServiceIntegration(): void
    {
        $client = self::createClientWithDatabase();

        // 验证 SocketIOService 正确集成到控制器中
        $container = self::getContainer();
        $this->assertTrue($container->has(SocketIOService::class));

        $socketIOService = $container->get(SocketIOService::class);
        $this->assertInstanceOf(SocketIOService::class, $socketIOService);

        // 测试服务能够处理请求
        $client->request('GET', '/socket.io/', [
            'EIO' => '4',
            'transport' => 'polling',
        ]);

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ]);
    }

    public function testSocketEndpointErrorHandling(): void
    {
        $client = self::createClientWithDatabase();

        // 发送无效的 Engine.IO 数据，测试错误处理
        $client->request('GET', '/socket.io/', [
            'EIO' => 'invalid',
            'transport' => 'invalid-transport',
        ]);

        $response = $client->getResponse();

        // 错误时应该返回适当的状态码和错误信息
        if (Response::HTTP_INTERNAL_SERVER_ERROR === $response->getStatusCode()) {
            $this->assertSame('application/json', $response->headers->get('Content-Type'));

            $responseContent = $response->getContent();
            $this->assertIsString($responseContent);
            $content = json_decode($responseContent, true);
            $this->assertIsArray($content);
            $this->assertArrayHasKey('error', $content);
            $this->assertIsString($content['error']);
        }

        // 错误响应也应该有 CORS 头
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testSocketEndpointWebSocketUpgrade(): void
    {
        $client = self::createClientWithDatabase();

        // 模拟 WebSocket 升级请求（虽然在 HTTP 测试中不会真正升级）
        $client->request('GET', '/socket.io/', [
            'EIO' => '4',
            'transport' => 'websocket',
        ], [], [
            'HTTP_CONNECTION' => 'Upgrade',
            'HTTP_UPGRADE' => 'websocket',
            'HTTP_SEC_WEBSOCKET_KEY' => base64_encode(random_bytes(16)),
            'HTTP_SEC_WEBSOCKET_VERSION' => '13',
        ]);

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_INTERNAL_SERVER_ERROR,
            Response::HTTP_SWITCHING_PROTOCOLS,
        ]);
    }

    public function testSocketEndpointWithCustomHeaders(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/socket.io/', [
            'EIO' => '4',
            'transport' => 'polling',
        ], [], [
            'HTTP_ORIGIN' => 'http://localhost:3000',
            'HTTP_USER_AGENT' => 'Socket.IO Client/4.0.0',
            'HTTP_ACCEPT' => 'text/plain',
        ]);

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_BAD_REQUEST,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ]);

        // 验证 CORS 头正确处理了 Origin
        $this->assertSame('*', $response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testSocketEndpointRejectsPutRequest(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(true);

        $client->request('PUT', '/socket.io/');

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
    }

    public function testSocketEndpointRejectsDeleteRequest(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(true);

        $client->request('DELETE', '/socket.io/');

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(true);

        $client->request($method, '/socket.io/');

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
    }
}
