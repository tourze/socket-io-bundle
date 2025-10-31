# Socket.IO Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![PHP Version](https://img.shields.io/packagist/php-v/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)
[![Latest Version](https://img.shields.io/packagist/v/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)
[![License](https://img.shields.io/packagist/l/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)
[![Build Status](https://img.shields.io/travis/tourze/socket-io-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/socket-io-bundle)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/tourze/socket-io-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/socket-io-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)

A Symfony bundle providing a full-featured Socket.IO server implementation 
for real-time, bidirectional communication. Supports room management, 
message delivery, namespaces, and persistent storage.

## Table of Contents

- [Features](#features)
- [Installation](#installation)
- [Configuration](#configuration)
- [Dependencies](#dependencies)
- [Quick Start](#quick-start)
- [Usage](#usage)
- [Advanced Usage](#advanced-usage)
- [Commands](#commands)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)
- [Changelog](#changelog)

## Features

- Full Socket.IO server implementation (PHP, Symfony)
- Room management (join/leave, auto-cleanup)
- Message delivery, broadcast, and history
- Namespace support
- Auto-reconnect and heartbeat
- Persistent storage (Doctrine ORM)
- Extensible service layer

## Installation

```bash
composer require tourze/socket-io-bundle
```

## Configuration

Set these in your `.env`:

```env
SOCKET_IO_PING_INTERVAL=25000
SOCKET_IO_PING_TIMEOUT=20000
SOCKET_IO_MAX_PAYLOAD_SIZE=1000000
```

## Quick Start

1. Register the bundle in your Symfony config if not using Flex:

```php
// config/bundles.php
return [
    // ...
    SocketIoBundle\SocketIoBundle::class => ['all' => true],
];
```

2. Add the endpoint to your routes (if not using annotation routing):

```yaml
# config/routes.yaml
socket_io:
  resource: '@SocketIoBundle/Controller/SocketController.php'
  type: annotation
```

3. Start the server and connect from your JS client:

```js
const socket = io('http://localhost:8000/socket.io/');
socket.emit('joinRoom', 'room-1');
socket.on('roomList', rooms => console.log(rooms));
```

## Dependencies

This bundle requires the following:

**PHP Requirements:**
- PHP >= 8.1

**Symfony Requirements:**
- Symfony >= 7.3
- Doctrine ORM >= 3.0
- EasyAdmin Bundle >= 4.0

**Core Dependencies:**
- `doctrine/orm`: ^3.0
- `doctrine/doctrine-bundle`: ^2.13
- `easycorp/easyadmin-bundle`: ^4
- `symfony/framework-bundle`: ^7.3
- `symfony/console`: ^7.3

## Usage

### Basic Client Connection

```js
const socket = io('http://localhost:8000/socket.io/');

// Join a room
socket.emit('joinRoom', 'room-1');

// Leave a room
socket.emit('leaveRoom', 'room-1');

// Get room list
socket.emit('getRooms');

// Listen for room updates
socket.on('roomList', rooms => console.log(rooms));
```

### Server-Side Broadcasting

```php
use SocketIoBundle\Service\MessageService;

class NotificationService
{
    public function __construct(
        private MessageService $messageService
    ) {}
    
    public function broadcastToRoom(string $roomName, string $event, array $data): void
    {
        $this->messageService->sendToRooms([$roomName], $event, $data);
    }
}
```

## Advanced Usage

### Custom Room Logic

Extend the `RoomService` to implement custom room behavior:

```php
use SocketIoBundle\Service\RoomService;

class CustomRoomService extends RoomService
{
    public function joinRoom(Socket $socket, string $roomName): void
    {
        // Custom logic before joining
        parent::joinRoom($socket, $roomName);
        // Custom logic after joining
    }
}
```

### Message Delivery Status

Monitor message delivery using the `DeliveryService` and `MessageStatus` enum:

```php
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Enum\MessageStatus;

class MyService
{
    public function __construct(
        private DeliveryService $deliveryService
    ) {}
    
    public function checkDeliveryStatus(string $messageId): MessageStatus
    {
        return $this->deliveryService->getMessageStatus($messageId);
    }
}
```

### Persistent Message History

Access message history via Doctrine entities:

```php
use SocketIoBundle\Repository\MessageRepository;

class MessageHistoryService
{
    public function __construct(
        private MessageRepository $messageRepository
    ) {}
    
    public function getRoomHistory(string $roomName, int $limit = 50): array
    {
        return $this->messageRepository->findByRoomName($roomName, $limit);
    }
}
```

## Commands

### Heartbeat Command

Execute Socket.IO heartbeat check and resource cleanup:

```bash
php bin/console socket-io:heartbeat [--daemon] [--interval=25000]
```

**Options:**
- `--daemon` (`-d`): Run in daemon mode
- `--interval=25000` (`-i`): Heartbeat interval in milliseconds (default: 25000)

This command:
- Checks active connections and removes expired ones
- Cleans up expired deliveries and messages
- Broadcasts alive events to active connections
- Can run continuously in daemon mode

### Cleanup Deliveries Command

Clean up expired message delivery records:

```bash
php bin/console socket:cleanup-deliveries [--days=7] [--daemon] [--interval=3600]
```

**Options:**
- `--days=7` (`-d`): Retention period in days (default: 7)
- `--daemon`: Run in daemon mode
- `--interval=3600` (`-i`): Cleanup interval in seconds (default: 3600)

This command removes delivery records older than the specified number of days. 
It can run once or continuously in daemon mode for automated cleanup.

## Testing

Run the test suite:

```bash
phpunit
```

Run with coverage:

```bash
phpunit --coverage-html coverage/
```

## Contributing

PRs and issues welcome! Please follow PSR-12 and Symfony best practices.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/my-new-feature`)
3. Commit your changes (`git commit -am 'Add some feature'`)
4. Push to the branch (`git push origin feature/my-new-feature`)
5. Create a new Pull Request

## License

MIT License. See [LICENSE](LICENSE).

## Changelog

See [Releases](https://packagist.org/packages/tourze/socket-io-bundle#releases) for version history.