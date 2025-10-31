<?php

namespace SocketIoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Repository\MessageRepository;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Service\HeartbeatExecutionStrategy;
use SocketIoBundle\Service\MessageService;
use SocketIoBundle\Service\SocketService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(HeartbeatExecutionStrategy::class)]
final class HeartbeatExecutionStrategyTest extends TestCase
{
    private SocketRepository&MockObject $socketRepository;

    private MessageRepository&MockObject $messageRepository;

    private SocketService&MockObject $socketService;

    private MessageService&MockObject $messageService;

    private DeliveryService&MockObject $deliveryService;

    private HeartbeatExecutionStrategy $strategy;

    public function testExecuteSingleRunSuccess(): void
    {
        // 模拟活跃连接
        $socket = new class extends Socket {
            public function __construct()
            {
                parent::__construct();
                $this->setSessionId('session123');
                $this->setSocketId('test-socket');
            }

            public function getNamespace(): string
            {
                return '/';
            }
        };

        $this->socketRepository
            ->expects($this->once())
            ->method('findActiveConnections')
            ->willReturn([$socket])
        ;

        $this->socketService
            ->expects($this->once())
            ->method('checkActive')
            ->with($socket)
        ;

        $this->deliveryService
            ->expects($this->once())
            ->method('cleanupDeliveries')
            ->willReturn(3)
        ;

        $this->messageRepository
            ->expects($this->once())
            ->method('cleanupOldMessages')
            ->willReturn(2)
        ;

        $this->deliveryService
            ->expects($this->once())
            ->method('cleanupQueues')
        ;

        $this->messageService
            ->expects($this->exactly(2))
            ->method('broadcast')
            ->willReturnOnConsecutiveCalls(0, 1)
        ;

        $output = new BufferedOutput();
        $result = $this->strategy->execute($output);

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertStringContainsString('执行单次心跳检查', $output->fetch());
    }

    public function testExecuteWithException(): void
    {
        $this->socketRepository
            ->expects($this->once())
            ->method('findActiveConnections')
            ->willThrowException(new \RuntimeException('Database error'))
        ;

        $output = new BufferedOutput();
        $result = $this->strategy->execute($output);

        $this->assertSame(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('心跳检查失败: Database error', $outputContent);
    }

    protected function setUp(): void
    {
        parent::setUp();

        /* 必须使用具体的 SocketRepository 类进行 Mock：
         * 理由1：该类继承自 ServiceEntityRepository，包含 Doctrine ORM 的专门实现
         * 理由2：测试需要验证对连接查询方法的具体调用（如 findActiveConnections）
         * 理由3：没有定义通用的 Repository 接口，直接 Mock 具体类能确保测试的完整性
         */
        $this->socketRepository = $this->createMock(SocketRepository::class);
        /* 必须使用具体的 MessageRepository 类进行 Mock：
         * 理由1：该类继承自 ServiceEntityRepository，包含 Doctrine ORM 的专门实现
         * 理由2：测试需要验证对消息清理方法的具体调用（如 cleanupOldMessages）
         * 理由3：没有定义通用的 Repository 接口，直接 Mock 具体类能确保测试的完整性
         */
        $this->messageRepository = $this->createMock(MessageRepository::class);
        /* 必须使用具体的 SocketService 类进行 Mock：
         * 理由1：该类包含复杂的 Socket 连接管理逻辑，需要验证具体操作的调用
         * 理由2：没有定义对应的连接管理接口，直接 Mock 具体类可以验证实际的业务流程
         * 理由3：测试需要验证对 checkActive、disconnect 等具体方法的调用，而非抽象接口
         */
        $this->socketService = $this->createMock(SocketService::class);
        /* 必须使用具体的 MessageService 类进行 Mock：
         * 理由1：该类负责消息广播的核心功能，测试需要验证具体的广播操作
         * 理由2：没有定义相应的消息服务接口，使用具体类能确保测试的准确性
         * 理由3：测试需要验证对 broadcast 等具体方法的调用，而非抽象定义
         */
        $this->messageService = $this->createMock(MessageService::class);
        /* 必须使用具体的 DeliveryService 类进行 Mock：
         * 理由1：该类负责消息投递清理的核心业务逻辑，测试需要验证具体的清理操作
         * 理由2：没有定义相应的投递服务接口，使用具体类能确保测试的完整性
         * 理由3：测试需要验证对 cleanupDeliveries、cleanupQueues 等具体方法的调用，而非抽象接口
         */
        $this->deliveryService = $this->createMock(DeliveryService::class);

        $this->strategy = new HeartbeatExecutionStrategy(
            $this->socketRepository,
            $this->messageRepository,
            $this->socketService,
            $this->messageService,
            $this->deliveryService,
            false, // isDaemon
            5000 // interval
        );
    }
}
