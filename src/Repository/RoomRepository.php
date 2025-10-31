<?php

namespace SocketIoBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<Room>
 *
 * @method Room|null find($id, $lockMode = null, $lockVersion = null)
 * @method Room|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method Room[]    findAll()
 * @method Room[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
#[AsRepository(entityClass: Room::class)]
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    public function findByName(string $name): ?Room
    {
        /** @var Room|null */
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * @return array<int, Room>
     */
    public function findByClientId(string $clientId): array
    {
        /** @var array<int, Room> */
        return $this->createQueryBuilder('r')
            ->innerJoin('r.sockets', 's')
            ->where('s.clientId = :clientId')
            ->setParameter('clientId', $clientId)
            ->getQuery()
            ->getResult()
        ;
    }

    public function removeClientFromAllRooms(string $clientId): void
    {
        $rooms = $this->findByClientId($clientId);
        $entityManager = $this->getEntityManager();

        foreach ($rooms as $room) {
            $socketsToRemove = [];
            foreach ($room->getSockets() as $socket) {
                if ($socket->getClientId() === $clientId) {
                    $socketsToRemove[] = $socket;
                }
            }

            foreach ($socketsToRemove as $socket) {
                $socket->leaveRoom($room);
                $entityManager->persist($socket);
            }
            $entityManager->persist($room);
        }
        $entityManager->flush();
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
        /** @var Room|null */
        return $this->findOneBy(['name' => $name, 'namespace' => $namespace]);
    }

    /**
     * 获取符合条件的多个房间
     * @param array<string> $names
     * @return array<Room>
     */
    public function findByNamesAndNamespace(array $names, string $namespace = '/'): array
    {
        /** @var array<int, Room> */
        return $this->findBy(['name' => $names, 'namespace' => $namespace]);
    }

    /**
     * 查找命名空间下的所有房间
     *
     * @param string $namespace 命名空间
     *
     * @return array<int, Room> 房间数组
     */
    public function findByNamespace(string $namespace): array
    {
        /** @var array<int, Room> */
        return $this->findBy(['namespace' => $namespace]);
    }

    /**
     * 查找 Socket 加入的所有房间
     *
     * @param Socket $socket 连接实体
     *
     * @return array<int, Room> 房间数组
     */
    public function findBySocket(Socket $socket): array
    {
        /** @var array<int, Room> */
        return $this->createQueryBuilder('r')
            ->innerJoin('r.sockets', 's')
            ->where('s.id = :socketId')
            ->setParameter('socketId', $socket->getId())
            ->getQuery()
            ->getResult()
        ;
    }

    public function save(Room $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Room $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
