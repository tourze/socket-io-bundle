<?php

namespace SocketIoBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<Message>
 *
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: Message::class)]
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * @return array<int, Message>
     */
    public function findRoomMessages(Room $room, int $limit = 50, ?int $before = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->innerJoin('m.rooms', 'r')
            ->where('r = :room')
            ->setParameter('room', $room)
            ->orderBy('m.createTime', 'DESC')
            ->setMaxResults($limit)
        ;

        if (null !== $before) {
            $qb->andWhere('m.id < :before')
                ->setParameter('before', $before)
            ;
        }

        /** @var array<int, Message> */
        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, Message>
     */
    public function findUserMessages(string $userId, int $limit = 50, ?int $before = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->innerJoin('m.sender', 's')
            ->where('s.id = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('m.createTime', 'DESC')
            ->setMaxResults($limit)
        ;

        if (null !== $before) {
            $qb->andWhere('m.id < :before')
                ->setParameter('before', $before)
            ;
        }

        /** @var array<int, Message> */
        return $qb->getQuery()->getResult();
    }

    public function cleanupOldMessages(int $days = 30): int
    {
        $date = new \DateTime("-{$days} days");

        /** @var int */
        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.createTime < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute()
        ;
    }

    public function save(Message $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Message $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
