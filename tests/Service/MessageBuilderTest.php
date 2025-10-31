<?php

namespace SocketIoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Protocol\SocketPacket;
use SocketIoBundle\Service\MessageBuilder;
use SocketIoBundle\Service\PayloadProcessor;

/**
 * @internal
 */
#[CoversClass(MessageBuilder::class)]
final class MessageBuilderTest extends TestCase
{
    private MessageBuilder $messageBuilder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->messageBuilder = new MessageBuilder();
    }

    public function testCreateMessageWithValidSocketPacket(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 是实体类，代表客户端连接，包含连接状态和会话信息
         * 2. 测试需要验证消息与发送者的关联关系
         * 3. 当前架构中没有 Socket 接口，使用实体类 Mock 是标准做法
         */
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('test-session');
                $this->setSocketId('test-socket');
            }

            public function getNamespace(): string
            {
                return '/';
            }
        };
        /*
         * 使用具体类 SocketPacket 进行 Mock 是必要的，因为：
         * 1. SocketPacket 是数据传输对象，封装了 Socket.IO 协议的数据包结构
         * 2. 测试需要模拟各种数据包格式和内容，验证消息构建逻辑
         * 3. 没有定义 SocketPacketInterface，直接 Mock DTO 类是合理的选择
         */
        $socketPacket = $this->createMock(SocketPacket::class);
        $socketPacket->method('getData')->willReturn('["test_event", "param1", "param2"]');
        $socketPacket->method('getNamespace')->willReturn('/test');
        $socketPacket->method('getId')->willReturn(123);

        $message = $this->messageBuilder->createMessage($socket, $socketPacket);

        $this->assertInstanceOf(Message::class, $message);
        $this->assertSame('test_event', $message->getEvent());
        $this->assertSame(['param1', 'param2'], $message->getData());
        $this->assertSame($socket, $message->getSender());
        $this->assertSame([
            'namespace' => '/test',
            'messageId' => 123,
        ], $message->getMetadata());
    }

    public function testCreateMessageWithNullSocketData(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 实体类包含客户端连接信息，是消息发送者的标识
         * 2. 测试边界情况时需要验证空数据处理逻辑
         * 3. 没有 Socket 接口定义，使用实体类 Mock 符合项目规范
         */
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('test-session');
                $this->setSocketId('test-socket');
            }

            public function getNamespace(): string
            {
                return '/';
            }
        };
        /*
         * 使用具体类 SocketPacket 进行 Mock 是必要的，因为：
         * 1. 需要测试空数据包的处理逻辑
         * 2. SocketPacket 作为 DTO 封装了协议细节
         * 3. 直接 Mock 具体类能准确模拟各种边界情况
         */
        $socketPacket = $this->createMock(SocketPacket::class);
        $socketPacket->method('getData')->willReturn(null);
        $socketPacket->method('getNamespace')->willReturn('/');
        $socketPacket->method('getId')->willReturn(null);

        $message = $this->messageBuilder->createMessage($socket, $socketPacket);

        $this->assertSame('', $message->getEvent());
        $this->assertSame([], $message->getData());
    }

    public function testCreateMessageWithInvalidJson(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 是一个 Doctrine 实体类，包含了业务逻辑和数据库映射
         * 2. 测试需要模拟实体的行为
         * 3. 该实体没有对应的接口，使用实体类进行 Mock 是标准做法
         */
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('test-session');
                $this->setSocketId('test-socket');
            }

            public function getNamespace(): string
            {
                return '/';
            }
        };

        /*
         * 使用具体类 SocketPacket 进行 Mock 是必要的，因为：
         * 1. SocketPacket 是项目内部的数据传输对象类
         * 2. 该类没有定义对应的接口
         * 3. 测试需要模拟 getData()、getNamespace() 和 getId() 等方法的行为
         */
        $socketPacket = $this->createMock(SocketPacket::class);
        $socketPacket->method('getData')->willReturn('invalid json');
        $socketPacket->method('getNamespace')->willReturn('/');
        $socketPacket->method('getId')->willReturn(null);

        $message = $this->messageBuilder->createMessage($socket, $socketPacket);

        $this->assertSame('', $message->getEvent());
        $this->assertSame([], $message->getData());
    }

    public function testCreateDelivery(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 实体代表消息接收者，包含连接和会话信息
         * 2. 创建投递记录时需要关联具体的接收端点
         * 3. 没有抽象接口，使用实体类 Mock 是标准实践
         */
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('test-session');
                $this->setSocketId('test-socket');
            }

            public function getNamespace(): string
            {
                return '/';
            }
        };
        /*
         * 使用具体类 Message 进行 Mock 是必要的，因为：
         * 1. Message 是核心实体，包含消息内容和元数据
         * 2. 投递记录需要关联具体的消息实体
         * 3. 实体类包含业务逻辑和数据结构，接口无法完全替代
         */
        $message = $this->createMock(Message::class);

        $delivery = $this->messageBuilder->createDelivery($socket, $message);

        $this->assertInstanceOf(Delivery::class, $delivery);
        $this->assertSame($message, $delivery->getMessage());
        $this->assertSame($socket, $delivery->getSocket());
        $this->assertSame(MessageStatus::PENDING, $delivery->getStatus());
    }

    public function testBuildMessagePayload(): void
    {
        /*
         * 使用具体类 Message 进行 Mock 是必要的，因为：
         * 1. Message 实体包含事件名称和数据，是构建载荷的核心
         * 2. 测试需要验证消息到载荷的转换逻辑
         * 3. 实体类的具体方法和属性是测试的关键部分
         */
        $message = $this->createMock(Message::class);
        $message->method('getEvent')->willReturn('test');
        $message->method('getData')->willReturn(['data']);

        /*
         * 使用具体类 Delivery 进行 Mock 是必要的，因为：
         * 1. Delivery 实体封装了消息投递的完整信息
         * 2. 构建载荷时需要访问投递记录的消息关联
         * 3. 没有 Delivery 接口，实体类 Mock 是唯一选择
         */
        $delivery = $this->createMock(Delivery::class);
        $delivery->method('getMessage')->willReturn($message);

        /*
         * 使用具体类 PayloadProcessor 进行 Mock 是必要的，因为：
         * 1. PayloadProcessor 服务负责编码和构建最终载荷
         * 2. 测试需要模拟编码过程，验证载荷构建流程
         * 3. 服务类没有接口定义，直接 Mock 是合理做法
         */
        $payloadProcessor = $this->createMock(PayloadProcessor::class);
        $payloadProcessor->method('encodePacket')->willReturn('encoded_packet');
        $payloadProcessor->method('buildPayload')->willReturn('final_payload');

        [$payload, $processedDeliveries] = $this->messageBuilder->buildMessagePayload(
            [$delivery],
            $payloadProcessor,
            1000
        );

        $this->assertSame('final_payload', $payload);
        $this->assertSame([$delivery], $processedDeliveries);
    }

    public function testCreateSocketPacket(): void
    {
        /*
         * 使用具体类 Message 进行 Mock 是必要的，因为：
         * 1. Message 实体的事件和数据需要转换为 SocketPacket
         * 2. 测试消息到数据包的转换逻辑
         * 3. 实体类提供了必要的数据结构和访问方法
         */
        $message = $this->createMock(Message::class);
        $message->method('getEvent')->willReturn('test_event');
        $message->method('getData')->willReturn(['param1', 'param2']);

        $socketPacket = $this->messageBuilder->createSocketPacket($message);

        $this->assertInstanceOf(SocketPacket::class, $socketPacket);
    }
}
