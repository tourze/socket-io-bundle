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

    public function test_entity_manager_is_accessible(): void
    {
        $this->assertInstanceOf(EntityManagerInterface::class, $this->repository->getEntityManager());
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
        $this->assertSame('SocketIoBundle\Entity\Socket', $paramType->getName());
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
        $this->assertSame('SocketIoBundle\Entity\Message', $paramType->getName());
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
        $this->assertSame('int', $paramType->getName());
    }

    public function test_find_pending_deliveries_return_type(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findPendingDeliveries');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('array', $returnType->getName());
    }

    public function test_find_message_deliveries_return_type(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findMessageDeliveries');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('array', $returnType->getName());
    }

    public function test_cleanup_old_deliveries_return_type(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('cleanupOldDeliveries');
        $returnType = $method->getReturnType();
        
        $this->assertNotNull($returnType);
        $this->assertSame('int', $returnType->getName());
    }

    public function test_repository_has_correct_entity_class(): void
    {
        // 通过检查方法存在性来验证这是正确的 Delivery Repository
        $this->assertTrue(method_exists($this->repository, 'findPendingDeliveries'));
        $this->assertTrue(method_exists($this->repository, 'findMessageDeliveries'));
        $this->assertTrue(method_exists($this->repository, 'cleanupOldDeliveries'));
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