<?php

namespace SocketIoBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Repository\DeliveryRepository;

class DeliveryRepositoryTest extends TestCase
{
    private DeliveryRepository $repository;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        
        $this->repository = new class($managerRegistry, $entityManager) extends DeliveryRepository {
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
        $this->assertInstanceOf(DeliveryRepository::class, $this->repository);
    }

    public function test_repository_instance(): void
    {
        // Test that repository is properly instantiated
        $this->assertInstanceOf(DeliveryRepository::class, $this->repository);
    }

    public function test_repository_methods_exist(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        
        $this->assertTrue($reflection->hasMethod('findPendingDeliveries'));
        $this->assertTrue($reflection->hasMethod('findMessageDeliveries'));
        $this->assertTrue($reflection->hasMethod('cleanupOldDeliveries'));
    }

    public function test_find_pending_deliveries_method_signature(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findPendingDeliveries');
        $parameters = $method->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertSame('socket', $parameters[0]->getName());
        
        // 检查参数类型
        $paramType = $parameters[0]->getType();
        $this->assertNotNull($paramType);
        $this->assertSame('SocketIoBundle\Entity\Socket', $paramType instanceof \ReflectionNamedType ? $paramType->getName() : (string) $paramType);
    }

    public function test_find_message_deliveries_method_signature(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findMessageDeliveries');
        $parameters = $method->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertSame('message', $parameters[0]->getName());
        
        // 检查参数类型
        $paramType = $parameters[0]->getType();
        $this->assertNotNull($paramType);
        $this->assertSame('SocketIoBundle\Entity\Message', $paramType instanceof \ReflectionNamedType ? $paramType->getName() : (string) $paramType);
    }

    public function test_cleanup_old_deliveries_method_signature(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('cleanupOldDeliveries');
        $parameters = $method->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertSame('days', $parameters[0]->getName());
        
        // 检查默认值
        $this->assertTrue($parameters[0]->isDefaultValueAvailable());
        $this->assertSame(7, $parameters[0]->getDefaultValue());
        
        // 检查参数类型
        $paramType = $parameters[0]->getType();
        $this->assertNotNull($paramType);
        $this->assertSame('int', $paramType instanceof \ReflectionNamedType ? $paramType->getName() : (string) $paramType);
    }

    public function test_find_pending_deliveries_return_type(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findPendingDeliveries');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('array', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function test_find_message_deliveries_return_type(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findMessageDeliveries');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('array', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function test_cleanup_old_deliveries_return_type(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('cleanupOldDeliveries');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('int', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function test_repository_has_correct_methods_implementation(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        
        // 验证 findPendingDeliveries 方法
        $this->assertTrue($reflection->hasMethod('findPendingDeliveries'));
        $findPendingMethod = $reflection->getMethod('findPendingDeliveries');
        $this->assertTrue($findPendingMethod->isPublic());
        
        // 验证 findMessageDeliveries 方法
        $this->assertTrue($reflection->hasMethod('findMessageDeliveries'));
        $findMessageMethod = $reflection->getMethod('findMessageDeliveries');
        $this->assertTrue($findMessageMethod->isPublic());
        
        // 验证 cleanupOldDeliveries 方法
        $this->assertTrue($reflection->hasMethod('cleanupOldDeliveries'));
        $cleanupMethod = $reflection->getMethod('cleanupOldDeliveries');
        $this->assertTrue($cleanupMethod->isPublic());
        
        // 验证返回类型
        $returnType1 = $findPendingMethod->getReturnType();
        $this->assertSame('array', $returnType1 instanceof \ReflectionNamedType ? $returnType1->getName() : (string) $returnType1);
        $returnType2 = $findMessageMethod->getReturnType();
        $this->assertSame('array', $returnType2 instanceof \ReflectionNamedType ? $returnType2->getName() : (string) $returnType2);
        $returnType3 = $cleanupMethod->getReturnType();
        $this->assertSame('int', $returnType3 instanceof \ReflectionNamedType ? $returnType3->getName() : (string) $returnType3);
    }

    public function test_all_methods_have_correct_visibility(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        
        $findPendingMethod = $reflection->getMethod('findPendingDeliveries');
        $this->assertTrue($findPendingMethod->isPublic());
        
        $findMessageMethod = $reflection->getMethod('findMessageDeliveries');
        $this->assertTrue($findMessageMethod->isPublic());
        
        $cleanupMethod = $reflection->getMethod('cleanupOldDeliveries');
        $this->assertTrue($cleanupMethod->isPublic());
    }

    public function test_method_names_follow_convention(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        
        $customMethods = array_filter($methods, function(\ReflectionMethod $method) {
            $name = $method->getName();
            // 排除魔术方法
            if (str_starts_with($name, '__')) {
                return false;
            }
            // 排除基础 Repository 方法
            return !in_array($name, [
                'getEntityManager', 'find', 'findAll', 'findBy', 'findOneBy',
                'getClassName', 'getEntityName', 'createQueryBuilder', 'count', 'matching'
            ]);
        });
        
        foreach ($customMethods as $method) {
            $name = $method->getName();
            $this->assertMatchesRegularExpression('/^(find|cleanup|get|create|update|delete)/', $name, 
                "Method '{$name}' should follow naming convention");
        }
    }
} 