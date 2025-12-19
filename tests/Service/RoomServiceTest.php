<?php

namespace SocketIoBundle\Tests\Service;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Service\RoomService;
use SocketIoBundle\SocketIoBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Tourze\DoctrineSnowflakeBundle\DoctrineSnowflakeBundle;
use Tourze\DoctrineTimestampBundle\DoctrineTimestampBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RoomService::class)]
#[RunTestsInSeparateProcesses]
final class RoomServiceTest extends AbstractIntegrationTestCase
{
    private RoomService $roomService;

    /**
     * @return array<string, array<string, bool>>
     */
    public static function configureBundles(): array
    {
        return [
            FrameworkBundle::class => ['all' => true],
            DoctrineBundle::class => ['all' => true],
            DoctrineSnowflakeBundle::class => ['all' => true],
            DoctrineTimestampBundle::class => ['all' => true],
            SocketIoBundle::class => ['all' => true],
        ];
    }

    protected function onSetUp(): void
    {
        $this->roomService = self::getService(RoomService::class);
    }

    public function testFindOrCreateRoomReturnsExistingRoom(): void
    {
        $roomName = 'test-room';
        $namespace = '/chat';

        $existingRoom = new Room();
        $existingRoom->setName($roomName);
        $existingRoom->setNamespace($namespace);
        $this->persistAndFlush($existingRoom);

        $result = $this->roomService->findOrCreateRoom($roomName, $namespace);

        $this->assertEquals($roomName, $result->getName());
        $this->assertEquals($namespace, $result->getNamespace());
    }

    public function testFindOrCreateRoomCreatesNewRoom(): void
    {
        $roomName = 'test-room';
        $namespace = '/chat';

        $result = $this->roomService->findOrCreateRoom($roomName, $namespace);

        $this->assertInstanceOf(Room::class, $result);
        $this->assertEquals($roomName, $result->getName());
        $this->assertEquals($namespace, $result->getNamespace());
    }

    public function testJoinRoomCreatesNewRoom(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/chat');
        $this->persistAndFlush($socket);

        $roomName = 'test-room';

        $this->roomService->joinRoom($socket, $roomName);

        $em = self::getEntityManager();
        $em->refresh($socket);

        $rooms = $socket->getRooms();
        $this->assertGreaterThanOrEqual(1, $rooms->count());

        $roomNames = array_map(fn ($room) => $room->getName(), $rooms->toArray());
        $this->assertContains($roomName, $roomNames);
    }

    public function testJoinRoomWithExistingRoom(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/chat');
        $this->persistAndFlush($socket);

        $roomName = 'test-room';
        $namespace = '/chat';

        $existingRoom = new Room();
        $existingRoom->setName($roomName);
        $existingRoom->setNamespace($namespace);
        $this->persistAndFlush($existingRoom);

        $this->roomService->joinRoom($socket, $roomName);

        $em = self::getEntityManager();
        $em->refresh($socket);
        $em->refresh($existingRoom);

        $this->assertTrue($existingRoom->getSockets()->contains($socket));
    }

    public function testJoinRoomDoesNothingWhenAlreadyInRoom(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/chat');

        $roomName = 'test-room';
        $namespace = '/chat';

        $existingRoom = new Room();
        $existingRoom->setName($roomName);
        $existingRoom->setNamespace($namespace);

        // 先分别持久化各实体，再建立关系
        $this->persistAndFlush($existingRoom);
        $this->persistAndFlush($socket);

        $socket->joinRoom($existingRoom);
        $em = self::getEntityManager();
        $em->flush();
        $em->refresh($existingRoom);

        $initialCount = $existingRoom->getSockets()->count();

        $this->roomService->joinRoom($socket, $roomName);

        $em->refresh($existingRoom);
        $this->assertEquals($initialCount, $existingRoom->getSockets()->count());
    }

    public function testLeaveRoomRemovesSocket(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/chat');

        $socket2 = new Socket();
        $socket2->setSessionId('test-session-2');
        $socket2->setSocketId('test-socket-2');
        $socket2->setNamespace('/chat');

        $roomName = 'test-room';

        // 先分别持久化 socket 实体
        $this->persistAndFlush($socket);
        $this->persistAndFlush($socket2);

        // 使用 service 的 joinRoom 方法（这会自动创建或找到 room 并关联）
        $this->roomService->joinRoom($socket, $roomName);
        $this->roomService->joinRoom($socket2, $roomName);

        // 验证房间有两个 socket
        $em = self::getEntityManager();
        $room = $em->getRepository(Room::class)->findOneBy(['name' => $roomName, 'namespace' => '/chat']);
        $this->assertNotNull($room);
        $em->refresh($room);
        $this->assertCount(2, $room->getSockets());

        // 调用 leaveRoom - 需要先 refresh socket 确保 namespace 被正确加载
        $em->refresh($socket);
        $this->roomService->leaveRoom($socket, $roomName);

        // 清除实体管理器缓存，确保从数据库重新加载
        $em->clear();

        $refreshedRoom = $em->find(Room::class, $room->getId());
        $this->assertNotNull($refreshedRoom);
        $this->assertCount(1, $refreshedRoom->getSockets());
    }

    public function testLeaveRoomDeletesEmptyRoom(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/chat');

        $roomName = 'test-room';
        $namespace = '/chat';

        $room = new Room();
        $room->setName($roomName);
        $room->setNamespace($namespace);

        // 先分别持久化各实体，再建立关系
        $this->persistAndFlush($room);
        $this->persistAndFlush($socket);

        $socket->joinRoom($room);
        $em = self::getEntityManager();
        $em->flush();

        $roomId = $room->getId();

        $this->roomService->leaveRoom($socket, $roomName);

        $em = self::getEntityManager();
        $foundRoom = $em->find(Room::class, $roomId);

        // 房间应该被删除
        $this->assertNull($foundRoom);
    }

    public function testLeaveRoomDoesNothingWhenRoomNotFound(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/chat');
        $this->persistAndFlush($socket);

        $roomName = 'test-room';

        // 不应该抛出异常
        $this->roomService->leaveRoom($socket, $roomName);

        $this->expectNotToPerformAssertions();
    }

    public function testLeaveAllRooms(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/chat');

        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/chat');

        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/chat');

        // 先分别持久化各实体，再建立关系
        $this->persistAndFlush($room1);
        $this->persistAndFlush($room2);
        $this->persistAndFlush($socket);

        $socket->joinRoom($room1);
        $socket->joinRoom($room2);
        $em = self::getEntityManager();
        $em->flush();

        $this->roomService->leaveAllRooms($socket);

        $em = self::getEntityManager();
        $em->refresh($socket);

        $this->assertEquals(0, $socket->getRooms()->count());
    }

    public function testGetRoomMembersReturnsEmptyArrayWhenRoomNotFound(): void
    {
        $roomName = 'test-room';
        $namespace = '/chat';

        $result = $this->roomService->getRoomMembers($roomName, $namespace);

        $this->assertEquals([], $result);
    }

    public function testGetRoomMembersReturnsSockets(): void
    {
        $roomName = 'test-room';
        $namespace = '/chat';

        $room = new Room();
        $room->setName($roomName);
        $room->setNamespace($namespace);

        $socket1 = new Socket();
        $socket1->setSessionId('session1');
        $socket1->setSocketId('socket1');
        $socket1->setNamespace($namespace);

        $socket2 = new Socket();
        $socket2->setSessionId('session2');
        $socket2->setSocketId('socket2');
        $socket2->setNamespace($namespace);

        // 先分别持久化各实体，再建立关系
        $this->persistAndFlush($room);
        $this->persistAndFlush($socket1);
        $this->persistAndFlush($socket2);

        $socket1->joinRoom($room);
        $socket2->joinRoom($room);
        $em = self::getEntityManager();
        $em->flush();

        $result = $this->roomService->getRoomMembers($roomName, $namespace);

        $this->assertCount(2, $result);
    }

    public function testSetRoomMetadata(): void
    {
        $room = new Room();
        $room->setName('test-room');
        $room->setNamespace('/');
        $this->persistAndFlush($room);

        $metadata = ['key' => 'value'];

        $this->roomService->setRoomMetadata($room, $metadata);

        $em = self::getEntityManager();
        $em->refresh($room);

        $this->assertEquals($metadata, $room->getMetadata());
    }

    public function testGetSocketRoomsReturnsRoomNames(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/');

        $room1 = new Room();
        $room1->setName('room1');
        $room1->setNamespace('/');

        $room2 = new Room();
        $room2->setName('room2');
        $room2->setNamespace('/');

        // 先分别持久化各实体，再建立关系
        $this->persistAndFlush($room1);
        $this->persistAndFlush($room2);
        $this->persistAndFlush($socket);

        $socket->joinRoom($room1);
        $socket->joinRoom($room2);
        $em = self::getEntityManager();
        $em->flush();

        $result = $this->roomService->getSocketRooms($socket);

        $this->assertCount(2, $result);
        $this->assertContains('room1', $result);
        $this->assertContains('room2', $result);
    }

    public function testGetSocketRoomsReturnsEmptyArrayWhenNoRooms(): void
    {
        $socket = new Socket();
        $socket->setSessionId('test-session');
        $socket->setSocketId('test-socket');
        $socket->setNamespace('/');
        $this->persistAndFlush($socket);

        $result = $this->roomService->getSocketRooms($socket);

        $this->assertEquals([], $result);
    }
}
