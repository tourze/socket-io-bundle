<?php

namespace SocketIoBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;

/**
 * @method Delivery|null find($id, $lockMode = null, $lockVersion = null)
 * @method Delivery|null findOneBy(array $criteria, array $orderBy = null)
 * @method Delivery[]    findAll()
 * @method Delivery[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DeliveryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Delivery::class);
    }

    public function findPendingDeliveries(Socket $socket): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.socket = :socket')
            ->andWhere('d.status = :status')
            ->setParameter('socket', $socket)
            ->setParameter('status', MessageStatus::PENDING)
            ->orderBy('d.createTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findMessageDeliveries(Message $message): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.message = :message')
            ->setParameter('message', $message)
            ->orderBy('d.createTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function cleanupOldDeliveries(int $days = 7): int
    {
        return $this->createQueryBuilder('d')
            ->delete()
            ->where('d.createTime < :date')
            ->setParameter('date', new \DateTime("-{$days} days"))
            ->getQuery()
            ->execute();
    }
}
