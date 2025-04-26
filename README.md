# Socket.IO Bundle

Symfony 的 Socket.IO 集成包，提供实时双向通信功能。

## 功能特性

- 完整的 Socket.IO 服务端实现
- 房间管理机制
- 消息投递和广播
- 命名空间支持
- 自动重连和心跳检测
- 数据持久化

## 房间管理

### 客户端事件

1. `joinRoom`: 加入房间

   ```javascript
   socket.emit('joinRoom', { room: '房间名称' });
   ```

2. `leaveRoom`: 离开房间

   ```javascript
   socket.emit('leaveRoom', '房间名称');
   ```

3. `getRooms`: 获取当前房间列表

   ```javascript
   socket.emit('getRooms');
   ```

### 服务端事件

1. `roomList`: 房间列表更新事件

   ```javascript
   socket.on('roomList', (rooms) => {
     console.log('当前房间列表:', rooms);
   });
   ```

### 使用流程

1. 加入房间：
   - 客户端发送 `joinRoom` 事件
   - 服务端处理加入请求
   - 服务端返回更新后的房间列表

2. 离开房间：
   - 客户端发送 `leaveRoom` 事件
   - 服务端处理离开请求
   - 服务端返回更新后的房间列表

3. 获取房间列表：
   - 客户端发送 `getRooms` 事件
   - 服务端立即返回当前房间列表

### 示例代码

```javascript
// 连接 Socket.IO 服务器
const socket = io('http://localhost:3000');

// 加入房间
socket.emit('joinRoom', 'chat-room-1');

// 监听房间列表更新
socket.on('roomList', (rooms) => {
  console.log('我的房间列表:', rooms);
});

// 离开房间
socket.emit('leaveRoom', { room: 'chat-room-1' });

// 获取当前房间列表
socket.emit('getRooms');
```

## 注意事项

1. 房间管理完全在服务端处理，客户端只能通过事件进行操作
2. 房间名称在同一命名空间下必须唯一
3. 断开连接时会自动离开所有房间
4. 空房间会被自动清理

## 配置说明

在 `.env` 文件中配置：

```env
# Socket.IO 配置
SOCKET_IO_PING_INTERVAL=25000  # 心跳间隔（毫秒）
SOCKET_IO_PING_TIMEOUT=20000   # 心跳超时（毫秒）
SOCKET_IO_MAX_PAYLOAD_SIZE=1000000  # 最大负载大小（字节）
```
