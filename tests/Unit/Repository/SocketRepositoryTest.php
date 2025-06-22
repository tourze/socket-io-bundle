<?php

namespace SocketIoBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Repository\SocketRepository;

class SocketRepositoryTest extends TestCase
{
    private SocketRepository $repository;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $managerRegistry = $this->createMock(ManagerRegistry::class);
        
        $this->repository = new class($managerRegistry, $entityManager) extends SocketRepository {
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

            public function findOneBy(array $criteria, ?array $orderBy = null): ?object
            {
                if ($criteria === ['sessionId' => 'test-session-id']) {
                    $socket = new Socket('test-session-id', 'test-socket-id');
                    return $socket;
                }
                if ($criteria === ['clientId' => 'test-client-id']) {
                    $socket = new Socket('test-session-id', 'test-socket-id');
                    $socket->setClientId('test-client-id');
                    return $socket;
                }
                return null;
            }
        };
    }

    public function test_find_by_session_id_returns_socket_when_found(): void
    {
        $socket = $this->repository->findBySessionId('test-session-id');
        
        $this->assertInstanceOf(Socket::class, $socket);
        $this->assertSame('test-session-id', $socket->getSessionId());
        $this->assertSame('test-socket-id', $socket->getSocketId());
    }

    public function test_find_by_session_id_returns_null_when_not_found(): void
    {
        $socket = $this->repository->findBySessionId('non-existent-session-id');
        
        $this->assertNull($socket);
    }

    public function test_find_by_client_id_returns_socket_when_found(): void
    {
        $socket = $this->repository->findByClientId('test-client-id');
        
        $this->assertInstanceOf(Socket::class, $socket);
        $this->assertSame('test-client-id', $socket->getClientId());
    }

    public function test_find_by_client_id_returns_null_when_not_found(): void
    {
        $socket = $this->repository->findByClientId('non-existent-client-id');
        
        $this->assertNull($socket);
    }

    public function test_repository_inheritance(): void
    {
        $this->assertInstanceOf(SocketRepository::class, $this->repository);
    }

    public function test_repository_instance_valid(): void
    {
        // 测试存储库实例有效
        $this->assertInstanceOf(SocketRepository::class, $this->repository);
    }

    public function test_find_by_session_id_with_empty_string(): void
    {
        $socket = $this->repository->findBySessionId('');
        
        $this->assertNull($socket);
    }

    public function test_find_by_client_id_with_empty_string(): void
    {
        $socket = $this->repository->findByClientId('');
        
        $this->assertNull($socket);
    }

    public function test_repository_methods_exist(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        
        $this->assertTrue($reflection->hasMethod('findBySessionId'));
        $this->assertTrue($reflection->hasMethod('findByClientId'));
        $this->assertTrue($reflection->hasMethod('findActiveConnections'));
        $this->assertTrue($reflection->hasMethod('cleanupInactiveConnections'));
        $this->assertTrue($reflection->hasMethod('findActiveConnectionsByNamespace'));
    }

    public function test_find_by_session_id_parameter_type(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findBySessionId');
        $parameters = $method->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertSame('sessionId', $parameters[0]->getName());
        $this->assertTrue($parameters[0]->hasType());
        $type = $parameters[0]->getType();
        $this->assertSame('string', $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type);
    }

    public function test_find_by_client_id_parameter_type(): void
    {
        $reflection = new \ReflectionClass($this->repository);
        $method = $reflection->getMethod('findByClientId');
        $parameters = $method->getParameters();
        
        $this->assertCount(1, $parameters);
        $this->assertSame('clientId', $parameters[0]->getName());
        $this->assertTrue($parameters[0]->hasType());
        $type = $parameters[0]->getType();
        $this->assertSame('string', $type instanceof \ReflectionNamedType ? $type->getName() : (string) $type);
    }
} 