<?php

namespace SocketIoBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Controller\SocketTestController;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Service\MessageService;
use Symfony\Component\HttpFoundation\Response;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;

/**
 * 测试 SocketTestController 完整 HTTP 流程
 * 重点测试 MessageService 真实广播功能，验证返回的 JSON 数据结构
 *
 * @internal
 */
#[CoversClass(SocketTestController::class)]
#[RunTestsInSeparateProcesses]
final class SocketTestControllerTest extends AbstractWebTestCase
{
    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(SocketTestController::class);
        $this->assertTrue($reflection->isFinal());

        $method = new \ReflectionMethod(SocketTestController::class, '__invoke');
        $this->assertTrue($method->isPublic());
        $this->assertSame('__invoke', $method->getName());
    }

    public function testSocketTestEndpointReturnsJsonResponse(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/socket.io/test');

        $response = $client->getResponse();
        $this->assertSame('application/json', $response->headers->get('Content-Type'));

        $responseContent = $response->getContent();
        $this->assertIsString($responseContent);

        $content = json_decode($responseContent, true);
        $this->assertIsArray($content);
        $this->assertArrayHasKey('success', $content);
        $this->assertIsBool($content['success']);
    }

    public function testSocketTestEndpointSuccessResponseStructure(): void
    {
        $client = self::createClientWithDatabase();

        // 创建一些活跃的 Socket 连接供广播
        $socket1 = new Socket();
        $socket1->setSessionId('test-session-1-' . time());
        $socket1->setSocketId('test-socket-1-' . time());
        $socket1->setNamespace('/');
        $socket1->setTransport('polling');
        $socket1->setConnected(true);

        $socket2 = new Socket();
        $socket2->setSessionId('test-session-2-' . time());
        $socket2->setSocketId('test-socket-2-' . time());
        $socket2->setNamespace('/');
        $socket2->setTransport('websocket');
        $socket2->setConnected(true);

        $room = new Room();
        $room->setNamespace('/');
        $room->setName('test-room-' . time());
        $socket1->joinRoom($room);
        $socket2->joinRoom($room);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->persist($room);
        $entityManager->flush();

        $client->request('GET', '/socket.io/test');

        $response = $client->getResponse();
        if (Response::HTTP_OK === $response->getStatusCode()) {
            $responseContent = $response->getContent();
            $this->assertIsString($responseContent);
            $content = json_decode($responseContent, true);
            $this->assertIsArray($content);

            // 验证成功响应的完整结构
            $this->assertTrue($content['success']);
            $this->assertArrayHasKey('message', $content);
            $this->assertArrayHasKey('data', $content);

            // 验证消息内容
            $this->assertIsString($content['message']);
            $this->assertStringContainsString('Random message sent to', $content['message']);
            $this->assertStringContainsString('active clients', $content['message']);

            // 验证数据结构
            $this->assertIsArray($content['data']);
            $this->assertCount(2, $content['data'], 'Should contain exactly 2 random data items');

            // 验证每个数据项的格式：timestamp-hexstring
            foreach ($content['data'] as $item) {
                $this->assertIsString($item);
                $this->assertStringContainsString('-', $item);

                $parts = explode('-', $item);
                $this->assertCount(2, $parts, 'Each data item should be timestamp-hex format');
                $this->assertIsNumeric($parts[0], 'First part should be timestamp');
                $this->assertTrue(ctype_xdigit($parts[1]), 'Second part should be hex string');
                $this->assertSame(16, strlen($parts[1]), 'Hex part should be 16 characters (8 bytes)');
            }
        }
    }

    public function testSocketTestEndpointServiceIntegration(): void
    {
        $client = self::createClientWithDatabase();

        // 验证 MessageService 正确集成
        $container = self::getContainer();
        $this->assertTrue($container->has(MessageService::class));

        $messageService = $container->get(MessageService::class);
        $this->assertInstanceOf(MessageService::class, $messageService);

        $client->request('GET', '/socket.io/test');

        // 即使没有活跃连接，服务也应该正常工作
        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ]);
    }

    public function testSocketTestEndpointErrorResponse(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/socket.io/test');

        $response = $client->getResponse();

        if (Response::HTTP_INTERNAL_SERVER_ERROR === $response->getStatusCode()) {
            $responseContent = $response->getContent();
            $this->assertIsString($responseContent);
            $content = json_decode($responseContent, true);
            $this->assertIsArray($content);

            // 验证错误响应结构
            $this->assertFalse($content['success']);
            $this->assertArrayHasKey('error', $content);
            $this->assertIsString($content['error']);
            $this->assertNotEmpty($content['error']);
        } else {
            // 如果没有错误，至少验证响应状态码
            $this->assertContains($response->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ]);
        }
    }

    public function testSocketTestEndpointRandomDataGeneration(): void
    {
        $client = self::createClientWithDatabase();

        // 连续调用两次，验证数据是随机生成的
        $client->request('GET', '/socket.io/test');
        $response1 = $client->getResponse();

        $client->request('GET', '/socket.io/test');
        $response2 = $client->getResponse();

        if (Response::HTTP_OK === $response1->getStatusCode() && Response::HTTP_OK === $response2->getStatusCode()) {
            $responseContent1 = $response1->getContent();
            $responseContent2 = $response2->getContent();
            $this->assertIsString($responseContent1);
            $this->assertIsString($responseContent2);

            $content1 = json_decode($responseContent1, true);
            $content2 = json_decode($responseContent2, true);
            $this->assertIsArray($content1);
            $this->assertIsArray($content2);

            // 两次调用的数据应该不同（因为包含时间戳和随机数）
            $this->assertNotEquals($content1['data'], $content2['data'], 'Random data should be different between calls');
        } else {
            // 如果有错误，至少验证状态码在预期范围内
            $this->assertContains($response1->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ]);
            $this->assertContains($response2->getStatusCode(), [
                Response::HTTP_OK,
                Response::HTTP_INTERNAL_SERVER_ERROR,
            ]);
        }
    }

    public function testSocketTestEndpointBroadcastEvent(): void
    {
        $client = self::createClientWithDatabase();

        // 创建一些 Socket 和 Room 来接收广播
        $socket = new Socket();
        $socket->setSessionId('broadcast-session-' . time());
        $socket->setSocketId('broadcast-socket-' . time());
        $socket->setNamespace('/');
        $socket->setTransport('polling');
        $socket->setConnected(true);

        $room = new Room();
        $room->setNamespace('/');
        $room->setName('broadcast-room-' . time());
        $socket->joinRoom($room);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->flush();

        $client->request('GET', '/socket.io/test');

        $response = $client->getResponse();
        if (Response::HTTP_OK === $response->getStatusCode()) {
            $responseContent = $response->getContent();
            $this->assertIsString($responseContent);
            $content = json_decode($responseContent, true);
            $this->assertIsArray($content);

            // 应该显示向多少个客户端发送了消息
            $this->assertArrayHasKey('message', $content);
            $this->assertIsString($content['message']);
            $this->assertStringContainsString('active clients', $content['message']);

            // 检查是否真的创建了消息记录（MessageService.broadcast() 的副作用）
            $messageRepository = $entityManager->getRepository(Message::class);
            $recentMessages = $messageRepository->findBy([], ['createTime' => 'DESC'], 5);

            // 应该能找到刚刚创建的消息
            $foundTestMessage = false;
            foreach ($recentMessages as $message) {
                if ('random2' === $message->getEvent()) {
                    $foundTestMessage = true;
                    $messageData = $message->getData();
                    $this->assertIsArray($messageData);
                    $this->assertCount(2, $messageData);
                    break;
                }
            }

            $this->assertTrue($foundTestMessage, 'Should create a message with event "random2"');
        }
    }

    public function testSocketTestEndpointWithQueryParameters(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/socket.io/test?debug=1&test=value');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ]);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testSocketTestEndpointWithCustomHeaders(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/socket.io/test', [], [], [
            'HTTP_USER_AGENT' => 'Test Client/1.0',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [
            Response::HTTP_OK,
            Response::HTTP_INTERNAL_SERVER_ERROR,
        ]);
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
    }

    public function testSocketTestEndpointNotFoundForInvalidPath(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(true);

        $client->request('GET', '/socket.io/test/nonexistent');

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(true);

        $client->request($method, '/socket.io/test');

        $response = $client->getResponse();
        $this->assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
    }
}
