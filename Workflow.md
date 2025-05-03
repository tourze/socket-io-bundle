# Socket-IO Bundle 工作流程

本模块主要涉及连接建立、房间管理、消息投递三个核心流程。

---

## 1. 连接建立流程

```mermaid
sequenceDiagram
    participant Client
    participant Controller
    participant SocketIOService
    participant SocketService
    participant DB
    Client->>Controller: HTTP 请求 /socket.io/
    Controller->>SocketIOService: handleRequest()
    SocketIOService->>SocketService: createConnection()
    SocketService->>DB: 新建 Socket 实体
    SocketService-->>SocketIOService: 返回 Socket
    SocketIOService-->>Controller: 返回握手数据
    Controller-->>Client: 响应握手
```

---

## 2. 房间管理流程

```mermaid
sequenceDiagram
    participant Client
    participant Controller
    participant RoomService
    participant DB
    Client->>Controller: emit('joinRoom', room)
    Controller->>RoomService: joinRoom(socket, room)
    RoomService->>DB: 查找/新建 Room
    RoomService->>DB: 更新 Socket-Room 关系
    RoomService-->>Controller: 完成
    Controller-->>Client: emit('roomList', rooms)
```

---

## 3. 消息投递流程

```mermaid
sequenceDiagram
    participant Sender
    participant Controller
    participant MessageService
    participant DeliveryService
    participant DB
    participant Receiver
    Sender->>Controller: emit('event', data)
    Controller->>MessageService: createMessage()
    MessageService->>DB: 新建 Message
    MessageService->>DeliveryService: dispatchMessageToSocket()
    DeliveryService->>DB: 新建 Delivery
    DeliveryService-->>Receiver: emit('event', data)
    Receiver-->>DeliveryService: ack/deliver
    DeliveryService->>DB: 更新 Delivery 状态
```

---

## 说明

- 所有流程均基于 HTTP/轮询，支持后续扩展 WebSocket。
- 房间和消息均持久化，支持断线重连和消息历史。
- 消息投递有状态跟踪，支持失败重试。
