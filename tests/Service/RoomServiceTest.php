<?php

namespace SocketIoBundle\Tests\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\ReflectionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Repository\RoomRepository;
use SocketIoBundle\Service\RoomService;

/**
 * @internal
 */
#[CoversClass(RoomService::class)]
final class RoomServiceTest extends TestCase
{
    /** @var MockObject&EntityManagerInterface */
    private EntityManagerInterface $em;

    /** @var MockObject&RoomRepository */
    private RoomRepository $roomRepository;

    private RoomService $roomService;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建EntityManager的Mock对象
        $this->em = $this->createMock(EntityManagerInterface::class);

        // 配置getRepository方法返回一个简单的mock repository
        $this->em->method('getRepository')->willReturn(
            $this->createMock(EntityRepository::class)
        );

        $this->roomRepository = $this->createMock(RoomRepository::class);

        $this->roomService = new RoomService(
            $this->em,
            $this->roomRepository
        );
    }

    public function testFindOrCreateRoomReturnsExistingRoom(): void
    {
        $roomName = 'test-room';
        $namespace = '/chat';
        /** @var Room&MockObject $existingRoom */
        $existingRoom = new Room();

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($existingRoom)
        ;

        $result = $this->roomService->findOrCreateRoom($roomName, $namespace);

        $this->assertSame($existingRoom, $result);
    }

    public function testFindOrCreateRoomCreatesNewRoom(): void
    {
        $roomName = 'test-room';
        $namespace = '/chat';

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn(null)
        ;

        $this->em->expects($this->once())
            ->method('persist')
            ->with(self::callback(function ($room) use ($roomName, $namespace) {
                return $room instanceof Room
                    && $room->getName() === $roomName
                    && $room->getNamespace() === $namespace;
            }))
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $result = $this->roomService->findOrCreateRoom($roomName, $namespace);

        $this->assertInstanceOf(Room::class, $result);
        $this->assertEquals($roomName, $result->getName());
        $this->assertEquals($namespace, $result->getNamespace());
    }

    public function testJoinRoomCreatesNewRoom(): void
    {
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $roomName = 'test-room';
        $namespace = '/chat';

        $socket->method('getNamespace')->willReturn($namespace);

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn(null)
        ;

        $socket->expects($this->once())
            ->method('joinRoom')
            ->with(self::callback(function ($room) use ($roomName, $namespace) {
                return $room instanceof Room
                    && $room->getName() === $roomName
                    && $room->getNamespace() === $namespace;
            }))
        ;

        $this->em->expects($this->exactly(2))
            ->method('persist')
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->roomService->joinRoom($socket, $roomName);
    }

    public function testJoinRoomWithExistingRoom(): void
    {
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $roomName = 'test-room';
        $namespace = '/chat';
        /** @var Room&MockObject $existingRoom */
        $existingRoom = $this->createMock(Room::class);

        $socket->method('getNamespace')->willReturn($namespace);
        $existingRoom->method('getSockets')->willReturn(new ArrayCollection([]));

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($existingRoom)
        ;

        $socket->expects($this->once())
            ->method('joinRoom')
            ->with($existingRoom)
        ;

        $this->em->expects($this->exactly(2))
            ->method('persist')
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->roomService->joinRoom($socket, $roomName);
    }

    public function testJoinRoomDoesNothingWhenAlreadyInRoom(): void
    {
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $roomName = 'test-room';
        $namespace = '/chat';
        /** @var Room&MockObject $existingRoom */
        $existingRoom = $this->createMock(Room::class);

        $socket->method('getNamespace')->willReturn($namespace);
        $existingRoom->method('getSockets')->willReturn(new ArrayCollection([$socket]));

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($existingRoom)
        ;

        $socket->expects($this->never())
            ->method('joinRoom')
        ;

        $this->em->expects($this->never())
            ->method('persist')
        ;

        $this->em->expects($this->never())
            ->method('flush')
        ;

        $this->roomService->joinRoom($socket, $roomName);
    }

    public function testLeaveRoomRemovesSocket(): void
    {
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $roomName = 'test-room';
        $namespace = '/chat';
        /** @var Room&MockObject $room */
        $room = $this->createMock(Room::class);

        $socket->method('getNamespace')->willReturn($namespace);
        $room->method('getSockets')->willReturn(new ArrayCollection([$socket]));

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($room)
        ;

        $room->expects($this->once())
            ->method('removeSocket')
            ->with($socket)
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->roomService->leaveRoom($socket, $roomName);
    }

    public function testLeaveRoomDeletesEmptyRoom(): void
    {
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $roomName = 'test-room';
        $namespace = '/chat';
        /** @var Room&MockObject $room */
        $room = $this->createMock(Room::class);

        $socket->method('getNamespace')->willReturn($namespace);
        $room->method('getSockets')
            ->willReturnOnConsecutiveCalls(
                new ArrayCollection([$socket]),
                new ArrayCollection([])
            )
        ;

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($room)
        ;

        $room->expects($this->once())
            ->method('removeSocket')
            ->with($socket)
        ;

        $this->em->expects($this->once())
            ->method('remove')
            ->with($room)
        ;

        $this->em->expects($this->exactly(2))
            ->method('flush')
        ;

        $this->roomService->leaveRoom($socket, $roomName);
    }

    public function testLeaveRoomDoesNothingWhenRoomNotFound(): void
    {
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        $roomName = 'test-room';
        $namespace = '/chat';

        $socket->method('getNamespace')->willReturn($namespace);

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn(null)
        ;

        $this->em->expects($this->never())
            ->method('flush')
        ;

        $this->roomService->leaveRoom($socket, $roomName);
    }

    public function testLeaveAllRooms(): void
    {
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        /** @var MockObject&Room $room1 */
        $room1 = $this->createMock(Room::class);
        /** @var MockObject&Room $room2 */
        $room2 = $this->createMock(Room::class);

        $this->roomRepository->expects($this->once())
            ->method('findBySocket')
            ->with($socket)
            ->willReturn([$room1, $room2])
        ;

        $room1->expects($this->once())
            ->method('removeSocket')
            ->with($socket)
        ;

        $room2->expects($this->once())
            ->method('removeSocket')
            ->with($socket)
        ;

        $room1->method('getSockets')->willReturn(new ArrayCollection([]));
        $room2->method('getSockets')->willReturn(new ArrayCollection([$socket]));

        $this->em->expects($this->once())
            ->method('remove')
            ->with($room1)
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->roomService->leaveAllRooms($socket);
    }

    public function testGetRoomMembersReturnsEmptyArrayWhenRoomNotFound(): void
    {
        $roomName = 'test-room';
        $namespace = '/chat';

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn(null)
        ;

        $result = $this->roomService->getRoomMembers($roomName, $namespace);

        $this->assertEquals([], $result);
    }

    public function testGetRoomMembersReturnsSockets(): void
    {
        $roomName = 'test-room';
        $namespace = '/chat';
        /** @var Room&MockObject $room */
        $room = $this->createMock(Room::class);
        /** @var MockObject&Socket $socket1 */
        $socket1 = $this->createMock(Socket::class);
        /** @var MockObject&Socket $socket2 */
        $socket2 = $this->createMock(Socket::class);

        $this->roomRepository->expects($this->once())
            ->method('findByNameAndNamespace')
            ->with($roomName, $namespace)
            ->willReturn($room)
        ;

        $room->method('getSockets')->willReturn(new ArrayCollection([$socket1, $socket2]));

        $result = $this->roomService->getRoomMembers($roomName, $namespace);

        $this->assertEquals([$socket1, $socket2], $result);
    }

    public function testSetRoomMetadata(): void
    {
        /** @var Room&MockObject $room */
        $room = $this->createMock(Room::class);
        $metadata = ['key' => 'value'];

        $room->expects($this->once())
            ->method('setMetadata')
            ->with($metadata)
        ;

        $this->em->expects($this->once())
            ->method('flush')
        ;

        $this->roomService->setRoomMetadata($room, $metadata);
    }

    public function testGetSocketRoomsReturnsRoomNames(): void
    {
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);
        /** @var MockObject&Room $room1 */
        $room1 = $this->createMock(Room::class);
        /** @var MockObject&Room $room2 */
        $room2 = $this->createMock(Room::class);

        $room1->method('getName')->willReturn('room1');
        $room2->method('getName')->willReturn('room2');

        $this->roomRepository->expects($this->once())
            ->method('findBySocket')
            ->with($socket)
            ->willReturn([$room1, $room2])
        ;

        $result = $this->roomService->getSocketRooms($socket);

        $this->assertEquals(['room1', 'room2'], $result);
    }

    public function testGetSocketRoomsReturnsEmptyArrayWhenNoRooms(): void
    {
        /** @var Socket&MockObject $socket */
        $socket = $this->createMock(Socket::class);

        $this->roomRepository->expects($this->once())
            ->method('findBySocket')
            ->with($socket)
            ->willReturn([])
        ;

        $result = $this->roomService->getSocketRooms($socket);

        $this->assertEquals([], $result);
    }
}
