# Socket.IO Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)
[![Build Status](https://img.shields.io/travis/tourze/socket-io-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/socket-io-bundle)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/socket-io-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/socket-io-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)

A Symfony bundle providing a full-featured Socket.IO server implementation for real-time, bidirectional communication. Supports room management, message delivery, namespaces, and persistent storage.

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

**Requirements:**

- PHP >= 8.1
- Symfony >= 6.4
- Doctrine ORM

## Quick Start

Register the bundle in your Symfony config if not using Flex.

```php
// config/bundles.php
return [
    // ...
    SocketIoBundle\SocketIoBundle::class => ['all' => true],
];
```

Add the endpoint to your routes (if not using annotation routing):

```yaml
# config/routes.yaml
socket_io:
  resource: '@SocketIoBundle/Controller/SocketController.php'
  type: annotation
```

Start the server and connect from your JS client:

```js
const socket = io('http://localhost:8000/socket.io/');
socket.emit('joinRoom', 'room-1');
socket.on('roomList', rooms => console.log(rooms));
```

## Configuration

Set these in your `.env`:

```env
SOCKET_IO_PING_INTERVAL=25000
SOCKET_IO_PING_TIMEOUT=20000
SOCKET_IO_MAX_PAYLOAD_SIZE=1000000
```

## Usage

- Join/leave rooms: `joinRoom`, `leaveRoom`
- Get room list: `getRooms`
- Listen for `roomList` updates
- Broadcast messages: use the `MessageService` or emit from server

## Advanced

- Custom room logic: extend `RoomService`
- Message delivery status: see `DeliveryService` and `MessageStatus` enum
- Persistent message history: via Doctrine entities

## Contributing

PRs and issues welcome! Please follow PSR-12 and Symfony best practices. Run tests with:

```bash
phpunit
```

## License

MIT License. See [LICENSE](LICENSE).

## Changelog

See [Releases](https://packagist.org/packages/tourze/socket-io-bundle#releases) for version history.
