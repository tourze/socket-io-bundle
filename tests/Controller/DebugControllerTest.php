<?php

namespace SocketIoBundle\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Controller\DebugController;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tourze\PHPUnitSymfonyWebTest\AbstractWebTestCase;
use Twig\Environment;

/**
 * 测试 DebugController 完整 HTTP 流程
 * 重点测试 Twig 模板渲染和调试页面功能
 *
 * @internal
 */
#[CoversClass(DebugController::class)]
#[RunTestsInSeparateProcesses]
final class DebugControllerTest extends AbstractWebTestCase
{
    public function testControllerStructure(): void
    {
        $reflection = new \ReflectionClass(DebugController::class);
        $this->assertTrue($reflection->isFinal());

        $method = new \ReflectionMethod(DebugController::class, '__invoke');
        $this->assertTrue($method->isPublic());
        $this->assertSame('__invoke', $method->getName());
    }

    public function testDebugPageRendersSuccessfully(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/socket-io/debug');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful(), 'Debug page should render successfully');
        $this->assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertNotEmpty($content);

        // 验证是 HTML 内容
        $this->assertStringContainsString('<html', $content);
        $this->assertStringContainsString('</html>', $content);
    }

    public function testDebugPageWithSocketData(): void
    {
        $client = self::createClientWithDatabase();

        // 创建一些测试数据供调试页面显示
        $socket = new Socket();
        $socket->setSessionId('debug-session-' . time());
        $socket->setSocketId('debug-socket-' . time());
        $socket->setNamespace('/test');
        $socket->setTransport('polling');
        $socket->setConnected(true);

        $room = new Room();
        $room->setName('/test');
        $room->setNamespace('debug-room');

        $message = new Message();
        $message->setEvent('debug-event');
        $message->setData(['test' => 'debug data']);
        $message->setSender($socket);
        $message->addRoom($room);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->flush();

        $client->request('GET', '/socket-io/debug');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful(), 'Debug page should render successfully with socket data');
        $content = $response->getContent();

        // 调试页面应该能显示这些数据（具体内容取决于模板实现）
        $this->assertIsString($content);
        $this->assertNotEmpty($content);
    }

    public function testDebugPageTwigIntegration(): void
    {
        $client = self::createClientWithDatabase();

        // 验证 Twig 环境正确集成
        $container = self::getContainer();
        $this->assertTrue($container->has('twig'));

        $twig = $container->get('twig');
        $this->assertInstanceOf(Environment::class, $twig);

        // 验证模板路径
        $this->assertTrue($twig->getLoader()->exists('@SocketIo/debug.html.twig'));

        $client->request('GET', '/socket-io/debug');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful(), 'Debug page should render successfully with Twig integration');
    }

    public function testDebugPageResponseHeaders(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/socket-io/debug');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful(), 'Debug page should render successfully for header tests');
        $this->assertTrue($response->headers->has('Content-Type'));
        $contentType = $response->headers->get('Content-Type');
        $this->assertIsString($contentType);
        $this->assertStringContainsString('text/html', $contentType);

        // 应该有适当的缓存头（通常调试页面不缓存）
        $cacheControl = $response->headers->get('Cache-Control');
        $expires = $response->headers->get('Expires');
        $this->assertTrue(null !== $cacheControl || null !== $expires);
    }

    public function testDebugPageWithQueryParameters(): void
    {
        $client = self::createClientWithDatabase();

        // 测试带查询参数的调试页面访问
        $client->request('GET', '/socket-io/debug?view=connections&filter=active');

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful(), 'Debug page should render successfully with query parameters');
        $this->assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function testDebugPageWithCustomHeaders(): void
    {
        $client = self::createClientWithDatabase();

        $client->request('GET', '/socket-io/debug', [], [], [
            'HTTP_USER_AGENT' => 'Debug Browser/1.0',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.5',
        ]);

        $response = $client->getResponse();
        $this->assertTrue($response->isSuccessful(), 'Debug page should render successfully with custom headers');
    }

    public function testDebugPageNotFoundForInvalidPath(): void
    {
        $client = self::createClientWithDatabase();

        $client->catchExceptions(false);

        $this->expectException(NotFoundHttpException::class);
        $client->request('GET', '/socket-io/debug/nonexistent');
    }

    #[DataProvider('provideNotAllowedMethods')]
    public function testMethodNotAllowed(string $method): void
    {
        $client = self::createClientWithDatabase();

        $client->catchExceptions(false);

        $this->expectException(MethodNotAllowedHttpException::class);
        $client->request($method, '/socket-io/debug');
    }
}
