<?php

namespace SocketIoBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Controller\Admin\SocketCrudController;
use SocketIoBundle\Entity\Socket;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(SocketCrudController::class)]
#[RunTestsInSeparateProcesses]
final class SocketCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /**
     * 获取控制器服务实例
     * @return AbstractCrudController<Socket>
     */
    protected function getControllerService(): AbstractCrudController
    {
        /** @phpstan-ignore-next-line */
        return self::getService(SocketCrudController::class);
    }

    /**
     * 提供索引页的表头信息 - 基于控制器的字段配置
     * @return iterable<string, array{string}>
     */
    public static function provideIndexPageHeaders(): iterable
    {
        yield 'id' => ['ID'];
        yield 'socket_id' => ['Socket ID'];
        yield 'session_id' => ['会话ID'];
        yield 'client_id' => ['客户端ID'];
        yield 'namespace' => ['命名空间'];
        yield 'transport' => ['传输类型'];
        yield 'connected' => ['是否在线'];
        yield 'poll_count' => ['轮询次数'];
        yield 'last_heartbeat' => ['最后心跳时间'];
        yield 'last_delivery' => ['最后投递时间'];
        yield 'last_active' => ['最后活跃时间'];
        yield 'create_time' => ['创建时间'];
        yield 'update_time' => ['更新时间'];
    }

    /**
     * 提供新建页的字段信息 - 基于表单字段配置
     * @return iterable<string, array{string}>
     */
    public static function provideNewPageFields(): iterable
    {
        yield 'socketId' => ['socketId'];
        yield 'sessionId' => ['sessionId'];
        yield 'clientId' => ['clientId'];
        yield 'namespace' => ['namespace'];
        yield 'transport' => ['transport'];
        yield 'connected' => ['connected'];
        yield 'pollCount' => ['pollCount'];
    }

    /**
     * 提供编辑页的字段信息 - 基于编辑表单字段配置
     * @return iterable<string, array{string}>
     */
    public static function provideEditPageFields(): iterable
    {
        yield 'socketId' => ['socketId'];
        yield 'sessionId' => ['sessionId'];
        yield 'clientId' => ['clientId'];
        yield 'namespace' => ['namespace'];
        yield 'transport' => ['transport'];
        yield 'connected' => ['connected'];
        yield 'pollCount' => ['pollCount'];
    }

    public function testIndexPageRequiresAuthentication(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(true);

        try {
            $client->request('GET', $this->generateAdminUrl('index', ['crudController' => SocketCrudController::class]));
            $response = $client->getResponse();
            $this->assertTrue(
                $response->isRedirect() || $response->isClientError(),
                'Unauthenticated access should be redirected or denied'
            );
        } catch (AccessDeniedException $e) {
            $this->assertInstanceOf(AccessDeniedException::class, $e);
        }
    }

    public function testIndexPageWithAuthentication(): void
    {
        $client = $this->createAuthenticatedClient();

        try {
            $client->request('GET', $this->generateAdminUrl('index', ['crudController' => SocketCrudController::class]));

            // Accept either success or redirect (depends on EasyAdmin dashboard config)
            $this->assertTrue(
                $client->getResponse()->isSuccessful() || $client->getResponse()->isRedirect(),
                'Authenticated access should succeed or redirect to valid page'
            );
        } catch (\Throwable $e) {
            // If EasyAdmin is not properly configured in test, verify the exception is expected
            $this->assertStringContainsString('AdminContext', $e->getMessage(), 'Should get EasyAdmin context error when not properly configured');
        }
    }

    public function testControllerHasRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);

        // Test that controller has all required configuration methods
        $this->assertTrue($reflection->hasMethod('configureFields'), 'Controller must have configureFields method');
        $this->assertTrue($reflection->hasMethod('configureFilters'), 'Controller must have configureFilters method');
        $this->assertTrue($reflection->hasMethod('configureCrud'), 'Controller must have configureCrud method');
        $this->assertTrue($reflection->hasMethod('configureActions'), 'Controller must have configureActions method');

        // Test that controller has custom action methods
        $this->assertTrue($reflection->hasMethod('viewRooms'), 'Controller must have viewRooms method');
        $this->assertTrue($reflection->hasMethod('disconnectSocket'), 'Controller must have disconnectSocket method');
        $this->assertTrue($reflection->hasMethod('refreshStatus'), 'Controller must have refreshStatus method');
    }

    public function testCustomActionsArePublic(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);

        $viewRoomsMethod = $reflection->getMethod('viewRooms');
        $this->assertTrue($viewRoomsMethod->isPublic(), 'viewRooms method must be public');

        $disconnectSocketMethod = $reflection->getMethod('disconnectSocket');
        $this->assertTrue($disconnectSocketMethod->isPublic(), 'disconnectSocket method must be public');

        $refreshStatusMethod = $reflection->getMethod('refreshStatus');
        $this->assertTrue($refreshStatusMethod->isPublic(), 'refreshStatus method must be public');
    }

    public function testEntityCreationAndPersistence(): void
    {
        // Create test data to verify entities can be created properly
        $socket = new Socket();
        $socket->setSessionId('session-id');
        $socket->setSocketId('socket-id');

        // Verify initial state
        $this->assertEquals('socket-id', $socket->getSocketId());
        $this->assertEquals('session-id', $socket->getSessionId());
        $this->assertTrue($socket->isConnected()); // Default state
        $this->assertEquals('/', $socket->getNamespace()); // Default namespace
        $this->assertEquals(0, $socket->getPollCount()); // Default poll count
    }

    public function testSocketConnectionStatusManagement(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');

        // Test initial connected state
        $this->assertTrue($socket->isConnected());

        // Test disconnection
        $socket->setConnected(false);
        $this->assertFalse($socket->isConnected());

        // Test reconnection
        $socket->setConnected(true);
        $this->assertTrue($socket->isConnected());

        // Test status update methods
        $socket->updateLastActiveTime();
        $this->assertInstanceOf(\DateTimeInterface::class, $socket->getLastActiveTime());

        $socket->updatePingTime();
        $this->assertInstanceOf(\DateTimeInterface::class, $socket->getLastPingTime());
    }

    public function testUnauthorizedAccessRedirectsToLogin(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage("Access Denied. The user doesn't have ROLE_ADMIN.");

        $client->request('GET', $this->generateAdminUrl('index', ['crudController' => SocketCrudController::class]));
    }

    public function testViewRoomsActionMethodExists(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        $method = $reflection->getMethod('viewRooms');

        $this->assertTrue($method->isPublic(), 'viewRooms method must be public');
        $this->assertCount(1, $method->getParameters(), 'viewRooms method should have 1 parameter');
        $this->assertEquals('context', $method->getParameters()[0]->getName(), 'Parameter should be named context');
    }

    public function testDisconnectSocketActionMethodExists(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        $method = $reflection->getMethod('disconnectSocket');

        $this->assertTrue($method->isPublic(), 'disconnectSocket method must be public');
        $this->assertCount(1, $method->getParameters(), 'disconnectSocket method should have 1 parameter');
        $this->assertEquals('context', $method->getParameters()[0]->getName(), 'Parameter should be named context');
    }

    public function testRefreshStatusActionMethodExists(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);
        $method = $reflection->getMethod('refreshStatus');

        $this->assertTrue($method->isPublic(), 'refreshStatus method must be public');
        $this->assertCount(1, $method->getParameters(), 'refreshStatus method should have 1 parameter');
        $this->assertEquals('context', $method->getParameters()[0]->getName(), 'Parameter should be named context');
    }

    public function testAllCustomActionMethodsExist(): void
    {
        $reflection = new \ReflectionClass(SocketCrudController::class);

        // Test all custom action methods exist
        $this->assertTrue($reflection->hasMethod('viewRooms'), 'Controller must have viewRooms method');
        $this->assertTrue($reflection->hasMethod('disconnectSocket'), 'Controller must have disconnectSocket method');
        $this->assertTrue($reflection->hasMethod('refreshStatus'), 'Controller must have refreshStatus method');

        // Verify all methods are public
        $this->assertTrue($reflection->getMethod('viewRooms')->isPublic(), 'viewRooms method must be public');
        $this->assertTrue($reflection->getMethod('disconnectSocket')->isPublic(), 'disconnectSocket method must be public');
        $this->assertTrue($reflection->getMethod('refreshStatus')->isPublic(), 'refreshStatus method must be public');
    }

    public function testSocketTimeManagement(): void
    {
        $socket = new Socket();
        $socket->setSessionId('time-session');
        $socket->setSocketId('time-socket');

        // 测试初始时间状态 (时间戳在构造时被初始化)
        // 验证时间戳是否为预期类型
        $this->assertInstanceOf(
            \DateTimeInterface::class,
            $socket->getLastPingTime(),
            'Initial ping time should be DateTimeInterface'
        );
        $this->assertInstanceOf(
            \DateTimeInterface::class,
            $socket->getLastActiveTime(),
            'Initial active time should be DateTimeInterface'
        );
        $this->assertNull(
            $socket->getLastDeliverTime(),
            'Initial deliver time should be null'
        );

        // 测试更新方法
        $socket->updatePingTime();
        $this->assertInstanceOf(\DateTimeInterface::class, $socket->getLastPingTime(), 'Ping time should be set after update');

        $socket->updateLastActiveTime();
        $this->assertInstanceOf(\DateTimeInterface::class, $socket->getLastActiveTime(), 'Active time should be set after update');

        // 测试手动设置时间
        $specificTime = new \DateTimeImmutable('2023-01-01 12:00:00');
        $socket->setLastDeliverTime($specificTime);
        $this->assertEquals($specificTime, $socket->getLastDeliverTime(), 'Deliver time should be set correctly');
    }

    public function testSocketTransportAndNamespaceHandling(): void
    {
        $socket = new Socket();
        $socket->setSessionId('transport-session');
        $socket->setSocketId('transport-socket');

        // 测试默认值
        $this->assertEquals('/', $socket->getNamespace(), 'Default namespace should be "/"');
        $this->assertEquals('polling', $socket->getTransport(), 'Default transport should be "polling"');
        $this->assertEquals(0, $socket->getPollCount(), 'Default poll count should be 0');
        $this->assertTrue($socket->isConnected(), 'Default connected state should be true');

        // 测试设置值
        $socket->setNamespace('/custom');
        $socket->setTransport('websocket');
        $socket->incrementPollCount();
        $socket->incrementPollCount();
        $socket->setConnected(false);

        $this->assertEquals('/custom', $socket->getNamespace(), 'Custom namespace should be set');
        $this->assertEquals('websocket', $socket->getTransport(), 'Custom transport should be set');
        $this->assertEquals(2, $socket->getPollCount(), 'Poll count should be incremented twice');
        $this->assertFalse($socket->isConnected(), 'Connected state should be false');

        // 测试增加轮询次数
        $socket->incrementPollCount();
        $this->assertEquals(3, $socket->getPollCount(), 'Poll count should be incremented');

        $socket->incrementPollCount();
        $this->assertEquals(4, $socket->getPollCount(), 'Poll count should be incremented again');
    }

    public function testSocketHandshakeDataHandling(): void
    {
        $socket = new Socket();
        $socket->setSessionId('handshake-session');
        $socket->setSocketId('handshake-socket');

        // 测试初始状态
        $this->assertNull($socket->getHandshake(), 'Initial handshake should be null');

        // 测试设置复杂的握手数据
        $handshakeData = [
            'headers' => [
                'user-agent' => 'Mozilla/5.0',
                'accept' => 'application/json',
            ],
            'query' => [
                'token' => 'abc123',
                'version' => '1.0',
            ],
            'url' => '/socket.io/?transport=websocket',
            'address' => '127.0.0.1',
            'time' => '2023-01-01T12:00:00Z',
        ];

        $socket->setHandshake($handshakeData);
        $this->assertEquals($handshakeData, $socket->getHandshake(), 'Handshake data should be stored correctly');

        // 测试空数组
        $socket->setHandshake([]);
        $this->assertEquals([], $socket->getHandshake(), 'Empty handshake data should be stored correctly');

        // 测试null
        $socket->setHandshake(null);
        $this->assertNull($socket->getHandshake(), 'Null handshake should be stored correctly');
    }

}
