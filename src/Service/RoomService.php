<?php

namespace SocketIoBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Repository\RoomRepository;

/**
 * 房间管理服务
 *
 * 负责管理 Socket.io 的房间功能，包括：
 * - 房间的创建和查找
 * - 成员的加入和离开
 * - 房间元数据管理
 * - 房间成员查询
 */
class RoomService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RoomRepository $roomRepository,
    ) {
    }

    /**
     * 查找或创建房间
     *
     * @param string $name      房间名称
     * @param string $namespace 命名空间
     *
     * @return Room 找到或新创建的房间实体
     */
    public function findOrCreateRoom(string $name, string $namespace = '/'): Room
    {
        $room = $this->roomRepository->findByNameAndNamespace($name, $namespace);
        if (null === $room) {
            $room = new Room();
            $room->setName($name);
            $room->setNamespace($namespace);
            $this->em->persist($room);
            $this->em->flush();
        }

        return $room;
    }

    /**
     * 将连接加入房间
     *
     * @param Socket $socket   连接实体
     * @param string $roomName 房间名称
     */
    public function joinRoom(Socket $socket, string $roomName): void
    {
        $room = $this->roomRepository->findByNameAndNamespace($roomName, $socket->getNamespace());
        if (null === $room) {
            $room = new Room();
            $room->setName($roomName);
            $room->setNamespace($socket->getNamespace());
        }

        if (!$room->getSockets()->contains($socket)) {
            $socket->joinRoom($room);
            $this->em->persist($room);
            $this->em->persist($socket);
            $this->em->flush();
        }
    }

    /**
     * 将连接从房间移除
     *
     * @param Socket $socket   连接实体
     * @param string $roomName 房间名称
     */
    public function leaveRoom(Socket $socket, string $roomName): void
    {
        $room = $this->roomRepository->findByNameAndNamespace($roomName, $socket->getNamespace());
        if (null !== $room && $room->getSockets()->contains($socket)) {
            $room->removeSocket($socket);
            $this->em->flush();

            // 如果房间为空，删除房间
            if ($room->getSockets()->isEmpty()) {
                $this->em->remove($room);
                $this->em->flush();
            }
        }
    }

    /**
     * 将连接从所有房间移除
     *
     * @param Socket $socket 连接实体
     */
    public function leaveAllRooms(Socket $socket): void
    {
        $rooms = $this->roomRepository->findBySocket($socket);
        foreach ($rooms as $room) {
            $room->removeSocket($socket);
            if ($room->getSockets()->isEmpty()) {
                $this->em->remove($room);
            }
        }
        $this->em->flush();
    }

    /**
     * 获取房间的所有成员
     *
     * @param string $roomName  房间名称
     * @param string $namespace 命名空间
     *
     * @return Socket[] 房间成员数组
     */
    public function getRoomMembers(string $roomName, string $namespace = '/'): array
    {
        $room = $this->roomRepository->findByNameAndNamespace($roomName, $namespace);

        return null !== $room ? $room->getSockets()->toArray() : [];
    }

    /**
     * 设置房间元数据
     *
     * @param Room                $room     房间实体
     * @param array<string,mixed> $metadata 元数据
     */
    public function setRoomMetadata(Room $room, array $metadata): void
    {
        $room->setMetadata($metadata);
        $this->em->flush();
    }

    /**
     * 获取 Socket 加入的所有房间名称
     *
     * @param Socket $socket 连接实体
     *
     * @return string[] 房间名称数组
     */
    public function getSocketRooms(Socket $socket): array
    {
        $rooms = $this->roomRepository->findBySocket($socket);

        return array_values(array_filter(
            array_map(fn (Room $room) => $room->getName(), $rooms),
            fn (?string $name) => null !== $name
        ));
    }
}
