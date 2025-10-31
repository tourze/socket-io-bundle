<?php

namespace SocketIoBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Repository\MessageRepository;
use SocketIoBundle\SocketIoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(MessageRepository::class)]
#[RunTestsInSeparateProcesses]
final class MessageRepositoryTest extends AbstractRepositoryTestCase
{
    private MessageRepository $repository;

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
        $this->repository = self::getService(MessageRepository::class);
    }

    public function testFindByWithNonMatchingCriteriaShouldReturnEmptyArraySpecific(): void
    {
        $results = $this->repository->findBy(['event' => 'nonexistent.event']);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    public function testFindOneByWithNonMatchingCriteriaShouldReturnNullSpecific(): void
    {
        $result = $this->repository->findOneBy(['event' => 'nonexistent.event']);
        $this->assertNull($result);
    }

    public function testSaveMethodShouldPersistEntity(): void
    {
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->flush();

        $message->addRoom($room);

        $this->repository->save($message);

        $this->assertNotNull($message->getId());

        $found = $this->repository->find($message->getId());
        $this->assertInstanceOf(Message::class, $found);
    }

    public function testSaveMethodWithFlushFalseShouldNotFlush(): void
    {
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->flush();

        $message->addRoom($room);

        $this->repository->save($message, false);

        $entityManager->flush();

        $this->assertNotNull($message->getId());
    }

    public function testRemoveMethodShouldDeleteEntity(): void
    {
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->flush();

        $id = $message->getId();
        $this->repository->remove($message);

        $found = $this->repository->find($id);
        $this->assertNull($found);
    }

    public function testRemoveMethodWithFlushFalseShouldNotFlush(): void
    {
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message = new Message();
        $message->setEvent('test.event');
        $message->addRoom($room);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->persist($message);
        $entityManager->flush();

        $id = $message->getId();
        $this->repository->remove($message, false);

        $found = $this->repository->find($id);
        $this->assertInstanceOf(Message::class, $found);

        $entityManager->flush();

        $found = $this->repository->find($id);
        $this->assertNull($found);
    }

    public function testFindRoomMessagesShouldReturnRoomMessages(): void
    {
        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/namespace1');
        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/namespace1');

        $message1 = new Message();
        $message1->setEvent('event1');
        $message1->addRoom($room1);

        $message2 = new Message();
        $message2->setEvent('event2');
        $message2->addRoom($room1);

        $message3 = new Message();
        $message3->setEvent('event3');
        $message3->addRoom($room2);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room1);
        $entityManager->persist($room2);
        $entityManager->persist($message1);
        $entityManager->persist($message2);
        $entityManager->persist($message3);
        $entityManager->flush();

        $results = $this->repository->findRoomMessages($room1);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);

        foreach ($results as $message) {
            $this->assertTrue($message->getRooms()->contains($room1));
        }
    }

    public function testFindUserMessagesShouldReturnUserMessages(): void
    {
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $sender = new Socket();
        $sender->setSessionId('session1');
        $sender->setSocketId('socket1');
        $sender->setClientId('user1');

        $message1 = new Message();
        $message1->setEvent('event1');
        $message1->addRoom($room);
        $message1->setSender($sender);

        $message2 = new Message();
        $message2->setEvent('event2');
        $message2->addRoom($room);
        $message2->setSender($sender);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->persist($sender);
        $entityManager->persist($message1);
        $entityManager->persist($message2);
        $entityManager->flush();

        $senderId = $sender->getId();
        $this->assertNotNull($senderId);
        $results = $this->repository->findUserMessages($senderId);

        $this->assertIsArray($results);
        $this->assertCount(2, $results);

        foreach ($results as $message) {
            $messageSender = $message->getSender();
            $this->assertNotNull($messageSender);
            $this->assertSame($senderId, $messageSender->getId());
        }
    }

    public function testCleanupOldMessagesShouldDeleteOldMessages(): void
    {
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');

        $oldMessage = new Message();
        $oldMessage->setEvent('old.event');
        $oldMessage->addRoom($room);

        $newMessage = new Message();
        $newMessage->setEvent('new.event');
        $newMessage->addRoom($room);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->persist($oldMessage);
        $entityManager->persist($newMessage);
        $entityManager->flush();

        // 手动设置旧记录的创建时间
        $entityManager->createQuery(
            'UPDATE SocketIoBundle\Entity\Message m SET m.createTime = :oldTime WHERE m.id = :id'
        )
            ->setParameter('oldTime', new \DateTime('-40 days'))
            ->setParameter('id', $oldMessage->getId())
            ->execute()
        ;

        $deletedCount = $this->repository->cleanupOldMessages(30);

        $this->assertGreaterThanOrEqual(1, $deletedCount);

        $remaining = $this->repository->findAll();
        $remainingIds = array_map(fn ($m) => $m->getId(), $remaining);
        $this->assertContains($newMessage->getId(), $remainingIds);
    }

    public function testFindOneByShouldRespectOrderByClause(): void
    {
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message1 = new Message();
        $message1->setEvent('order.test');
        $message1->addRoom($room);
        $message2 = new Message();
        $message2->setEvent('order.test');
        $message2->addRoom($room);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->persist($message1);
        $entityManager->persist($message2);
        $entityManager->flush();

        $result = $this->repository->findOneBy(['event' => 'order.test'], ['id' => 'ASC']);

        $this->assertInstanceOf(Message::class, $result);
        $this->assertSame('order.test', $result->getEvent());
    }

    public function testCountWithAssociationCriteria(): void
    {
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $sender = new Socket();
        $sender->setSessionId('session1');
        $sender->setSocketId('socket1');
        $sender->setClientId('user1');

        $message1 = new Message();
        $message1->setEvent('event1');
        $message1->addRoom($room);
        $message1->setSender($sender);

        $message2 = new Message();
        $message2->setEvent('event2');
        $message2->addRoom($room);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->persist($sender);
        $entityManager->persist($message1);
        $entityManager->persist($message2);
        $entityManager->flush();

        $count = $this->repository->count(['sender' => $sender]);

        $this->assertEquals(1, $count);
    }

    public function testFindByWithAssociationCriteria(): void
    {
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $sender = new Socket();
        $sender->setSessionId('session1');
        $sender->setSocketId('socket1');
        $sender->setClientId('user1');

        $message1 = new Message();
        $message1->setEvent('event1');
        $message1->addRoom($room);
        $message1->setSender($sender);

        $message2 = new Message();
        $message2->setEvent('event2');
        $message2->addRoom($room);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->persist($sender);
        $entityManager->persist($message1);
        $entityManager->persist($message2);
        $entityManager->flush();

        $results = $this->repository->findBy(['sender' => $sender]);

        $this->assertIsArray($results);
        $this->assertCount(1, $results);

        $resultSender = $results[0]->getSender();
        $this->assertNotNull($resultSender);
        $this->assertSame($sender->getId(), $resultSender->getId());
    }

    public function testFindByWithNullableCriteria(): void
    {
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message1 = new Message();
        $message1->setEvent('event1');
        $message1->addRoom($room);
        $message1->setSender(null);

        $message2 = new Message();
        $message2->setEvent('event2');
        $message2->addRoom($room);
        $message2->setMetadata(['key' => 'value']);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->persist($message1);
        $entityManager->persist($message2);
        $entityManager->flush();

        $results = $this->repository->findBy(['sender' => null]);

        $this->assertIsArray($results);
        $this->assertGreaterThanOrEqual(1, count($results));

        foreach ($results as $message) {
            $this->assertNull($message->getSender());
        }
    }

    public function testCountWithNullableCriteria(): void
    {
        $room = new Room();
        $room->setName('room1');
        $room->setNamespace('/namespace1');
        $message1 = new Message();
        $message1->setEvent('event1');
        $message1->addRoom($room);
        $message1->setSender(null);

        $message2 = new Message();
        $message2->setEvent('event2');
        $message2->addRoom($room);
        $message2->setMetadata(['key' => 'value']);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);
        $entityManager->persist($message1);
        $entityManager->persist($message2);
        $entityManager->flush();

        $count = $this->repository->count(['sender' => null]);

        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testRepositoryInheritance(): void
    {
        $this->assertInstanceOf(MessageRepository::class, $this->repository);
    }

    protected function createNewEntity(): object
    {
        $room = new Room();
        $room->setName('test_room_' . uniqid());
        $room->setNamespace('/test');
        $entity = new Message();
        $entity->setEvent('test.event.' . uniqid());
        $entity->addRoom($room);

        $entityManager = self::getEntityManager();
        $entityManager->persist($room);

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<Message>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
