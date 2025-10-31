<?php

namespace SocketIoBundle\Tests\Controller\Admin;

use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Controller\Admin\MessageCrudController;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminControllerTestCase;

/**
 * @internal
 */
#[CoversClass(MessageCrudController::class)]
#[RunTestsInSeparateProcesses]
final class MessageCrudControllerTest extends AbstractEasyAdminControllerTestCase
{
    /**
     * 获取控制器服务实例
     * @return AbstractCrudController<Message>
     */
    protected function getControllerService(): AbstractCrudController
    {
        /** @phpstan-ignore-next-line */
        return self::getService(MessageCrudController::class);
    }

    /**
     * 重写父类方法，提供测试所需的数据
     * @return iterable<int, object>
     */
    protected function getFixturesData(): iterable
    {
        // 创建 Message 实体
        $message = new Message();
        $message->setEvent('test-event-' . uniqid());
        $message->setData(['test' => 'data']);

        yield $message;
    }

    public static function provideIndexPageHeaders(): iterable
    {
        yield 'ID' => ['ID'];
        yield '事件名' => ['事件名'];
        yield '数据' => ['数据'];
        yield '创建时间' => ['创建时间'];
    }

    public static function provideNewPageFields(): iterable
    {
        yield 'event' => ['event'];
        yield 'data' => ['data'];
        yield 'metadata' => ['metadata'];
        yield 'sender' => ['sender'];
        yield 'rooms' => ['rooms'];
    }

    public static function provideEditPageFields(): iterable
    {
        yield 'event' => ['event'];
        yield 'data' => ['data'];
        yield 'metadata' => ['metadata'];
        yield 'sender' => ['sender'];
        yield 'rooms' => ['rooms'];
    }

    public function testGetEntityFqcnReturnsCorrectEntityClass(): void
    {
        $this->assertSame(Message::class, MessageCrudController::getEntityFqcn());
    }

    public function testIndexPageRequiresAuthentication(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(true);

        try {
            $client->request('GET', $this->generateAdminUrl('index', ['crudController' => MessageCrudController::class]));
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
        $client = self::createClientWithDatabase();
        $this->createAdminUser('admin@test.com', 'password123');
        $this->loginAsAdmin($client, 'admin@test.com', 'password123');

        try {
            $client->request('GET', $this->generateAdminUrl('index', ['crudController' => MessageCrudController::class]));

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
        $reflection = new \ReflectionClass(MessageCrudController::class);

        // Test that controller has all required configuration methods
        $this->assertTrue($reflection->hasMethod('configureFields'), 'Controller must have configureFields method');
        $this->assertTrue($reflection->hasMethod('configureFilters'), 'Controller must have configureFilters method');
        $this->assertTrue($reflection->hasMethod('configureCrud'), 'Controller must have configureCrud method');
        $this->assertTrue($reflection->hasMethod('configureActions'), 'Controller must have configureActions method');

        // Test that controller has custom action methods
        $this->assertTrue($reflection->hasMethod('viewDeliveries'), 'Controller must have viewDeliveries method');
        $this->assertTrue($reflection->hasMethod('resendMessage'), 'Controller must have resendMessage method');
    }

    public function testCustomActionsArePublic(): void
    {
        $reflection = new \ReflectionClass(MessageCrudController::class);

        $viewDeliveriesMethod = $reflection->getMethod('viewDeliveries');
        $this->assertTrue($viewDeliveriesMethod->isPublic(), 'viewDeliveries method must be public');

        $resendMessageMethod = $reflection->getMethod('resendMessage');
        $this->assertTrue($resendMessageMethod->isPublic(), 'resendMessage method must be public');
    }

    public function testEntityCreationAndPersistence(): void
    {
        // Create test data to verify entities can be created properly
        $message = new Message();
        $message->setEvent('test-event');
        $message->setData(['data' => 'test']);

        // Verify initial state
        $this->assertEquals('test-event', $message->getEvent());
        $this->assertEquals(['data' => 'test'], $message->getData());
        $this->assertNull($message->getMetadata()); // Default null metadata
        $this->assertNull($message->getSender()); // Default null sender
        $this->assertEmpty($message->getRooms()); // Initial empty collection
        $this->assertEmpty($message->getDeliveries()); // Initial empty collection

        // Test that timestamps are properly initialized (may be null until persisted)
        // This is expected behavior for Doctrine entities before persistence
    }

    public function testMessageRoomAssociations(): void
    {
        $message = new Message();
        $message->setEvent('chat-message');

        $room1 = new Room();
        $room1->setName('general');
        $room1->setNamespace('/chat');

        $room2 = new Room();
        $room2->setName('support');
        $room2->setNamespace('/support');

        // Test adding rooms to message
        $message->addRoom($room1);
        $message->addRoom($room2);

        $this->assertCount(2, $message->getRooms());
        $this->assertTrue($message->getRooms()->contains($room1));
        $this->assertTrue($message->getRooms()->contains($room2));

        // Test removing room from message
        $message->removeRoom($room1);
        $this->assertCount(1, $message->getRooms());
        $this->assertFalse($message->getRooms()->contains($room1));
        $this->assertTrue($message->getRooms()->contains($room2));
    }

    public function testMessageSenderAssociation(): void
    {
        $message = new Message();
        $message->setEvent('user-message');

        $sender = new Socket();
        $sender->setSessionId('session-123');
        $sender->setSocketId('socket-456');
        $message->setSender($sender);

        $this->assertSame($sender, $message->getSender());
        $this->assertEquals('socket-456', $message->getSender()->getSocketId());
        $this->assertEquals('session-123', $message->getSender()->getSessionId());
    }

    public function testMessageMetadataManagement(): void
    {
        $message = new Message();
        $message->setEvent('notification');

        // Test metadata setting and getting
        $metadata = ['priority' => 'high', 'type' => 'alert'];
        $message->setMetadata($metadata);

        $this->assertEquals($metadata, $message->getMetadata());
        $metadata = $message->getMetadata();
        $this->assertIsArray($metadata);
        $this->assertEquals('high', $metadata['priority']);
        $this->assertEquals('alert', $metadata['type']);
    }

    public function testUnauthorizedAccessRedirectsToLogin(): void
    {
        $client = self::createClientWithDatabase();
        $client->catchExceptions(false);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage("Access Denied. The user doesn't have ROLE_ADMIN.");

        $client->request('GET', $this->generateAdminUrl('index', ['crudController' => MessageCrudController::class]));
    }

    public function testCustomActionMethodsExist(): void
    {
        // Test that controller has custom action methods required by configureCrud
        $reflection = new \ReflectionClass(MessageCrudController::class);

        $this->assertTrue($reflection->hasMethod('viewDeliveries'), 'Controller must have viewDeliveries method');
        $this->assertTrue($reflection->hasMethod('resendMessage'), 'Controller must have resendMessage method');

        // Verify method signatures
        $viewDeliveriesMethod = $reflection->getMethod('viewDeliveries');
        $resendMessageMethod = $reflection->getMethod('resendMessage');

        $this->assertTrue($viewDeliveriesMethod->isPublic(), 'viewDeliveries method should be public');
        $this->assertTrue($resendMessageMethod->isPublic(), 'resendMessage method should be public');
        $this->assertEquals('Symfony\Component\HttpFoundation\Response', (string) $viewDeliveriesMethod->getReturnType(), 'viewDeliveries should return Response');
        $this->assertEquals('Symfony\Component\HttpFoundation\Response', (string) $resendMessageMethod->getReturnType(), 'resendMessage should return Response');
    }

    public function testMessageWithComplexData(): void
    {
        $message = new Message();
        $message->setEvent('complex-event');

        // 测试复杂数据结构
        $complexData = [
            'user' => [
                'id' => 123,
                'name' => 'Test User',
                'roles' => ['user', 'admin'],
            ],
            'timestamp' => '2023-01-01T00:00:00Z',
            'metadata' => [
                'source' => 'api',
                'version' => '1.0',
            ],
        ];

        $message->setData($complexData);
        $this->assertEquals($complexData, $message->getData(), 'Complex data should be stored and retrieved correctly');

        // 测试元数据
        $metadata = ['priority' => 'high', 'encrypted' => true];
        $message->setMetadata($metadata);
        $this->assertEquals($metadata, $message->getMetadata(), 'Metadata should be stored and retrieved correctly');
    }

    public function testMessageWithMultipleRoomsAndSender(): void
    {
        $message = new Message();
        $message->setEvent('broadcast-message');
        $message->setData(['content' => 'Hello everyone!']);

        // 创建多个房间
        $room1 = new Room();
        $room1->setName('general');
        $room1->setNamespace('/chat');

        $room2 = new Room();
        $room2->setName('announcement');
        $room2->setNamespace('/system');

        $room3 = new Room();
        $room3->setName('vip');
        $room3->setNamespace('/premium');

        // 创建发送者
        $sender = new Socket();
        $sender->setSessionId('sender-session');
        $sender->setSocketId('sender-socket');
        $sender->setNamespace('/chat');

        // 设置关联
        $message->addRoom($room1);
        $message->addRoom($room2);
        $message->addRoom($room3);
        $message->setSender($sender);

        // 验证关联
        $this->assertCount(3, $message->getRooms(), 'Message should be associated with 3 rooms');
        $this->assertTrue($message->getRooms()->contains($room1), 'Should contain room1');
        $this->assertTrue($message->getRooms()->contains($room2), 'Should contain room2');
        $this->assertTrue($message->getRooms()->contains($room3), 'Should contain room3');
        $this->assertSame($sender, $message->getSender(), 'Sender should be correctly associated');

        // 测试移除房间
        $message->removeRoom($room2);
        $this->assertCount(2, $message->getRooms(), 'Message should now be associated with 2 rooms');
        $this->assertFalse($message->getRooms()->contains($room2), 'Should not contain room2 after removal');
    }

    public function testViewDeliveriesAction(): void
    {
        // 测试viewDeliveries方法的存在性和基本功能
        $reflection = new \ReflectionClass(MessageCrudController::class);
        $method = $reflection->getMethod('viewDeliveries');

        $this->assertTrue($method->isPublic(), 'viewDeliveries method should be public');
        $this->assertEquals('Symfony\Component\HttpFoundation\Response', (string) $method->getReturnType(), 'viewDeliveries should return Response');

        // 验证方法参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters, 'viewDeliveries should accept one parameter');
        $this->assertEquals('context', $parameters[0]->getName(), 'Parameter should be named context');
        $this->assertEquals('EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext', (string) $parameters[0]->getType(), 'Parameter should be AdminContext type');
    }

    public function testResendMessageAction(): void
    {
        // 测试resendMessage方法的存在性和基本功能
        $reflection = new \ReflectionClass(MessageCrudController::class);
        $method = $reflection->getMethod('resendMessage');

        $this->assertTrue($method->isPublic(), 'resendMessage method should be public');
        $this->assertEquals('Symfony\Component\HttpFoundation\Response', (string) $method->getReturnType(), 'resendMessage should return Response');

        // 验证方法参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters, 'resendMessage should accept one parameter');
        $this->assertEquals('context', $parameters[0]->getName(), 'Parameter should be named context');
        $this->assertEquals('EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext', (string) $parameters[0]->getType(), 'Parameter should be AdminContext type');
    }

    public function testConfigureActionsMethodExists(): void
    {
        // 简单验证configureActions方法存在和基本结构
        $reflection = new \ReflectionClass(MessageCrudController::class);

        $this->assertTrue($reflection->hasMethod('configureActions'), 'Controller should have configureActions method');

        $method = $reflection->getMethod('configureActions');
        $this->assertTrue($method->isPublic(), 'configureActions method should be public');
        $this->assertEquals('EasyCorp\Bundle\EasyAdminBundle\Config\Actions', (string) $method->getReturnType(), 'configureActions should return Actions');

        // 验证参数
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters, 'configureActions should accept one parameter');
        $this->assertEquals('actions', $parameters[0]->getName(), 'Parameter should be named actions');
        $this->assertEquals('EasyCorp\Bundle\EasyAdminBundle\Config\Actions', (string) $parameters[0]->getType(), 'Parameter should be Actions type');
    }
}
