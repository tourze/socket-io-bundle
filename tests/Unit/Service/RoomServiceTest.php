<?php

namespace SocketIoBundle\Tests\Unit\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Repository\RoomRepository;
use SocketIoBundle\Service\RoomService;

class RoomServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private RoomRepository $roomRepository;
    private RoomService $roomService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->roomRepository = $this->createMock(RoomRepository::class);
        $this->roomService = new RoomService($this->entityManager, $this->roomRepository);
    }

    public function testFindOrCreateRoom_withExistingRoom_returnsExistingRoom(): void
    {
        // 准备测试数据
        $roomName = 'test-room';
        $namespace = '/test';
        $existingRoom = $this->createMock(Room::class);

        // 配置 mock 对象行为
        $existingRoom->method('getName')->willReturn($roomName);
        $existingRoom->method('getNamespace')->willReturn($namespace);

        // 设置模拟行为
        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($existingRoom);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->never())
            ->method('flush');

        // 执行测试
        $result = $this->roomService->findOrCreateRoom($roomName, $namespace);

        // 断言
        $this->assertSame($existingRoom, $result);
        $this->assertEquals($roomName, $result->getName());
        $this->assertEquals($namespace, $result->getNamespace());
    }

    public function testFindOrCreateRoom_withNonExistingRoom_createsAndReturnsNewRoom(): void
    {
        // 准备测试数据
        $roomName = 'new-room';
        $namespace = '/test';

        // 设置模拟行为
        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($room) use ($roomName, $namespace) {
                return $room instanceof Room
                    && $room->getName() === $roomName
                    && $room->getNamespace() === $namespace;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // 执行测试
        $result = $this->roomService->findOrCreateRoom($roomName, $namespace);

        // 断言
        $this->assertInstanceOf(Room::class, $result);
        $this->assertEquals($roomName, $result->getName());
        $this->assertEquals($namespace, $result->getNamespace());
    }

    public function testJoinRoom_withExistingRoom_addsSocketToRoom(): void
    {
        // 准备测试数据
        $roomName = 'test-room';
        $namespace = '/test';
        $existingRoom = $this->createMock(Room::class);
        $socket = $this->createMock(Socket::class);
        $sockets = $this->createMock(ArrayCollection::class);

        // 设置模拟行为
        $socket->expects($this->once())
            ->method('getNamespace')
            ->willReturn($namespace);

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($existingRoom);

        $existingRoom->expects($this->once())
            ->method('getSockets')
            ->willReturn($sockets);

        $sockets->expects($this->once())
            ->method('contains')
            ->with($socket)
            ->willReturn(false);

        $socket->expects($this->once())
            ->method('joinRoom')
            ->with($existingRoom);

        // 使用参数捕获代替 at 方法
        $persistedEntities = [];
        $this->entityManager->expects($this->exactly(2))
            ->method('persist')
            ->will($this->returnCallback(function ($entity) use (&$persistedEntities) {
                $persistedEntities[] = $entity;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // 执行测试
        $this->roomService->joinRoom($socket, $roomName);

        // 修改断言，不再调用 getNamespace
        $this->assertCount(2, $persistedEntities);
        $this->assertSame($existingRoom, $persistedEntities[0]);
        $this->assertSame($socket, $persistedEntities[1]);
    }

    public function testJoinRoom_socketAlreadyInRoom_doesNothing(): void
    {
        // 准备测试数据
        $roomName = 'test-room';
        $namespace = '/test';
        $existingRoom = $this->createMock(Room::class);
        $socket = $this->createMock(Socket::class);
        $sockets = $this->createMock(ArrayCollection::class);

        // 设置模拟行为
        $socket->expects($this->once())
            ->method('getNamespace')
            ->willReturn($namespace);

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($existingRoom);

        $existingRoom->expects($this->once())
            ->method('getSockets')
            ->willReturn($sockets);

        $sockets->expects($this->once())
            ->method('contains')
            ->with($socket)
            ->willReturn(true);

        $socket->expects($this->never())
            ->method('joinRoom');

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->never())
            ->method('flush');

        // 执行测试
        $this->roomService->joinRoom($socket, $roomName);
    }

    public function testJoinRoom_withNonExistingRoom_createsRoomAndAddsSocket(): void
    {
        // 准备测试数据
        $roomName = 'new-room';
        $namespace = '/test';
        $socket = $this->createMock(Socket::class);

        // 设置模拟行为
        $socket->expects($this->atLeastOnce())
            ->method('getNamespace')
            ->willReturn($namespace);

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn(null);

        $socket->expects($this->once())
            ->method('joinRoom')
            ->with($this->callback(function ($room) use ($roomName, $namespace) {
                return $room instanceof Room
                    && $room->getName() === $roomName
                    && $room->getNamespace() === $namespace;
            }));

        $this->entityManager->expects($this->exactly(2))
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        // 执行测试
        $this->roomService->joinRoom($socket, $roomName);
    }

    public function testLeaveRoom_socketInRoom_removesSocketFromRoom(): void
    {
        // 准备测试数据
        $roomName = 'test-room';
        $namespace = '/test';
        $room = $this->createMock(Room::class);
        $socket = $this->createMock(Socket::class);
        $sockets = $this->createMock(ArrayCollection::class);

        // 设置模拟行为
        $socket->expects($this->once())
            ->method('getNamespace')
            ->willReturn($namespace);

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($room);

        $room->expects($this->atLeastOnce())
            ->method('getSockets')
            ->willReturn($sockets);

        $sockets->expects($this->once())
            ->method('contains')
            ->with($socket)
            ->willReturn(true);

        $room->expects($this->once())
            ->method('removeSocket')
            ->with($socket);

        $sockets->expects($this->once())
            ->method('isEmpty')
            ->willReturn(true);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($room);

        $this->entityManager->expects($this->exactly(2))
            ->method('flush');

        // 执行测试
        $this->roomService->leaveRoom($socket, $roomName);
    }

    public function testLeaveRoom_roomDoesNotExist_doesNothing(): void
    {
        // 准备测试数据
        $roomName = 'non-existent-room';
        $namespace = '/test';
        $socket = $this->createMock(Socket::class);

        // 设置模拟行为
        $socket->expects($this->once())
            ->method('getNamespace')
            ->willReturn($namespace);

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn(null);

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->entityManager->expects($this->never())
            ->method('remove');

        // 执行测试
        $this->roomService->leaveRoom($socket, $roomName);
    }

    public function testLeaveRoom_socketNotInRoom_doesNothing(): void
    {
        // 准备测试数据
        $roomName = 'test-room';
        $namespace = '/test';
        $room = $this->createMock(Room::class);
        $socket = $this->createMock(Socket::class);
        $sockets = $this->createMock(ArrayCollection::class);

        // 设置模拟行为
        $socket->expects($this->once())
            ->method('getNamespace')
            ->willReturn($namespace);

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($room);

        $room->expects($this->once())
            ->method('getSockets')
            ->willReturn($sockets);

        $sockets->expects($this->once())
            ->method('contains')
            ->with($socket)
            ->willReturn(false);

        $room->expects($this->never())
            ->method('removeSocket');

        $this->entityManager->expects($this->never())
            ->method('flush');

        // 执行测试
        $this->roomService->leaveRoom($socket, $roomName);
    }

    public function testLeaveAllRooms_withRooms_removesSocketFromAllRooms(): void
    {
        // 准备测试数据
        $socket = $this->createMock(Socket::class);
        $room1 = $this->createMock(Room::class);
        $room2 = $this->createMock(Room::class);
        $sockets1 = $this->createMock(ArrayCollection::class);
        $sockets2 = $this->createMock(ArrayCollection::class);
        $rooms = [$room1, $room2];

        // 设置模拟行为
        $this->roomRepository->expects($this->once())
            ->method('findBySocket')
            ->with($socket)
            ->willReturn($rooms);

        $room1->expects($this->once())
            ->method('removeSocket')
            ->with($socket);

        $room2->expects($this->once())
            ->method('removeSocket')
            ->with($socket);

        $room1->expects($this->once())
            ->method('getSockets')
            ->willReturn($sockets1);

        $room2->expects($this->once())
            ->method('getSockets')
            ->willReturn($sockets2);

        $sockets1->expects($this->once())
            ->method('isEmpty')
            ->willReturn(false);

        $sockets2->expects($this->once())
            ->method('isEmpty')
            ->willReturn(true);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($room2);

        $this->entityManager->expects($this->once())
            ->method('flush');

        // 执行测试
        $this->roomService->leaveAllRooms($socket);
    }

    public function testGetRoomMembers_roomExists_returnsMembers(): void
    {
        // 准备测试数据
        $roomName = 'test-room';
        $namespace = '/test';
        $room = $this->createMock(Room::class);
        $socket1 = $this->createMock(Socket::class);
        $socket2 = $this->createMock(Socket::class);
        $sockets = $this->createMock(ArrayCollection::class);
        $socketArray = [$socket1, $socket2];

        // 设置模拟行为
        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($room);

        $room->expects($this->once())
            ->method('getSockets')
            ->willReturn($sockets);

        $sockets->expects($this->once())
            ->method('toArray')
            ->willReturn($socketArray);

        // 执行测试
        $result = $this->roomService->getRoomMembers($roomName, $namespace);

        // 断言
        $this->assertCount(2, $result);
        $this->assertSame($socket1, $result[0]);
        $this->assertSame($socket2, $result[1]);
    }

    public function testGetRoomMembers_roomDoesNotExist_returnsEmptyArray(): void
    {
        // 准备测试数据
        $roomName = 'non-existent-room';
        $namespace = '/test';

        // 设置模拟行为
        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn(null);

        // 执行测试
        $result = $this->roomService->getRoomMembers($roomName, $namespace);

        // 断言
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testSetRoomMetadata_updatesAndPersistsMetadata(): void
    {
        // 准备测试数据
        $room = $this->createMock(Room::class);
        $metadata = ['key' => 'value', 'another' => 'data'];

        // 设置模拟行为
        $room->expects($this->once())
            ->method('setMetadata')
            ->with($metadata);

        $this->entityManager->expects($this->once())
            ->method('flush');

        // 执行测试
        $this->roomService->setRoomMetadata($room, $metadata);
    }

    public function testGetSocketRooms_returnsRoomNames(): void
    {
        // 准备测试数据
        $socket = $this->createMock(Socket::class);
        $room1 = $this->createMock(Room::class);
        $room2 = $this->createMock(Room::class);
        $rooms = [$room1, $room2];

        // 设置模拟行为
        $this->roomRepository->expects($this->once())
            ->method('findBySocket')
            ->with($socket)
            ->willReturn($rooms);

        $room1->expects($this->once())
            ->method('getName')
            ->willReturn('room1');

        $room2->expects($this->once())
            ->method('getName')
            ->willReturn('room2');

        // 执行测试
        $result = $this->roomService->getSocketRooms($socket);

        // 断言
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals(['room1', 'room2'], $result);
    }
}
