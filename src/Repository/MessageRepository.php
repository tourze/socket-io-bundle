<?php

namespace SocketIoBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineEnhanceBundle\Repository\CommonRepositoryAware;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;

/**
 * @method Message|null find($id, $lockMode = null, $lockVersion = null)
 * @method Message|null findOneBy(array $criteria, array $orderBy = null)
 * @method Message[]    findAll()
 * @method Message[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MessageRepository extends ServiceEntityRepository
{
    use CommonRepositoryAware;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function findRoomMessages(Room $room, int $limit = 50, ?int $before = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.room = :room')
            ->setParameter('room', $room)
            ->orderBy('m.createTime', 'DESC')
            ->setMaxResults($limit);

        if (null !== $before) {
            $qb->andWhere('m.id < :before')
                ->setParameter('before', $before);
        }

        return $qb->getQuery()->getResult();
    }

    public function findUserMessages(string $userId, int $limit = 50, ?int $before = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->where('m.senderId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('m.createTime', 'DESC')
            ->setMaxResults($limit);

        if (null !== $before) {
            $qb->andWhere('m.id < :before')
                ->setParameter('before', $before);
        }

        return $qb->getQuery()->getResult();
    }

    public function cleanupOldMessages(int $days = 30): int
    {
        $date = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('m')
            ->delete()
            ->where('m.createTime < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
