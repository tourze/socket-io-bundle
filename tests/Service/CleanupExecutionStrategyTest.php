<?php

namespace SocketIoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Service\CleanupExecutionStrategy;
use SocketIoBundle\Service\DeliveryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
#[CoversClass(CleanupExecutionStrategy::class)]
final class CleanupExecutionStrategyTest extends TestCase
{
    private DeliveryService&MockObject $deliveryService;

    private CleanupExecutionStrategy $strategy;

    public function testExecuteSingleRunSuccess(): void
    {
        $this->deliveryService
            ->expects($this->once())
            ->method('cleanupDeliveries')
            ->with(30)
            ->willReturn(5)
        ;

        $output = new BufferedOutput();
        $result = $this->strategy->execute($output);

        $this->assertSame(Command::SUCCESS, $result);
        $this->assertStringContainsString('开始清理 30 天前的消息投递记录', $output->fetch());
    }

    public function testExecuteWithCleanupException(): void
    {
        $this->deliveryService
            ->expects($this->once())
            ->method('cleanupDeliveries')
            ->with(30)
            ->willThrowException(new \RuntimeException('Database error'))
        ;

        $output = new BufferedOutput();
        $result = $this->strategy->execute($output);

        $this->assertSame(Command::SUCCESS, $result);
        $outputContent = $output->fetch();
        $this->assertStringContainsString('清理失败: Database error', $outputContent);
    }

    protected function setUp(): void
    {
        parent::setUp();

        /* 必须使用具体的 DeliveryService 类进行 Mock：
         * 理由1：该类包含复杂的投递管理和清理逻辑，需要验证具体的业务方法调用
         * 理由2：没有定义对应的投递服务接口，直接 Mock 具体类能确保测试的准确性
         * 理由3：测试需要验证 cleanupDeliveries 等具体清理方法的调用和异常处理
         */
        $this->deliveryService = $this->createMock(DeliveryService::class);
        $this->strategy = new CleanupExecutionStrategy(
            $this->deliveryService,
            30, // days
            false, // isDaemon
            60 // interval
        );
    }
}
