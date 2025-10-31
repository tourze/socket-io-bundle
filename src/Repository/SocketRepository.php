<?php

namespace SocketIoBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SocketIoBundle\Entity\Socket;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<Socket>
 *
 * @method Socket|null find($id, $lockMode = null, $lockVersion = null)
 * @method Socket|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Socket[]    findAll()
 * @method Socket[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: Socket::class)]
class SocketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Socket::class);
    }

    public function findBySessionId(string $sessionId): ?Socket
    {
        /** @var Socket|null */
        return $this->findOneBy(['sessionId' => $sessionId]);
    }

    public function findByClientId(string $clientId): ?Socket
    {
        /** @var Socket|null */
        return $this->findOneBy(['clientId' => $clientId]);
    }

    /**
     * @return array<int, Socket>
     */
    public function findActiveConnections(): array
    {
        /** @var array<int, Socket> $result */
        $result = $this->createQueryBuilder('s')
            ->where('s.connected = :connected')
            ->andWhere('s.lastActiveTime > :timeout')
            ->setParameter('connected', true)
            ->setParameter('timeout', new \DateTimeImmutable('-30 seconds'))
            ->getQuery()
            ->getResult()
        ;

        foreach ($result as $socket) {
            if ($socket instanceof Socket) {
                $this->getEntityManager()->refresh($socket);
            }
        }

        return $result;
    }

    public function cleanupInactiveConnections(): int
    {
        $result = $this->createQueryBuilder('s')
            ->delete()
            ->where('s.connected = :connected')
            ->orWhere('s.lastActiveTime < :timeout')
            ->orWhere('s.lastActiveTime IS NULL')
            ->setParameter('connected', false)
            ->setParameter('timeout', new \DateTimeImmutable('-30 seconds'))
            ->getQuery()
            ->execute()
        ;

        return is_int($result) ? $result : 0;
    }

    /**
     * 查找命名空间下的所有活跃连接
     *
     * @param string $namespace 命名空间
     *
     * @return array<int, Socket> 连接数组
     */
    public function findActiveConnectionsByNamespace(string $namespace): array
    {
        /** @var array<int, Socket> $result */
        $result = $this->createQueryBuilder('s')
            ->where('s.connected = :connected')
            ->andWhere('s.lastActiveTime > :timeout')
            ->andWhere('s.namespace = :namespace')
            ->setParameter('connected', true)
            ->setParameter('timeout', new \DateTimeImmutable('-30 seconds'))
            ->setParameter('namespace', $namespace)
            ->getQuery()
            ->getResult()
        ;

        foreach ($result as $socket) {
            if ($socket instanceof Socket) {
                $this->getEntityManager()->refresh($socket);
            }
        }

        return $result;
    }

    public function save(Socket $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Socket $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
