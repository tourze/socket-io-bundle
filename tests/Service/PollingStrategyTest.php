<?php

namespace SocketIoBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Exception\ConnectionClosedException;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Service\MessageBuilder;
use SocketIoBundle\Service\PayloadProcessor;
use SocketIoBundle\Service\PollingStrategy;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(PollingStrategy::class)]
final class PollingStrategyTest extends TestCase
{
    /** @var MockObject&EntityManagerInterface */
    private EntityManagerInterface $em;

    /** @var MockObject&DeliveryService */
    private DeliveryService $deliveryService;

    /** @var MockObject&MessageBuilder */
    private MessageBuilder $messageBuilder;

    /** @var MockObject&PayloadProcessor */
    private PayloadProcessor $payloadProcessor;

    private PollingStrategy $pollingStrategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->deliveryService = $this->createMock(DeliveryService::class);
        $this->messageBuilder = $this->createMock(MessageBuilder::class);
        $this->payloadProcessor = $this->createMock(PayloadProcessor::class);
        $this->pollingStrategy = new PollingStrategy(
            $this->em,
            $this->deliveryService,
            $this->messageBuilder,
            $this->payloadProcessor
        );

        // Mock environment variables
        $_ENV['SOCKET_IO_PING_INTERVAL'] = '25000';
    }

    public function testShouldSendPingWhenEnoughTimePassed(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 是一个 Doctrine 实体类，包含了业务逻辑和数据库映射
         * 2. 测试需要模拟实体的行为，如 getLastPingTime() 方法
         * 3. 该实体没有对应的接口，使用实体类进行 Mock 是标准做法
         */
        $socket = $this->createMock(Socket::class);
        $socket->method('getLastPingTime')
            ->willReturn(new \DateTimeImmutable('-30 seconds'))
        ;

        $result = $this->pollingStrategy->shouldSendPing($socket, 10);

        $this->assertTrue($result);
    }

    public function testShouldNotSendPingWhenNotEnoughTimePassed(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 是一个 Doctrine 实体类，包含了业务逻辑和数据库映射
         * 2. 测试需要模拟实体的行为，如 getLastPingTime() 方法
         * 3. 该实体没有对应的接口，使用实体类进行 Mock 是标准做法
         */
        $socket = $this->createMock(Socket::class);
        $socket->method('getLastPingTime')
            ->willReturn(new \DateTimeImmutable('-5 seconds'))
        ;

        $result = $this->pollingStrategy->shouldSendPing($socket, 10);

        $this->assertFalse($result);
    }

    public function testShouldNotSendPingWhenNoLastPingTime(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 是一个 Doctrine 实体类，包含了业务逻辑和数据库映射
         * 2. 测试需要模拟实体的行为，如 getLastPingTime() 方法
         * 3. 该实体没有对应的接口，使用实体类进行 Mock 是标准做法
         */
        $socket = $this->createMock(Socket::class);
        $socket->method('getLastPingTime')->willReturn(null);

        $result = $this->pollingStrategy->shouldSendPing($socket, 10);

        $this->assertFalse($result);
    }

    public function testHandlePollTimeout(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 是一个 Doctrine 实体类，包含了业务逻辑和数据库映射
         * 2. 测试需要模拟实体的行为，如 updatePingTime() 方法
         * 3. 该实体没有对应的接口，使用实体类进行 Mock 是标准做法
         */
        $socket = $this->createMock(Socket::class);
        $socket->expects($this->once())->method('updatePingTime');

        $this->em->expects($this->once())->method('flush');

        $response = $this->pollingStrategy->handlePollTimeout($socket);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testWaitForMessagesWithPendingDeliveries(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 是一个 Doctrine 实体类，包含了业务逻辑和数据库映射
         * 2. 测试需要模拟实体的行为，如 isConnected() 和 updateDeliverTime() 方法
         * 3. 该实体没有对应的接口，使用实体类进行 Mock 是标准做法
         */
        $socket = $this->createMock(Socket::class);
        $socket->method('isConnected')->willReturn(true);
        $socket->expects($this->once())->method('updateDeliverTime');

        /*
         * 使用具体类 Delivery 进行 Mock 是必要的，因为：
         * 1. Delivery 是一个 Doctrine 实体类，代表消息投递记录
         * 2. 测试需要模拟消息投递的行为和状态
         * 3. 该实体没有对应的接口，使用实体类进行 Mock 是标准做法
         */
        $delivery = $this->createMock(Delivery::class);

        $this->deliveryService
            ->method('getPendingDeliveries')
            ->with($socket)
            ->willReturn([$delivery])
        ;

        $this->messageBuilder
            ->method('buildMessagePayload')
            ->willReturn(['payload_content', [$delivery]])
        ;

        $this->deliveryService
            ->expects($this->once())
            ->method('markDelivered')
            ->with($delivery)
        ;

        $this->em->expects($this->once())->method('flush');

        $response = $this->pollingStrategy->waitForMessagesOrTimeout($socket, 1, 1000);

        $this->assertInstanceOf(Response::class, $response);
    }

    public function testWaitForMessagesConnectionClosed(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 是一个 Doctrine 实体类，包含了业务逻辑和数据库映射
         * 2. 测试需要模拟实体的行为，如 isConnected() 方法返回 false
         * 3. 该实体没有对应的接口，使用实体类进行 Mock 是标准做法
         */
        $socket = $this->createMock(Socket::class);
        $socket->method('isConnected')->willReturn(false);

        $this->deliveryService
            ->method('getPendingDeliveries')
            ->willReturn([])
        ;

        $this->em->expects($this->once())->method('refresh');

        $this->expectException(ConnectionClosedException::class);

        $this->pollingStrategy->waitForMessagesOrTimeout($socket, 1, 1000);
    }

    public function testWaitForMessagesOrTimeoutWithoutMessages(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 是一个 Doctrine 实体类，包含了业务逻辑和数据库映射
         * 2. 测试需要模拟实体的行为，如 isConnected() 和 updatePingTime() 方法
         * 3. 该实体没有对应的接口，使用实体类进行 Mock 是标准做法
         */
        $socket = $this->createMock(Socket::class);
        $socket->method('isConnected')->willReturn(true);
        $socket->expects($this->once())->method('updatePingTime');

        $this->deliveryService
            ->method('getPendingDeliveries')
            ->willReturn([])
        ;

        $this->em->expects($this->once())->method('flush');

        // 用短超时避免测试运行太长时间
        $response = $this->pollingStrategy->waitForMessagesOrTimeout($socket, 0, 1000);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testWaitForMessagesOrTimeoutCompleteFlow(): void
    {
        /*
         * 使用具体类 Socket 进行 Mock 是必要的，因为：
         * 1. Socket 是一个 Doctrine 实体类，包含了业务逻辑和数据库映射
         * 2. 测试需要模拟实体的行为，如 isConnected() 和 updateDeliverTime() 方法
         * 3. 该实体没有对应的接口，使用实体类进行 Mock 是标准做法
         */
        $socket = $this->createMock(Socket::class);
        $socket->method('isConnected')->willReturn(true);
        $socket->expects($this->once())->method('updateDeliverTime');

        /*
         * 使用具体类 Delivery 进行 Mock 是必要的，因为：
         * 1. Delivery 是一个 Doctrine 实体类，代表消息投递记录
         * 2. 测试需要模拟消息投递的行为和状态
         * 3. 该实体没有对应的接口，使用实体类进行 Mock 是标准做法
         */
        $delivery = $this->createMock(Delivery::class);

        $this->deliveryService
            ->expects($this->atLeastOnce())
            ->method('getPendingDeliveries')
            ->with($socket)
            ->willReturnOnConsecutiveCalls([$delivery], [])
        ;

        $this->messageBuilder
            ->method('buildMessagePayload')
            ->willReturn(['test-payload', [$delivery]])
        ;

        $this->deliveryService
            ->expects($this->once())
            ->method('markDelivered')
            ->with($delivery)
        ;

        $this->em->expects($this->once())->method('flush');

        $response = $this->pollingStrategy->waitForMessagesOrTimeout($socket, 1, 1000);

        $this->assertInstanceOf(Response::class, $response);
    }
}
