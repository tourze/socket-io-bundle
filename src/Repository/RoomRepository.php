<?php

namespace SocketIoBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use DoctrineEnhanceBundle\Repository\CommonRepositoryAware;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;

/**
 * @method Room|null find($id, $lockMode = null, $lockVersion = null)
 * @method Room|null findOneBy(array $criteria, array $orderBy = null)
 * @method Room[]    findAll()
 * @method Room[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RoomRepository extends ServiceEntityRepository
{
    use CommonRepositoryAware;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    public function findByName(string $name): ?Room
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function findByClientId(string $clientId): array
    {
        $qb = $this->createQueryBuilder('r');

        return $qb
            ->where($qb->expr()->like('r.clients', ':clientId'))
            ->setParameter('clientId', '%' . $clientId . '%')
            ->getQuery()
            ->getResult();
    }

    public function removeClientFromAllRooms(string $clientId): void
    {
        $rooms = $this->findByClientId($clientId);
        foreach ($rooms as $room) {
            $room->removeClient($clientId);
            $this->_em->persist($room);
        }
        $this->_em->flush();
    }

    /**
     * 根据名称和命名空间查找房间
     *
     * @param string $name      房间名称
     * @param string $namespace 命名空间
     *
     * @return Room|null 找到的房间实体或null
     */
    public function findByNameAndNamespace(string $name, string $namespace = '/'): ?Room
    {
        return $this->findOneBy(['name' => $name, 'namespace' => $namespace]);
    }

    /**
     * 获取符合条件的多个房间
     */
    public function findByNamesAndNamespace(array $names, string $namespace = '/'): array
    {
        return $this->findBy(['name' => $names, 'namespace' => $namespace]);
    }

    /**
     * 查找命名空间下的所有房间
     *
     * @param string $namespace 命名空间
     *
     * @return Room[] 房间数组
     */
    public function findByNamespace(string $namespace): array
    {
        return $this->findBy(['namespace' => $namespace]);
    }

    /**
     * 查找 Socket 加入的所有房间
     *
     * @param Socket $socket 连接实体
     *
     * @return Room[] 房间数组
     */
    public function findBySocket(Socket $socket): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.sockets', 's')
            ->where('s.id = :socketId')
            ->setParameter('socketId', $socket->getId())
            ->getQuery()
            ->getResult();
    }
}
