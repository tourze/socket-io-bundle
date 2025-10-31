<?php

namespace SocketIoBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<Delivery>
 *
 * @method Delivery|null find($id, $lockMode = null, $lockVersion = null)
 * @method Delivery|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Delivery[]    findAll()
 * @method Delivery[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: Delivery::class)]
class DeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Delivery::class);
    }

    /**
     * @return array<int, Delivery>
     */
    public function findPendingDeliveries(Socket $socket): array
    {
        /** @var array<int, Delivery> */
        return $this->createQueryBuilder('d')
            ->where('d.socket = :socket')
            ->andWhere('d.status = :status')
            ->setParameter('socket', $socket)
            ->setParameter('status', MessageStatus::PENDING)
            ->orderBy('d.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @return array<int, Delivery>
     */
    public function findMessageDeliveries(Message $message): array
    {
        /** @var array<int, Delivery> */
        return $this->createQueryBuilder('d')
            ->where('d.message = :message')
            ->setParameter('message', $message)
            ->orderBy('d.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function cleanupOldDeliveries(int $days = 7): int
    {
        /** @var int */
        return $this->createQueryBuilder('d')
            ->delete()
            ->where('d.createTime < :date')
            ->setParameter('date', new \DateTime("-{$days} days"))
            ->getQuery()
            ->execute()
        ;
    }

    public function save(Delivery $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Delivery $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
