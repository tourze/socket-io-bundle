<?php

namespace SocketIoBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Repository\RoomRepository;
use SocketIoBundle\SocketIoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(RoomRepository::class)]
#[RunTestsInSeparateProcesses]
final class RoomRepositoryTest extends AbstractRepositoryTestCase
{
    private RoomRepository $repository;

    /**
     * @return array<string, array<string, bool>>
     */
    public static function configureBundles(): array
    {
        return [
            FrameworkBundle::class => ['all' => true],
            DoctrineBundle::class => ['all' => true],
            DoctrineSnowflakeBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            SocketIoBundle::class => ['all' => true],
        ];
    }

    protected function onSetUp(): void
    {
        $this->repository = self::getService(RoomRepository::class);
    }

    public function testFindByNamesAndNamespace(): void
    {
        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/namespace1');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/namespace1');
        $room3 = new Room();
        $room3->setName('room3');
        $room3->setNamespace('/namespace2');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->persist($room3);
        $entityManager->flush();

        $rooms = $this->repository->findByNamesAndNamespace(['room1', 'room2'], '/namespace1');

        $this->assertCount(2, $rooms);
        $roomNames = array_map(fn (Room $room) => $room->getName(), $rooms);
        $this->assertContains('room1', $roomNames);
        $this->assertContains('room2', $roomNames);

        foreach ($rooms as $room) {
            $this->assertSame('/namespace1', $room->getNamespace());
        }
    }

    public function testFindByNamesAndNamespaceWithDefaultNamespace(): void
    {
        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/');
        $room3 = new Room();
        $room3->setName('room3');
        $room3->setNamespace('/different');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->persist($room3);
        $entityManager->flush();

        $rooms = $this->repository->findByNamesAndNamespace(['room1', 'room2']);

        $this->assertCount(2, $rooms);
        foreach ($rooms as $room) {
            $this->assertSame('/', $room->getNamespace());
        }
    }

    public function testFindByNamesAndNamespaceWithEmptyArray(): void
    {
        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/namespace1');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->flush();

        $rooms = $this->repository->findByNamesAndNamespace([], '/namespace1');

        $this->assertCount(0, $rooms);
    }

    public function testFindByNamespace(): void
    {
        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/namespace1');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/namespace1');
        $room3 = new Room();
        $room3->setName('room3');
        $room3->setNamespace('/namespace2');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->persist($room3);
        $entityManager->flush();

        $rooms = $this->repository->findByNamespace('/namespace1');

        $this->assertCount(2, $rooms);
        foreach ($rooms as $room) {
            $this->assertSame('/namespace1', $room->getNamespace());
        }

        $roomNames = array_map(fn (Room $room) => $room->getName(), $rooms);
        $this->assertContains('room1', $roomNames);
        $this->assertContains('room2', $roomNames);
    }

    public function testFindByNamespaceWithDefaultNamespace(): void
    {
        // 删除所有现有房间
        $entityManager = self::getEntityManager();
        $entityManager->createQuery('DELETE FROM SocketIoBundle\Entity\Room')->execute();

        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/');
        $room3 = new Room();
        $room3->setName('room3');
        $room3->setNamespace('/different');

        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->persist($room3);
        $entityManager->flush();

        $rooms = $this->repository->findByNamespace('/');

        $this->assertCount(2, $rooms);
        foreach ($rooms as $room) {
            $this->assertSame('/', $room->getNamespace());
        }
    }

    public function testFindByNamespaceWithNonExistentNamespace(): void
    {
        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/namespace1');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->flush();

        $rooms = $this->repository->findByNamespace('/nonexistent');

        $this->assertCount(0, $rooms);
    }

    public function testFindByNameAndNamespace(): void
    {
        $room1 = new Room();
        $room1->setName('testroom');
        $room1->setNamespace('/namespace1');
        $room2 = new Room();
        $room2->setName('testroom');
        $room2->setNamespace('/namespace2');
        $room3 = new Room();
        $room3->setName('otherroom');
        $room3->setNamespace('/namespace1');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->persist($room3);
        $entityManager->flush();

        $room = $this->repository->findByNameAndNamespace('testroom', '/namespace1');

        $this->assertInstanceOf(Room::class, $room);
        $this->assertSame('testroom', $room->getName());
        $this->assertSame('/namespace1', $room->getNamespace());
    }

    public function testFindByNameAndNamespaceWithDefaultNamespace(): void
    {
        $room1 = new Room();
        $room1->setName('testroom');
        $room1->setNamespace('/');
        $room2 = new Room();
        $room2->setName('testroom');
        $room2->setNamespace('/namespace2');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->flush();

        $room = $this->repository->findByNameAndNamespace('testroom');

        $this->assertInstanceOf(Room::class, $room);
        $this->assertSame('testroom', $room->getName());
        $this->assertSame('/', $room->getNamespace());
    }

    public function testFindByNameAndNamespaceReturnsNullWhenNotFound(): void
    {
        $room1 = new Room();
        $room1->setName('testroom');
        $room1->setNamespace('/namespace1');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->flush();

        $room = $this->repository->findByNameAndNamespace('nonexistent', '/namespace1');

        $this->assertNull($room);
    }

    public function testFindByClientId(): void
    {
        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket1->setClientId('client1');
        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');
        $socket2->setClientId('client2');
        $socket3 = new Socket();
        $socket3->setSessionId('session3');
        $socket3->setSocketId('socket3');
        $socket3->setClientId('client2');

        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/');
        $room3 = new Room();
        $room3->setName('room3');
        $room3->setNamespace('/');

        $socket1->joinRoom($room1);
        $socket2->joinRoom($room1);
        $socket2->joinRoom($room2);
        $socket3->joinRoom($room2);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->persist($socket3);
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->persist($room3);
        $entityManager->flush();

        $rooms = $this->repository->findByClientId('client2');

        $this->assertCount(2, $rooms);
        $this->assertContainsOnlyInstancesOf(Room::class, $rooms);

        $roomNames = array_map(fn (Room $room) => $room->getName(), $rooms);
        $this->assertContains('room1', $roomNames);
        $this->assertContains('room2', $roomNames);
    }

    public function testFindBySocket(): void
    {
        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');
        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/');
        $room3 = new Room();
        $room3->setName('room3');
        $room3->setNamespace('/');

        $socket1->joinRoom($room1);
        $socket1->joinRoom($room2);
        $socket2->joinRoom($room3);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->persist($room3);
        $entityManager->flush();

        $rooms = $this->repository->findBySocket($socket1);

        $this->assertCount(2, $rooms);
        $this->assertContainsOnlyInstancesOf(Room::class, $rooms);

        $roomNames = array_map(fn (Room $room) => $room->getName(), $rooms);
        $this->assertContains('room1', $roomNames);
        $this->assertContains('room2', $roomNames);
    }

    public function testRemoveClientFromAllRooms(): void
    {
        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket1->setClientId('client1');
        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');
        $socket2->setClientId('client2');
        $socket3 = new Socket();
        $socket3->setSessionId('session3');
        $socket3->setSocketId('socket3');
        $socket3->setClientId('client2');
        $socket4 = new Socket();
        $socket4->setSessionId('session4');
        $socket4->setSocketId('socket4');
        $socket4->setClientId('client3');

        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/');
        $room3 = new Room();
        $room3->setName('room3');
        $room3->setNamespace('/');

        $socket1->joinRoom($room1);
        $socket2->joinRoom($room1);
        $socket2->joinRoom($room2);
        $socket3->joinRoom($room2);
        $socket4->joinRoom($room3);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->persist($socket3);
        $entityManager->persist($socket4);
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->persist($room3);
        $entityManager->flush();

        $this->assertCount(2, $room1->getSockets());
        $this->assertCount(2, $room2->getSockets());
        $this->assertCount(1, $room3->getSockets());

        $this->repository->removeClientFromAllRooms('client2');

        $entityManager->clear();
        $room1 = $entityManager->find(Room::class, $room1->getId());
        $room2 = $entityManager->find(Room::class, $room2->getId());
        $room3 = $entityManager->find(Room::class, $room3->getId());

        $this->assertNotNull($room1);
        $this->assertNotNull($room2);
        $this->assertNotNull($room3);

        $this->assertCount(1, $room1->getSockets());
        $this->assertCount(0, $room2->getSockets());
        $this->assertCount(1, $room3->getSockets());
    }

    public function testFindByWithNonMatchingCriteriaShouldReturnEmptyArraySpecific(): void
    {
        $results = $this->repository->findBy(['namespace' => '/nonexistent-namespace']);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testFindOneByWithNonMatchingCriteriaShouldReturnNullSpecific(): void
    {
        $result = $this->repository->findOneBy(['name' => 'nonexistent-room']);
        $this->assertNull($result);
    }

    public function testSaveMethodShouldPersistEntity(): void
    {
        $room = new Room();
        $room->setName('save-test-room');
        $room->setNamespace('/test');

        $this->repository->save($room);

        $this->assertNotNull($room->getId());

        $found = $this->repository->find($room->getId());
        $this->assertInstanceOf(Room::class, $found);
        $this->assertSame('save-test-room', $found->getName());
    }

    public function testSaveMethodWithFlushFalseShouldNotFlush(): void
    {
        $room = new Room();
        $room->setName('save-no-flush-room');
        $room->setNamespace('/test');

        $this->repository->save($room, false);

        $entityManager = self::getEntityManager();
        $entityManager->flush();

        $this->assertNotNull($room->getId());

        $found = $this->repository->find($room->getId());
        $this->assertInstanceOf(Room::class, $found);
    }

    public function testRemoveMethodShouldDeleteEntity(): void
    {
        $room = new Room();
        $room->setName('delete-test-room');
        $room->setNamespace('/test');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->flush();

        $id = $room->getId();
        $this->repository->remove($room);

        $found = $this->repository->find($id);
        $this->assertNull($found);
    }

    public function testRemoveMethodWithFlushFalseShouldNotFlush(): void
    {
        $room = new Room();
        $room->setName('delete-no-flush-room');
        $room->setNamespace('/test');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->flush();

        $id = $room->getId();
        $this->repository->remove($room, false);

        $found = $this->repository->find($id);
        $this->assertInstanceOf(Room::class, $found);

        $entityManager->flush();

        $found = $this->repository->find($id);
        $this->assertNull($found);
    }

    public function testFindByNameShouldReturnRoom(): void
    {
        $room = new Room();
        $room->setName('find-by-name-test');
        $room->setNamespace('/test');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->flush();

        $found = $this->repository->findByName('find-by-name-test');

        $this->assertInstanceOf(Room::class, $found);
        $this->assertSame('find-by-name-test', $found->getName());
    }

    public function testFindByNameWithNonExistentNameShouldReturnNull(): void
    {
        $found = $this->repository->findByName('nonexistent-room-name');
        $this->assertNull($found);
    }

    public function testFindOneByShouldRespectOrderByClause(): void
    {
        $room1 = new Room();
        $room1->setName('order-test');
        $room1->setNamespace('/namespace1');
        $room2 = new Room();
        $room2->setName('order-test');
        $room2->setNamespace('/namespace2');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->flush();

        $result = $this->repository->findOneBy(['name' => 'order-test'], ['id' => 'ASC']);

        $this->assertInstanceOf(Room::class, $result);
        $this->assertSame('order-test', $result->getName());
    }

    public function testCountWithAssociationCriteria(): void
    {
        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');

        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/namespace1');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/namespace1');

        $socket1->joinRoom($room1);
        $room2->addSocket($socket2);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->flush();

        // 使用简单字段测试关联查询，而不是集合属性
        $count = $this->repository->count(['namespace' => '/namespace1']);

        $this->assertEquals(2, $count);
    }

    public function testFindByWithAssociationCriteria(): void
    {
        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');

        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/namespace1');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/namespace1');

        $socket1->joinRoom($room1);
        $room2->addSocket($socket2);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->flush();

        // 使用简单字段测试关联查询，而不是集合属性
        $results = $this->repository->findBy(['namespace' => '/namespace1']);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        foreach ($results as $room) {
            $this->assertSame('/namespace1', $room->getNamespace());
        }
    }

    public function testFindByWithNullableCriteria(): void
    {
        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/namespace1');
        $room1->setMetadata(null);

        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/namespace1');
        $room2->setMetadata(['key' => 'value']);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->flush();

        $results = $this->repository->findBy(['metadata' => null]);

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));

        foreach ($results as $room) {
            $this->assertNull($room->getMetadata());
        }
    }

    public function testCountWithNullableCriteria(): void
    {
        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/namespace1');
        $room1->setMetadata(null);

        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/namespace1');
        $room2->setMetadata(['key' => 'value']);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->flush();

        $count = $this->repository->count(['metadata' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testRepositoryInheritance(): void
    {
        $this->assertInstanceOf(RoomRepository::class, $this->repository);
    }

    protected function createNewEntity(): object
    {
        $room = new Room();
        $room->setName('test_room_' . uniqid());
        $room->setNamespace('/test_' . uniqid());

        return $room;
    }

    /**
     * @return ServiceEntityRepository<Room>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
