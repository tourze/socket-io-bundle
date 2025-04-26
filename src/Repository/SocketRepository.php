<?php

namespace SocketIoBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineEnhanceBundle\Repository\CommonRepositoryAware;
use SocketIoBundle\Entity\Socket;

/**
 * @method Socket|null find($id, $lockMode = null, $lockVersion = null)
 * @method Socket|null findOneBy(array $criteria, array $orderBy = null)
 * @method Socket[]    findAll()
 * @method Socket[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SocketRepository extends ServiceEntityRepository
{
    use CommonRepositoryAware;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Socket::class);
    }

    public function findBySessionId(string $sessionId): ?Socket
    {
        return $this->findOneBy(['sessionId' => $sessionId]);
    }

    public function findByClientId(string $clientId): ?Socket
    {
        return $this->findOneBy(['clientId' => $clientId]);
    }

    /**
     * @return array<Socket>
     */
    public function findActiveConnections(): array
    {
        $result = $this->createQueryBuilder('s')
            ->where('s.connected = :connected')
            ->andWhere('s.lastActiveTime > :timeout')
            ->setParameter('connected', true)
            ->setParameter('timeout', new \DateTime('-30 seconds'))
            ->getQuery()
            ->getResult();

        foreach ($result as $socket) {
            $this->getEntityManager()->refresh($socket);
        }

        return $result;
    }

    public function cleanupInactiveConnections(): int
    {
        return $this->createQueryBuilder('s')
            ->delete()
            ->where('s.connected = :connected')
            ->orWhere('s.lastActiveTime < :timeout')
            ->orWhere('s.lastActiveTime IS NULL')
            ->setParameter('connected', false)
            ->setParameter('timeout', new \DateTime('-30 seconds'))
            ->getQuery()
            ->execute();
    }

    /**
     * 查找命名空间下的所有活跃连接
     *
     * @param string $namespace 命名空间
     *
     * @return Socket[] 连接数组
     */
    public function findActiveConnectionsByNamespace(string $namespace): array
    {
        $result = $this->createQueryBuilder('s')
            ->where('s.connected = :connected')
            ->andWhere('s.lastActiveTime > :timeout')
            ->andWhere('s.namespace = :namespace')
            ->setParameter('connected', true)
            ->setParameter('timeout', new \DateTime('-30 seconds'))
            ->setParameter('namespace', $namespace)
            ->getQuery()
            ->getResult();

        foreach ($result as $socket) {
            $this->getEntityManager()->refresh($socket);
        }

        return $result;
    }
}
