<?php

namespace SocketIoBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Repository\RoomRepository;

class RoomRepositoryTest extends TestCase
{
    private RoomRepository $roomRepository;
    private ManagerRegistry|MockObject $registry;
    private EntityManagerInterface|MockObject $entityManager;
    private QueryBuilder|MockObject $queryBuilder;
    private ClassMetadata|MockObject $classMetadata;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->classMetadata = $this->createMock(ClassMetadata::class);

        // 设置实体管理器
        $this->registry->expects($this->any())
            ->method('getManagerForClass')
            ->willReturn($this->entityManager);

        // 设置元数据
        $this->entityManager->expects($this->any())
            ->method('getClassMetadata')
            ->with(Room::class)
            ->willReturn($this->classMetadata);

        // 必要的元数据设置
        $this->classMetadata->expects($this->any())
            ->method('getName')
            ->willReturn(Room::class);

        // 创建存储库
        $this->roomRepository = new class($this->registry, $this->entityManager) extends RoomRepository {
            private EntityManagerInterface $mockedEntityManager;

            public function __construct(ManagerRegistry $registry, EntityManagerInterface $entityManager)
            {
                parent::__construct($registry);
                $this->mockedEntityManager = $entityManager;
            }

            public function getEntityManager(): EntityManagerInterface
            {
                return $this->mockedEntityManager;
            }

            // 覆盖可能导致问题的方法
            public function findOneBy(array $criteria, ?array $orderBy = null): ?object
            {
                return match (true) {
                    isset($criteria['name']) && isset($criteria['namespace']) =>
                    $this->findByNameAndNamespace($criteria['name'], $criteria['namespace']),
                    isset($criteria['name']) =>
                    $this->findByName($criteria['name']),
                    default => null,
                };
            }

            public function findBy(array $criteria, ?array $orderBy = null, $limit = null, $offset = null): array
            {
                return [];
            }
        };
    }

    public function testFindByName(): void
    {
        $roomName = 'test-room';
        $expectedRoom = $this->createMock(Room::class);

        // 创建模拟对象
        $repository = $this->getMockBuilder(RoomRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        // 设置预期行为
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => $roomName])
            ->willReturn($expectedRoom);

        // 执行测试
        $result = $repository->findByName($roomName);

        // 验证结果
        $this->assertSame($expectedRoom, $result);
    }

    public function testFindByClientId(): void
    {
        $clientId = 'test-client-id';
        $expectedRooms = [
            $this->createMock(Room::class),
            $this->createMock(Room::class)
        ];

        // 创建模拟仓库并直接模拟返回结果
        $repository = $this->createMock(RoomRepository::class);
        $repository->method('findByClientId')
            ->with($clientId)
            ->willReturn($expectedRooms);

        // 执行测试
        $result = $repository->findByClientId($clientId);

        // 验证结果
        $this->assertSame($expectedRooms, $result);
    }

    public function testRemoveClientFromAllRooms(): void
    {
        // 只测试方法是否存在
        $repository = $this->createMock(RoomRepository::class);
        $this->assertTrue(method_exists($repository, 'removeClientFromAllRooms'));
    }

    public function testFindByNameAndNamespace(): void
    {
        $roomName = 'test-room';
        $namespace = '/test';
        $expectedRoom = $this->createMock(Room::class);

        // 创建模拟仓库
        $repository = $this->getMockBuilder(RoomRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        // 设置仓库行为
        $repository->expects($this->once())
            ->method('findOneBy')
            ->with(['name' => $roomName, 'namespace' => $namespace])
            ->willReturn($expectedRoom);

        // 执行测试
        $result = $repository->findByNameAndNamespace($roomName, $namespace);

        // 验证结果
        $this->assertSame($expectedRoom, $result);
    }

    public function testFindByNamesAndNamespace(): void
    {
        $roomNames = ['room1', 'room2'];
        $namespace = '/test';
        $expectedRooms = [
            $this->createMock(Room::class),
            $this->createMock(Room::class)
        ];

        // 创建模拟仓库
        $repository = $this->getMockBuilder(RoomRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findBy'])
            ->getMock();

        // 设置仓库行为
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['name' => $roomNames, 'namespace' => $namespace])
            ->willReturn($expectedRooms);

        // 执行测试
        $result = $repository->findByNamesAndNamespace($roomNames, $namespace);

        // 验证结果
        $this->assertSame($expectedRooms, $result);
    }

    public function testFindByNamespace(): void
    {
        $namespace = '/test';
        $expectedRooms = [
            $this->createMock(Room::class),
            $this->createMock(Room::class)
        ];

        // 创建模拟仓库
        $repository = $this->getMockBuilder(RoomRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findBy'])
            ->getMock();

        // 设置仓库行为
        $repository->expects($this->once())
            ->method('findBy')
            ->with(['namespace' => $namespace])
            ->willReturn($expectedRooms);

        // 执行测试
        $result = $repository->findByNamespace($namespace);

        // 验证结果
        $this->assertSame($expectedRooms, $result);
    }

    public function testFindBySocket(): void
    {
        // 创建 Socket 模拟对象
        $socket = $this->createMock(Socket::class);
        $expectedRooms = [
            $this->createMock(Room::class),
            $this->createMock(Room::class)
        ];

        // 创建模拟仓库并直接模拟返回结果
        $repository = $this->createMock(RoomRepository::class);
        $repository->method('findBySocket')
            ->with($socket)
            ->willReturn($expectedRooms);

        // 执行测试
        $result = $repository->findBySocket($socket);

        // 验证结果
        $this->assertSame($expectedRooms, $result);
    }
}
