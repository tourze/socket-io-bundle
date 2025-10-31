# Socket.IO Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![PHP 版本](https://img.shields.io/packagist/php-v/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)
[![最新版本](https://img.shields.io/packagist/v/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)
[![许可证](https://img.shields.io/packagist/l/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)
[![构建状态](https://img.shields.io/travis/tourze/socket-io-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/socket-io-bundle)
[![代码覆盖率](https://img.shields.io/scrutinizer/coverage/g/tourze/socket-io-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/socket-io-bundle)
[![总下载量](https://img.shields.io/packagist/dt/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)

一个为 Symfony 提供完整 Socket.IO 服务端实现的 Bundle，支持实时双向通信、房间管理、消息投递、命名空间和持久化存储。

## 目录

- [功能特性](#功能特性)
- [安装](#安装)
- [配置](#配置)
- [依赖要求](#依赖要求)
- [快速开始](#快速开始)
- [使用方法](#使用方法)
- [高级用法](#高级用法)
- [命令行工具](#命令行工具)
- [测试](#测试)
- [贡献](#贡献)
- [许可证](#许可证)
- [更新日志](#更新日志)

## 功能特性

- 完整 Socket.IO 服务端实现（PHP/Symfony）
- 房间管理（加入/离开，自动清理）
- 消息投递、广播与历史记录
- 命名空间支持
- 自动重连与心跳检测
- 持久化存储（Doctrine ORM）
- 可扩展的服务层

## 安装

```bash
composer require tourze/socket-io-bundle
```

## 配置

在您的 `.env` 文件中设置：

```env
SOCKET_IO_PING_INTERVAL=25000
SOCKET_IO_PING_TIMEOUT=20000
SOCKET_IO_MAX_PAYLOAD_SIZE=1000000
```

## 快速开始

1. 如果没有使用 Flex，在 Symfony 配置中注册 Bundle：

```php
// config/bundles.php
return [
    // ...
    SocketIoBundle\SocketIoBundle::class => ['all' => true],
];
```

2. 添加端点到您的路由（如果不使用注解路由）：

```yaml
# config/routes.yaml
socket_io:
  resource: '@SocketIoBundle/Controller/SocketController.php'
  type: annotation
```

3. 启动服务器并从 JS 客户端连接：

```js
const socket = io('http://localhost:8000/socket.io/');
socket.emit('joinRoom', 'room-1');
socket.on('roomList', rooms => console.log(rooms));
```

## 依赖要求

此 Bundle 需要以下依赖：

**PHP 要求：**
- PHP >= 8.1

**Symfony 要求：**
- Symfony >= 7.3
- Doctrine ORM >= 3.0
- EasyAdmin Bundle >= 4.0

**核心依赖：**
- `doctrine/orm`: ^3.0
- `doctrine/doctrine-bundle`: ^2.13
- `easycorp/easyadmin-bundle`: ^4
- `symfony/framework-bundle`: ^7.3
- `symfony/console`: ^7.3

## 使用方法

### 基本客户端连接

```js
const socket = io('http://localhost:8000/socket.io/');

// 加入房间
socket.emit('joinRoom', 'room-1');

// 离开房间
socket.emit('leaveRoom', 'room-1');

// 获取房间列表
socket.emit('getRooms');

// 监听房间更新
socket.on('roomList', rooms => console.log(rooms));
```

### 服务端广播

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

## 高级用法

### 自定义房间逻辑

扩展 `RoomService` 来实现自定义房间行为：

```php
use SocketIoBundle\Service\RoomService;

class CustomRoomService extends RoomService
{
    public function joinRoom(Socket $socket, string $roomName): void
    {
        // 加入前的自定义逻辑
        parent::joinRoom($socket, $roomName);
        // 加入后的自定义逻辑
    }
}
```

### 消息投递状态

使用 `DeliveryService` 和 `MessageStatus` 枚举监控消息投递：

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

### 持久化消息历史

通过 Doctrine 实体访问消息历史：

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

## 命令行工具

### 心跳检测命令

执行 Socket.IO 心跳检测和资源清理：

```bash
php bin/console socket-io:heartbeat [--daemon] [--interval=25000]
```

**选项：**
- `--daemon` (`-d`)：以守护进程模式运行
- `--interval=25000` (`-i`)：心跳间隔（毫秒，默认：25000）

此命令：
- 检查活跃连接并移除过期连接
- 清理过期的投递和消息
- 向活跃连接广播存活事件
- 可在守护进程模式下连续运行

### 清理投递记录命令

清理过期的消息投递记录：

```bash
php bin/console socket:cleanup-deliveries [--days=7] [--daemon] [--interval=3600]
```

**选项：**
- `--days=7` (`-d`)：保留天数（默认：7）
- `--daemon`：以守护进程模式运行
- `--interval=3600` (`-i`)：清理间隔（秒，默认：3600）

此命令删除超过指定天数的投递记录。
可以一次性运行或在守护进程模式下连续运行以进行自动清理。

## 测试

运行测试套件：

```bash
phpunit
```

运行覆盖率测试：

```bash
phpunit --coverage-html coverage/
```

## 贡献

欢迎 PR 和 issue！请遵循 PSR-12 和 Symfony 最佳实践。

1. Fork 仓库
2. 创建您的功能分支 (`git checkout -b feature/my-new-feature`)
3. 提交您的更改 (`git commit -am 'Add some feature'`)
4. 推送到分支 (`git push origin feature/my-new-feature`)
5. 创建新的 Pull Request

## 许可证

MIT 许可证。查看 [LICENSE](LICENSE)。

## 更新日志

查看 [Releases](https://packagist.org/packages/tourze/socket-io-bundle#releases) 获取版本历史。