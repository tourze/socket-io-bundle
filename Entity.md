# Socket-IO Bundle 实体设计说明

本模块包含四个核心实体：Socket、Room、Message、Delivery。所有实体均基于 Doctrine ORM，遵循 PSR-12 及项目实体规范。

---

## 1. Socket（连接）

- 表名：`ims_socket_io_connection`
- 代表一个客户端连接实例。
- 主要字段：
  - `id`：主键，Snowflake 算法生成
  - `sessionId`：会话ID，唯一
  - `socketId`：Socket.IO 连接ID，唯一
  - `namespace`：命名空间，默认`/`
  - `clientId`：客户端自定义ID，可选
  - `handshake`：握手数据（JSON）
  - `lastPingTime`：最后心跳时间
  - `lastDeliverTime`：最后消息投递时间
  - `lastActiveTime`：最后活跃时间
  - `connected`：是否在线
  - `pollCount`：轮询次数
  - `transport`：传输类型（如 polling）
  - `createTime`/`updateTime`：创建/更新时间
- 关系：
  - 多对多 `rooms`（Room）
  - 一对多 `deliveries`（Delivery）

---

## 2. Room（房间）

- 表名：`ims_socket_io_room`
- 代表一个逻辑房间，支持命名空间隔离。
- 主要字段：
  - `id`：主键，Snowflake 算法生成
  - `name`：房间名
  - `namespace`：命名空间，默认`/`
  - `metadata`：房间元数据（JSON）
  - `createTime`/`updateTime`：创建/更新时间
- 关系：
  - 多对多 `sockets`（Socket）
  - 多对多 `messages`（Message）
- 设计说明：
  - 房间名+命名空间唯一
  - 空房间自动清理

---

## 3. Message（消息）

- 表名：`ims_socket_io_message`
- 代表一条 Socket.IO 消息事件。
- 主要字段：
  - `id`：主键，Snowflake 算法生成
  - `event`：事件名
  - `data`：消息内容（JSON）
  - `sender`：发送者（Socket，nullable）
  - `metadata`：消息元数据（JSON，可选）
  - `createTime`：创建时间
- 关系：
  - 多对多 `rooms`（Room）
  - 一对多 `deliveries`（Delivery）

---

## 4. Delivery（消息投递）

- 表名：`ims_socket_io_delivery`
- 代表一条消息对某个连接的投递记录。
- 主要字段：
  - `id`：主键，Snowflake 算法生成
  - `socket`：目标连接（Socket）
  - `message`：消息实体（Message）
  - `status`：投递状态（枚举 MessageStatus：PENDING/DELIVERED/FAILED）
  - `error`：失败原因（可选）
  - `retries`：重试次数
  - `deliveredAt`：实际投递时间
  - `createTime`/`updateTime`：创建/更新时间
- 设计说明：
  - 支持重试与失败处理
  - 可用于消息可靠性统计

---

## 实体关系图（ER 简述）

- Socket <-> Room：多对多
- Room <-> Message：多对多
- Message <-> Delivery：一对多
- Socket <-> Delivery：一对多

---

## 设计补充

- 所有主键均为 Snowflake 算法生成的字符串
- 所有时间字段均为 DATETIME 类型，便于筛选和排序
- 采用 JSON 字段存储扩展数据，便于灵活扩展
- 通过多对多关系实现房间与消息、房间与连接的灵活绑定
- 投递状态采用枚举，便于业务扩展和前端展示
