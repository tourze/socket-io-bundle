<?php

namespace SocketIoBundle\EventSubscriber;

use SocketIoBundle\Event\SocketEvent;
use SocketIoBundle\Service\MessageService;
use SocketIoBundle\Service\RoomService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RoomEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RoomService $roomService,
        private readonly MessageService $messageService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SocketEvent::class => 'onSocketEvent',
        ];
    }

    public function onSocketEvent(SocketEvent $event): void
    {
        $socket = $event->getSocket();
        if (null === $socket) {
            return;
        }

        match ($event->getName()) {
            'joinRoom' => $this->handleJoinRoom($event),
            'leaveRoom' => $this->handleLeaveRoom($event),
            'getRooms' => $this->handleGetRooms($event),
            default => null,
        };
    }

    private function handleJoinRoom(SocketEvent $event): void
    {
        $socket = $event->getSocket();
        if (null === $socket) {
            return;
        }

        $data = $event->getData();
        if (!isset($data[0]) || !is_string($data[0])) {
            return;
        }

        $roomName = $data[0];
        $this->roomService->joinRoom($socket, $roomName);

        // 发送更新后的房间列表
        $rooms = $this->roomService->getSocketRooms($socket);
        $this->messageService->sendToSocket($socket, 'roomList', ['rooms' => $rooms]);
    }

    private function handleLeaveRoom(SocketEvent $event): void
    {
        $socket = $event->getSocket();
        if (null === $socket) {
            return;
        }

        $data = $event->getData();
        if (!isset($data[0]) || !is_array($data[0]) || !isset($data[0]['room']) || !is_string($data[0]['room'])) {
            return;
        }

        $roomName = $data[0]['room'];
        $this->roomService->leaveRoom($socket, $roomName);

        // 发送更新后的房间列表
        $rooms = $this->roomService->getSocketRooms($socket);
        $this->messageService->sendToSocket($socket, 'roomList', ['rooms' => $rooms]);
    }

    private function handleGetRooms(SocketEvent $event): void
    {
        $socket = $event->getSocket();
        if (null === $socket) {
            return;
        }

        // 发送当前房间列表
        $rooms = $this->roomService->getSocketRooms($socket);
        $this->messageService->sendToSocket($socket, 'roomList', ['rooms' => $rooms]);
    }
}
