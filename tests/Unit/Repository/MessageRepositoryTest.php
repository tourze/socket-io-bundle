<?php

namespace SocketIoBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Repository\MessageRepository;

class MessageRepositoryTest extends TestCase
{
    private MessageRepository $repository;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        
        $this->repository = new class($managerRegistry, $entityManager) extends MessageRepository {
            /** @var EntityManagerInterface */
            private $entityManager;

            public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
            {
                parent::__construct($registry);
                $this->entityManager = $entityManager;
            }

            public function getEntityManager(): EntityManagerInterface
            {
                return $this->entityManager;
            }
        };
    }

    public function test_repository_inheritance(): void
    {
        $this->assertInstanceOf(MessageRepository::class, $this->repository);
    }

    public function test_repository_instance(): void
    {
        // Test that repository is properly instantiated
        $this->assertInstanceOf(MessageRepository::class, $this->repository);
    }

    public function test_repository_methods_exist(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        
        $this->assertTrue($reflection->hasMethod('findRoomMessages'));
        $this->assertTrue($reflection->hasMethod('findUserMessages'));
        $this->assertTrue($reflection->hasMethod('cleanupOldMessages'));
    }

    public function test_find_room_messages_method_signature(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findRoomMessages');
        $parameters = $method->getParameters();
        
        $this->assertCount(3, $parameters);
        $this->assertSame('room', $parameters[0]->getName());
        $this->assertSame('limit', $parameters[1]->getName());
        $this->assertSame('before', $parameters[2]->getName());
        
        // 检查默认值
        $this->assertTrue($parameters[1]->isDefaultValueAvailable());
        $this->assertSame(50, $parameters[1]->getDefaultValue());
        $this->assertTrue($parameters[2]->isDefaultValueAvailable());
        $this->assertNull($parameters[2]->getDefaultValue());
    }

    public function test_find_user_messages_method_signature(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findUserMessages');
        $parameters = $method->getParameters();
        
        $this->assertCount(3, $parameters);
        $this->assertSame('userId', $parameters[0]->getName());
        $this->assertSame('limit', $parameters[1]->getName());
        $this->assertSame('before', $parameters[2]->getName());
        
        // 检查默认值
        $this->assertTrue($parameters[1]->isDefaultValueAvailable());
        $this->assertSame(50, $parameters[1]->getDefaultValue());
        $this->assertTrue($parameters[2]->isDefaultValueAvailable());
        $this->assertNull($parameters[2]->getDefaultValue());
    }

    public function test_cleanup_old_messages_method_signature(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('cleanupOldMessages');
        $parameters = $method->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertSame('days', $parameters[0]->getName());
        
        // 检查默认值
        $this->assertTrue($parameters[0]->isDefaultValueAvailable());
        $this->assertSame(30, $parameters[0]->getDefaultValue());
    }

    public function test_cleanup_old_messages_return_type(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('cleanupOldMessages');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('int', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function test_find_room_messages_return_type(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findRoomMessages');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('array', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function test_find_user_messages_return_type(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findUserMessages');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('array', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function test_repository_has_correct_methods_implementation(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        
        // 验证 findRoomMessages 方法
        $this->assertTrue($reflection->hasMethod('findRoomMessages'));
        $findRoomMessagesMethod = $reflection->getMethod('findRoomMessages');
        $this->assertTrue($findRoomMessagesMethod->isPublic());
        
        // 验证 findUserMessages 方法
        $this->assertTrue($reflection->hasMethod('findUserMessages'));
        $findUserMessagesMethod = $reflection->getMethod('findUserMessages');
        $this->assertTrue($findUserMessagesMethod->isPublic());
        
        // 验证 cleanupOldMessages 方法
        $this->assertTrue($reflection->hasMethod('cleanupOldMessages'));
        $cleanupMethod = $reflection->getMethod('cleanupOldMessages');
        $this->assertTrue($cleanupMethod->isPublic());
        
        // 验证返回类型
        $returnType1 = $findRoomMessagesMethod->getReturnType();
        $this->assertSame('array', $returnType1 instanceof \ReflectionNamedType ? $returnType1->getName() : (string) $returnType1);
        $returnType2 = $findUserMessagesMethod->getReturnType();
        $this->assertSame('array', $returnType2 instanceof \ReflectionNamedType ? $returnType2->getName() : (string) $returnType2);
        $returnType3 = $cleanupMethod->getReturnType();
        $this->assertSame('int', $returnType3 instanceof \ReflectionNamedType ? $returnType3->getName() : (string) $returnType3);
    }
} 