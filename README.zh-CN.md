# Socket.IO Bundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)
[![Build Status](https://img.shields.io/travis/tourze/socket-io-bundle/master.svg?style=flat-square)](https://travis-ci.org/tourze/socket-io-bundle)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/socket-io-bundle.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/socket-io-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/socket-io-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/socket-io-bundle)

一个为 Symfony 提供完整 Socket.IO 服务端实现的 Bundle，支持实时双向通信、房间管理、消息投递、命名空间和持久化存储。

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

**依赖要求：**

- PHP >= 8.1
- Symfony >= 6.4
- Doctrine ORM

## 快速开始

如果未使用 Flex，请在 Symfony 配置中注册 Bundle：

```php
// config/bundles.php
return [
    // ...
    SocketIoBundle\SocketIoBundle::class => ['all' => true],
];
```

如未使用注解路由，请在路由配置中添加：

```yaml
# config/routes.yaml
socket_io:
  resource: '@SocketIoBundle/Controller/SocketController.php'
  type: annotation
```

启动服务端后，前端 JS 客户端连接示例：

```js
const socket = io('http://localhost:8000/socket.io/');
socket.emit('joinRoom', 'room-1');
socket.on('roomList', rooms => console.log(rooms));
```

## 配置

在 `.env` 文件中设置：

```env
SOCKET_IO_PING_INTERVAL=25000
SOCKET_IO_PING_TIMEOUT=20000
SOCKET_IO_MAX_PAYLOAD_SIZE=1000000
```

## 用法说明

- 加入/离开房间：`joinRoom`、`leaveRoom`
- 获取房间列表：`getRooms`
- 监听 `roomList` 事件获取房间变更
- 消息广播：使用 `MessageService` 或服务端 emit

## 高级用法

- 自定义房间逻辑：扩展 `RoomService`
- 消息投递状态：参考 `DeliveryService` 和 `MessageStatus` 枚举
- 消息历史持久化：基于 Doctrine 实体

## 贡献指南

欢迎 PR 和 Issue！请遵循 PSR-12 及 Symfony 最佳实践。测试运行：

```bash
phpunit
```

## 许可证

MIT License，详见 [LICENSE](LICENSE)。

## 更新日志

版本历史请见 [Releases](https://packagist.org/packages/tourze/socket-io-bundle#releases)。
