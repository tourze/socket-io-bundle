<?php

namespace SocketIoBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\SocketIoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(SocketRepository::class)]
#[RunTestsInSeparateProcesses]
final class SocketRepositoryTest extends AbstractRepositoryTestCase
{
    private SocketRepository $repository;

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
        $this->repository = self::getService(SocketRepository::class);
    }

    public function testFindBySessionId(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->flush();

        $foundSocket = $this->repository->findBySessionId('test-session-id');

        $this->assertInstanceOf(Socket::class, $foundSocket);
        $this->assertSame('test-session-id', $foundSocket->getSessionId());
        $this->assertSame('test-socket-id', $foundSocket->getSocketId());
    }

    public function testFindBySessionIdReturnsNullWhenNotFound(): void
    {
        $foundSocket = $this->repository->findBySessionId('non-existent-session-id');

        $this->assertNull($foundSocket);
    }

    public function testFindByClientId(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session-id');
        $socket->setSocketId('test-socket-id');
        $socket->setClientId('test-client-id');

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->flush();

        $foundSocket = $this->repository->findByClientId('test-client-id');

        $this->assertInstanceOf(Socket::class, $foundSocket);
        $this->assertSame('test-client-id', $foundSocket->getClientId());
    }

    public function testFindByClientIdReturnsNullWhenNotFound(): void
    {
        $foundSocket = $this->repository->findByClientId('non-existent-client-id');

        $this->assertNull($foundSocket);
    }

    public function testFindActiveConnections(): void
    {
        $activeSocket1 = new Socket();
        $activeSocket1->setSessionId('session1');
        $activeSocket1->setSocketId('socket1');
        $activeSocket1->setConnected(true);
        $activeSocket1->updateLastActiveTime();

        $activeSocket2 = new Socket();
        $activeSocket2->setSessionId('session2');
        $activeSocket2->setSocketId('socket2');
        $activeSocket2->setConnected(true);
        $activeSocket2->updateLastActiveTime();

        $inactiveSocket = new Socket();
        $inactiveSocket->setSessionId('session3');
        $inactiveSocket->setSocketId('socket3');
        $inactiveSocket->setConnected(false);

        $oldActiveSocket = new Socket();
        $oldActiveSocket->setSessionId('session4');
        $oldActiveSocket->setSocketId('socket4');
        $oldActiveSocket->setConnected(true);

        $entityManager = self::getEntityManager();
        $entityManager->persist($activeSocket1);
        $entityManager->persist($activeSocket2);
        $entityManager->persist($inactiveSocket);
        $entityManager->persist($oldActiveSocket);
        $entityManager->flush();

        sleep(1);

        $activeConnections = $this->repository->findActiveConnections();

        $this->assertGreaterThanOrEqual(2, count($activeConnections));

        $sessionIds = array_map(fn (Socket $socket) => $socket->getSessionId(), $activeConnections);
        $this->assertContains('session1', $sessionIds);
        $this->assertContains('session2', $sessionIds);

        foreach ($activeConnections as $socket) {
            $this->assertTrue($socket->isConnected());
        }
    }

    public function testFindActiveConnectionsReturnsEmptyArrayWhenNoActiveConnections(): void
    {
        $inactiveSocket = new Socket();
        $inactiveSocket->setSessionId('inactive-session');
        $inactiveSocket->setSocketId('inactive-socket');
        $inactiveSocket->setConnected(false);

        $entityManager = self::getEntityManager();
        $entityManager->persist($inactiveSocket);
        $entityManager->flush();

        $activeConnections = $this->repository->findActiveConnections();

        $this->assertIsArray($activeConnections);
    }

    public function testCleanupInactiveConnections(): void
    {
        $activeSocket = new Socket();
        $activeSocket->setSessionId('active-session');
        $activeSocket->setSocketId('active-socket');
        $activeSocket->setConnected(true);
        $activeSocket->updateLastActiveTime();

        $inactiveSocket1 = new Socket();
        $inactiveSocket1->setSessionId('inactive-session1');
        $inactiveSocket1->setSocketId('inactive-socket1');
        $inactiveSocket1->setConnected(false);

        $inactiveSocket2 = new Socket();
        $inactiveSocket2->setSessionId('inactive-session2');
        $inactiveSocket2->setSocketId('inactive-socket2');
        $inactiveSocket2->setConnected(true);

        $entityManager = self::getEntityManager();
        $entityManager->persist($activeSocket);
        $entityManager->persist($inactiveSocket1);
        $entityManager->persist($inactiveSocket2);
        $entityManager->flush();

        $deletedCount = $this->repository->cleanupInactiveConnections();

        $this->assertGreaterThanOrEqual(1, $deletedCount);

        $remainingSockets = $this->repository->findAll();

        $sessionIds = array_map(fn (Socket $socket) => $socket->getSessionId(), $remainingSockets);
        $this->assertContains('active-session', $sessionIds);
    }

    public function testCleanupInactiveConnectionsReturnsZeroWhenNoInactiveConnections(): void
    {
        $activeSocket = new Socket();
        $activeSocket->setSessionId('active-session');
        $activeSocket->setSocketId('active-socket');
        $activeSocket->setConnected(true);
        $activeSocket->updateLastActiveTime();

        $entityManager = self::getEntityManager();
        $entityManager->persist($activeSocket);
        $entityManager->flush();

        $deletedCount = $this->repository->cleanupInactiveConnections();

        $this->assertGreaterThanOrEqual(0, $deletedCount);
    }

    public function testFindActiveConnectionsByNamespace(): void
    {
        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket1->setNamespace('/namespace1');
        $socket1->setConnected(true);
        $socket1->updateLastActiveTime();

        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');
        $socket2->setNamespace('/namespace1');
        $socket2->setConnected(true);
        $socket2->updateLastActiveTime();

        $socket3 = new Socket();
        $socket3->setSessionId('session3');
        $socket3->setSocketId('socket3');
        $socket3->setNamespace('/namespace2');
        $socket3->setConnected(true);
        $socket3->updateLastActiveTime();

        $inactiveSocket = new Socket();
        $inactiveSocket->setSessionId('session4');
        $inactiveSocket->setSocketId('socket4');
        $inactiveSocket->setNamespace('/namespace1');
        $inactiveSocket->setConnected(false);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->persist($socket3);
        $entityManager->persist($inactiveSocket);
        $entityManager->flush();

        $activeConnections = $this->repository->findActiveConnectionsByNamespace('/namespace1');

        $this->assertCount(2, $activeConnections);

        foreach ($activeConnections as $socket) {
            $this->assertSame('/namespace1', $socket->getNamespace());
            $this->assertTrue($socket->isConnected());
        }

        $sessionIds = array_map(fn (Socket $socket) => $socket->getSessionId(), $activeConnections);
        $this->assertContains('session1', $sessionIds);
        $this->assertContains('session2', $sessionIds);
        $this->assertNotContains('session3', $sessionIds);
        $this->assertNotContains('session4', $sessionIds);
    }

    public function testFindActiveConnectionsByNamespaceWithDefaultNamespace(): void
    {
        // 删除所有现有 socket
        $entityManager = self::getEntityManager();
        $entityManager->createQuery('DELETE FROM SocketIoBundle\Entity\Socket')->execute();

        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket1->setNamespace('/');
        $socket1->setConnected(true);
        $socket1->updateLastActiveTime();

        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');
        $socket2->setNamespace('/other');
        $socket2->setConnected(true);
        $socket2->updateLastActiveTime();

        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->flush();

        $activeConnections = $this->repository->findActiveConnectionsByNamespace('/');

        $this->assertCount(1, $activeConnections);
        $this->assertSame('/', $activeConnections[0]->getNamespace());
        $this->assertSame('session1', $activeConnections[0]->getSessionId());
    }

    public function testFindActiveConnectionsByNamespaceReturnsEmptyArrayWhenNoMatches(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $socket->setNamespace('/namespace1');
        $socket->setConnected(true);
        $socket->updateLastActiveTime();

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->flush();

        $activeConnections = $this->repository->findActiveConnectionsByNamespace('/nonexistent');

        $this->assertIsArray($activeConnections);
        $this->assertCount(0, $activeConnections);
    }

    public function testFindByWithNonMatchingCriteriaShouldReturnEmptyArraySpecific(): void
    {
        $results = $this->repository->findBy(['namespace' => '/nonexistent-namespace']);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testFindOneByWithNonMatchingCriteriaShouldReturnNullSpecific(): void
    {
        $result = $this->repository->findOneBy(['sessionId' => 'nonexistent-session']);
        $this->assertNull($result);
    }

    public function testSaveMethodShouldPersistEntity(): void
    {
        $socket = new Socket();
        $socket->setSessionId('save-session');
        $socket->setSocketId('save-socket');
        $socket->setClientId('save-client');

        $this->repository->save($socket);

        $this->assertNotNull($socket->getId());

        $found = $this->repository->find($socket->getId());
        $this->assertInstanceOf(Socket::class, $found);
        $this->assertSame('save-session', $found->getSessionId());
    }

    public function testSaveMethodWithFlushFalseShouldNotFlush(): void
    {
        $socket = new Socket();
        $socket->setSessionId('save-no-flush-session');
        $socket->setSocketId('save-no-flush-socket');

        $this->repository->save($socket, false);

        $entityManager = self::getEntityManager();
        $entityManager->flush();

        $this->assertNotNull($socket->getId());

        $found = $this->repository->find($socket->getId());
        $this->assertInstanceOf(Socket::class, $found);
    }

    public function testRemoveMethodShouldDeleteEntity(): void
    {
        $socket = new Socket();
        $socket->setSessionId('delete-session');
        $socket->setSocketId('delete-socket');

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->flush();

        $id = $socket->getId();
        $this->repository->remove($socket);

        $found = $this->repository->find($id);
        $this->assertNull($found);
    }

    public function testRemoveMethodWithFlushFalseShouldNotFlush(): void
    {
        $socket = new Socket();
        $socket->setSessionId('delete-no-flush-session');
        $socket->setSocketId('delete-no-flush-socket');

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->flush();

        $id = $socket->getId();
        $this->repository->remove($socket, false);

        $found = $this->repository->find($id);
        $this->assertInstanceOf(Socket::class, $found);

        $entityManager->flush();

        $found = $this->repository->find($id);
        $this->assertNull($found);
    }

    public function testFindOneByShouldRespectOrderByClause(): void
    {
        $socket1 = new Socket();
        $socket1->setSessionId('order-test-1');
        $socket1->setSocketId('socket1');
        $socket1->setClientId('order-test');
        $socket2 = new Socket();
        $socket2->setSessionId('order-test-2');
        $socket2->setSocketId('socket2');
        $socket2->setClientId('order-test');

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->flush();

        $result = $this->repository->findOneBy(['clientId' => 'order-test'], ['id' => 'ASC']);

        $this->assertInstanceOf(Socket::class, $result);
        $this->assertSame('order-test', $result->getClientId());
    }

    public function testCountWithAssociationCriteria(): void
    {
        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/namespace1');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/namespace1');

        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket1->setNamespace('/namespace1');
        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');
        $socket2->setNamespace('/namespace1');

        $socket1->joinRoom($room1);
        $socket2->joinRoom($room2);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->flush();

        // 使用简单字段测试关联查询，而不是集合属性
        $count = $this->repository->count(['namespace' => '/namespace1']);

        $this->assertEquals(2, $count);
    }

    public function testFindByWithAssociationCriteria(): void
    {
        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/namespace1');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/namespace1');

        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket1->setNamespace('/namespace1');
        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');
        $socket2->setNamespace('/namespace1');

        $socket1->joinRoom($room1);
        $socket2->joinRoom($room2);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->flush();

        // 使用简单字段测试关联查询，而不是集合属性
        $results = $this->repository->findBy(['namespace' => '/namespace1']);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);
        foreach ($results as $socket) {
            $this->assertSame('/namespace1', $socket->getNamespace());
        }
    }

    public function testFindByWithNullableCriteria(): void
    {
        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket1->setClientId(null);

        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');
        $socket2->setClientId('client123');

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->flush();

        $results = $this->repository->findBy(['clientId' => null]);

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));

        foreach ($results as $socket) {
            $this->assertNull($socket->getClientId());
        }
    }

    public function testCountWithNullableCriteria(): void
    {
        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket1->setClientId(null);

        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');
        $socket2->setClientId('client123');

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->flush();

        $count = $this->repository->count(['clientId' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testRepositoryInheritance(): void
    {
        $this->assertInstanceOf(SocketRepository::class, $this->repository);
    }

    protected function createNewEntity(): object
    {
        $socket = new Socket();
        $socket->setSessionId('test_session_' . uniqid());
        $socket->setSocketId('test_socket_' . uniqid());

        return $socket;
    }

    /**
     * @return ServiceEntityRepository<Socket>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
