<?php

namespace SocketIoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Command\CleanupDeliveriesCommand;
use SocketIoBundle\Service\DeliveryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(CleanupDeliveriesCommand::class)]
#[RunTestsInSeparateProcesses]
final class CleanupDeliveriesCommandTest extends AbstractCommandTestCase
{
    private CleanupDeliveriesCommand $command;

    private DeliveryService $deliveryService;

    protected function onSetUp(): void
    {
        $this->deliveryService = self::getService(DeliveryService::class);
        $this->command = self::getService(CleanupDeliveriesCommand::class);
    }

    protected function getCommandTester(): CommandTester
    {
        return new CommandTester($this->command);
    }

    public function testExtendsCommand(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $this->assertTrue($reflection->isSubclassOf(Command::class));
    }

    public function testConstructorSetsDeliveryService(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $property = $reflection->getProperty('deliveryService');
        $property->setAccessible(true);

        $this->assertSame($this->deliveryService, $property->getValue($this->command));
    }

    public function testCommandConfiguration(): void
    {
        $this->assertSame('socket:cleanup-deliveries', $this->command->getName());
        $this->assertSame('清理过期的消息投递记录', $this->command->getDescription());

        $definition = $this->command->getDefinition();

        // 检查选项
        $this->assertTrue($definition->hasOption('days'));
        $this->assertTrue($definition->hasOption('daemon'));
        $this->assertTrue($definition->hasOption('interval'));

        $daysOption = $definition->getOption('days');
        $this->assertSame('d', $daysOption->getShortcut());
        $this->assertSame('保留天数', $daysOption->getDescription());
        $this->assertSame(7, $daysOption->getDefault());

        $daemonOption = $definition->getOption('daemon');
        $this->assertNull($daemonOption->getShortcut());
        $this->assertSame('以守护进程模式运行', $daemonOption->getDescription());
        $this->assertFalse($daemonOption->getDefault());

        $intervalOption = $definition->getOption('interval');
        $this->assertSame('i', $intervalOption->getShortcut());
        $this->assertSame('清理间隔（秒）', $intervalOption->getDescription());
        $this->assertSame(3600, $intervalOption->getDefault());
    }

    public function testExecuteNormalModeSuccess(): void
    {
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('开始清理 7 天前的消息投递记录', $output);
    }

    public function testExecuteNormalModeWithCustomDays(): void
    {
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute(['--days' => '14']);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('开始清理 14 天前的消息投递记录', $output);
    }

    public function testExecuteMethodExistsAndIsProtected(): void
    {
        $reflection = new \ReflectionClass($this->command);

        $this->assertTrue($reflection->hasMethod('execute'));
        $this->assertTrue($reflection->getMethod('execute')->isProtected());
    }

    public function testConfigureMethodExistsAndIsProtected(): void
    {
        $reflection = new \ReflectionClass($this->command);

        $this->assertTrue($reflection->hasMethod('configure'));
        $this->assertTrue($reflection->getMethod('configure')->isProtected());
    }

    public function testExecuteMethodSignature(): void
    {
        $reflection = new \ReflectionMethod($this->command, 'execute');

        $this->assertSame('execute', $reflection->getName());
        $this->assertSame(2, $reflection->getNumberOfParameters());
        $this->assertSame(2, $reflection->getNumberOfRequiredParameters());

        $parameters = $reflection->getParameters();
        $this->assertSame('input', $parameters[0]->getName());
        $this->assertSame('output', $parameters[1]->getName());

        $returnType = $reflection->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertSame('int', $returnType instanceof \ReflectionNamedType ? $returnType->getName() : (string) $returnType);
    }

    public function testCommandClassStructure(): void
    {
        $reflection = new \ReflectionClass($this->command);

        $this->assertSame('SocketIoBundle\Command\CleanupDeliveriesCommand', $reflection->getName());
        $this->assertTrue($reflection->isSubclassOf(Command::class));
        $this->assertFalse($reflection->isAbstract());
        $this->assertTrue($reflection->isFinal());
    }

    public function testCommandNameConstant(): void
    {
        $this->assertSame('socket:cleanup-deliveries', CleanupDeliveriesCommand::NAME);
    }

    public function testOptionDays(): void
    {
        $commandTester = $this->getCommandTester();

        // 测试天数选项配置
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('days'));

        $daysOption = $definition->getOption('days');
        $this->assertSame('d', $daysOption->getShortcut());
        $this->assertSame('保留天数', $daysOption->getDescription());
        $this->assertSame(7, $daysOption->getDefault());
        $this->assertTrue($daysOption->isValueOptional());

        // 测试使用不同天数值
        $input = new ArrayInput([
            '--days' => 14,
        ], $this->command->getDefinition());

        /** @var int|string $days */
        $days = $input->getOption('days');
        $this->assertSame(14, (int) $days);

        // 测试默认值
        $inputDefault = new ArrayInput([], $this->command->getDefinition());
        /** @var int|string $daysDefault */
        $daysDefault = $inputDefault->getOption('days');
        $this->assertSame(7, (int) $daysDefault);

        // 测试字符串数字值
        $inputString = new ArrayInput([
            '--days' => '30',
        ], $this->command->getDefinition());

        /** @var int|string $daysString */
        $daysString = $inputString->getOption('days');
        $this->assertSame(30, (int) $daysString);
    }

    public function testOptionDaemon(): void
    {
        $commandTester = $this->getCommandTester();

        // 测试守护进程选项配置
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('daemon'));

        $daemonOption = $definition->getOption('daemon');
        $this->assertNull($daemonOption->getShortcut());
        $this->assertSame('以守护进程模式运行', $daemonOption->getDescription());
        $this->assertFalse($daemonOption->getDefault());
        $this->assertFalse($daemonOption->isValueRequired());

        // 测试守护进程选项解析
        $input = new ArrayInput([
            '--daemon' => true,
        ], $this->command->getDefinition());

        $this->assertTrue((bool) $input->getOption('daemon'));

        // 测试默认值（不启用守护进程）
        $inputDefault = new ArrayInput([], $this->command->getDefinition());
        $this->assertFalse((bool) $inputDefault->getOption('daemon'));
    }

    public function testOptionInterval(): void
    {
        $commandTester = $this->getCommandTester();

        // 测试间隔选项配置
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('interval'));

        $intervalOption = $definition->getOption('interval');
        $this->assertSame('i', $intervalOption->getShortcut());
        $this->assertSame('清理间隔（秒）', $intervalOption->getDescription());
        $this->assertSame(3600, $intervalOption->getDefault());
        $this->assertTrue($intervalOption->isValueOptional());

        // 测试使用不同间隔值
        $input = new ArrayInput([
            '--interval' => 1800,
        ], $this->command->getDefinition());

        /** @var int|string $interval */
        $interval = $input->getOption('interval');
        $this->assertSame(1800, (int) $interval);

        // 测试默认值
        $inputDefault = new ArrayInput([], $this->command->getDefinition());
        /** @var int|string $intervalDefault */
        $intervalDefault = $inputDefault->getOption('interval');
        $this->assertSame(3600, (int) $intervalDefault);

        // 测试字符串数字值
        $inputString = new ArrayInput([
            '--interval' => '7200',
        ], $this->command->getDefinition());

        /** @var int|string $intervalString */
        $intervalString = $inputString->getOption('interval');
        $this->assertSame(7200, (int) $intervalString);
    }
}
