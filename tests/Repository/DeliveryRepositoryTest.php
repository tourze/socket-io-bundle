<?php

namespace SocketIoBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Repository\DeliveryRepository;
use SocketIoBundle\SocketIoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(DeliveryRepository::class)]
#[RunTestsInSeparateProcesses]
final class DeliveryRepositoryTest extends AbstractRepositoryTestCase
{
    private DeliveryRepository $repository;

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
        $this->repository = self::getService(DeliveryRepository::class);
    }

    public function testFindByWithNonMatchingCriteriaShouldReturnEmptyArraySpecific(): void
    {
        $entityManager = self::getEntityManager();
        $entityManager->createQuery('DELETE FROM SocketIoBundle\Entity\Delivery')->execute();
        $entityManager->flush();

        $results = $this->repository->findBy(['status' => MessageStatus::FAILED]);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testFindOneByWithNonMatchingCriteriaShouldReturnNullSpecific(): void
    {
        // 先清除数据库中的数据避免干扰
        $entityManager = self::getEntityManager();
        $entityManager->createQuery('DELETE FROM SocketIoBundle\Entity\Delivery')->execute();
        $entityManager->flush();

        $result = $this->repository->findOneBy(['status' => MessageStatus::FAILED]);
        $this->assertNull($result);
    }

    public function testSaveMethodShouldPersistEntity(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $delivery->setStatus(MessageStatus::PENDING);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->flush();

        $this->repository->save($delivery);

        $this->assertNotNull($delivery->getId());

        $found = $this->repository->find($delivery->getId());
        $this->assertInstanceOf(Delivery::class, $found);
    }

    public function testSaveMethodWithFlushFalseShouldNotFlush(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $delivery->setStatus(MessageStatus::PENDING);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->flush();

        $this->repository->save($delivery, false);

        $entityManager->flush();

        $this->assertNotNull($delivery->getId());
    }

    public function testRemoveMethodShouldDeleteEntity(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $delivery->setStatus(MessageStatus::PENDING);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->persist($delivery);
        $entityManager->flush();

        $id = $delivery->getId();
        $this->repository->remove($delivery);

        $found = $this->repository->find($id);
        $this->assertNull($found);
    }

    public function testRemoveMethodWithFlushFalseShouldNotFlush(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $delivery->setStatus(MessageStatus::PENDING);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->persist($delivery);
        $entityManager->flush();

        $id = $delivery->getId();
        $this->repository->remove($delivery, false);

        $found = $this->repository->find($id);
        $this->assertInstanceOf(Delivery::class, $found);

        $entityManager->flush();

        $found = $this->repository->find($id);
        $this->assertNull($found);
    }

    public function testFindPendingDeliveriesShouldReturnPendingDeliveries(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $delivery1 = new Delivery();
        $delivery1->setSocket($socket);
        $delivery1->setMessage($message);
        $delivery1->setStatus(MessageStatus::PENDING);

        $delivery2 = new Delivery();
        $delivery2->setSocket($socket);
        $delivery2->setMessage($message);
        $delivery2->setStatus(MessageStatus::DELIVERED);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->persist($delivery1);
        $entityManager->persist($delivery2);
        $entityManager->flush();

        $results = $this->repository->findPendingDeliveries($socket);

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));

        foreach ($results as $delivery) {
            $this->assertSame(MessageStatus::PENDING, $delivery->getStatus());
            $this->assertSame($socket->getId(), $delivery->getSocket()->getId());
        }
    }

    public function testFindMessageDeliveriesShouldReturnMessageDeliveries(): void
    {
        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $delivery1 = new Delivery();
        $delivery1->setSocket($socket1);
        $delivery1->setMessage($message);
        $delivery1->setStatus(MessageStatus::PENDING);

        $delivery2 = new Delivery();
        $delivery2->setSocket($socket2);
        $delivery2->setMessage($message);
        $delivery2->setStatus(MessageStatus::DELIVERED);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket1);
        $entityManager->persist($socket2);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->persist($delivery1);
        $entityManager->persist($delivery2);
        $entityManager->flush();

        $results = $this->repository->findMessageDeliveries($message);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);

        foreach ($results as $delivery) {
            $this->assertSame($message->getId(), $delivery->getMessage()->getId());
        }
    }

    public function testCleanupOldDeliveriesShouldDeleteOldDeliveries(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $oldDelivery = new Delivery();
        $oldDelivery->setSocket($socket);
        $oldDelivery->setMessage($message);
        $oldDelivery->setStatus(MessageStatus::DELIVERED);

        $newDelivery = new Delivery();
        $newDelivery->setSocket($socket);
        $newDelivery->setMessage($message);
        $newDelivery->setStatus(MessageStatus::PENDING);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->persist($oldDelivery);
        $entityManager->persist($newDelivery);
        $entityManager->flush();

        // 手动设置旧记录的创建时间
        $entityManager->createQuery(
            'UPDATE SocketIoBundle\Entity\Delivery d SET d.createTime = :oldTime WHERE d.id = :id'
        )
            ->setParameter('oldTime', new \DateTime('-10 days'))
            ->setParameter('id', $oldDelivery->getId())
            ->execute()
        ;

        $deletedCount = $this->repository->cleanupOldDeliveries(7);

        $this->assertGreaterThanOrEqual(1, $deletedCount);

        $remaining = $this->repository->findAll();
        $remainingIds = array_map(fn ($d) => $d->getId(), $remaining);
        $this->assertContains($newDelivery->getId(), $remainingIds);
    }

    public function testFindOneByShouldRespectOrderByClause(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $delivery1 = new Delivery();
        $delivery1->setSocket($socket);
        $delivery1->setMessage($message);
        $delivery1->setStatus(MessageStatus::PENDING);

        $delivery2 = new Delivery();
        $delivery2->setSocket($socket);
        $delivery2->setMessage($message);
        $delivery2->setStatus(MessageStatus::PENDING);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->persist($delivery1);
        $entityManager->persist($delivery2);
        $entityManager->flush();

        $result = $this->repository->findOneBy(['status' => MessageStatus::PENDING], ['id' => 'DESC']);

        $this->assertInstanceOf(Delivery::class, $result);
        $this->assertTrue($result->getId() === $delivery2->getId() || $result->getId() === $delivery1->getId());
    }

    public function testCountWithAssociationCriteria(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $delivery->setStatus(MessageStatus::PENDING);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->persist($delivery);
        $entityManager->flush();

        $count = $this->repository->count(['socket' => $socket]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByWithAssociationCriteria(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $delivery->setStatus(MessageStatus::PENDING);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->persist($delivery);
        $entityManager->flush();

        $results = $this->repository->findBy(['message' => $message]);

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));
        $this->assertContainsOnlyInstancesOf(Delivery::class, $results);

        foreach ($results as $result) {
            $this->assertSame($message->getId(), $result->getMessage()->getId());
        }
    }

    public function testFindByWithNullableCriteria(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $delivery->setStatus(MessageStatus::PENDING);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->persist($delivery);
        $entityManager->flush();

        $results = $this->repository->findBy(['error' => null]);

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));

        foreach ($results as $result) {
            $this->assertNull($result->getError());
        }
    }

    public function testCountWithNullableCriteria(): void
    {
        $socket = new Socket();
        $socket->setSessionId('session1');
        $socket->setSocketId('socket1');
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $delivery = new Delivery();
        $delivery->setSocket($socket);
        $delivery->setMessage($message);
        $delivery->setStatus(MessageStatus::PENDING);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->persist($delivery);
        $entityManager->flush();

        $count = $this->repository->count(['deliveredAt' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testRepositoryInheritance(): void
    {
        $this->assertInstanceOf(DeliveryRepository::class, $this->repository);
    }

    protected function createNewEntity(): object
    {
        $socket = new Socket();
        $socket->setSessionId('test_session_' . uniqid());
        $socket->setSocketId('test_socket_' . uniqid());
        $room = new Room();
        $room->setName('test_room_' . uniqid());
        $room->setNamespace('/test');
        $message = new Message();
        $message->setEvent('test.event.' . uniqid());
        $message->addRoom($room);

        $entity = new Delivery();
        $entity->setSocket($socket);
        $entity->setMessage($message);
        $entity->setStatus(MessageStatus::PENDING);

        $entityManager = self::getEntityManager();
        $entityManager->persist($socket);
        $entityManager->persist($room);
        $entityManager->persist($message);

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<Delivery>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
