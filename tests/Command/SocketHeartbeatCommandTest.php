<?php

namespace SocketIoBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Command\SocketHeartbeatCommand;
use SocketIoBundle\Repository\MessageRepository;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Service\MessageService;
use SocketIoBundle\Service\SocketService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(SocketHeartbeatCommand::class)]
#[RunTestsInSeparateProcesses]
final class SocketHeartbeatCommandTest extends AbstractCommandTestCase
{
    private SocketHeartbeatCommand $command;

    private SocketRepository $socketRepository;

    private MessageRepository $messageRepository;

    private SocketService $socketService;

    private MessageService $messageService;

    private DeliveryService $deliveryService;

    protected function onSetUp(): void
    {
        $this->socketRepository = self::getService(SocketRepository::class);
        $this->messageRepository = self::getService(MessageRepository::class);
        $this->socketService = self::getService(SocketService::class);
        $this->messageService = self::getService(MessageService::class);
        $this->deliveryService = self::getService(DeliveryService::class);
        $this->command = self::getService(SocketHeartbeatCommand::class);
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

    public function testConstructorSetsDependencies(): void
    {
        $reflection = new \ReflectionClass($this->command);

        $socketRepositoryProperty = $reflection->getProperty('socketRepository');
        $socketRepositoryProperty->setAccessible(true);
        $this->assertSame($this->socketRepository, $socketRepositoryProperty->getValue($this->command));

        $messageRepositoryProperty = $reflection->getProperty('messageRepository');
        $messageRepositoryProperty->setAccessible(true);
        $this->assertSame($this->messageRepository, $messageRepositoryProperty->getValue($this->command));

        $socketServiceProperty = $reflection->getProperty('socketService');
        $socketServiceProperty->setAccessible(true);
        $this->assertSame($this->socketService, $socketServiceProperty->getValue($this->command));

        $messageServiceProperty = $reflection->getProperty('messageService');
        $messageServiceProperty->setAccessible(true);
        $this->assertSame($this->messageService, $messageServiceProperty->getValue($this->command));

        $deliveryServiceProperty = $reflection->getProperty('deliveryService');
        $deliveryServiceProperty->setAccessible(true);
        $this->assertSame($this->deliveryService, $deliveryServiceProperty->getValue($this->command));
    }

    public function testCommandConfiguration(): void
    {
        $this->assertSame('socket-io:heartbeat', $this->command->getName());
        $this->assertSame('执行Socket.IO心跳检查和资源清理', $this->command->getDescription());

        $definition = $this->command->getDefinition();

        // 检查选项
        $this->assertTrue($definition->hasOption('daemon'));
        $this->assertTrue($definition->hasOption('interval'));

        $daemonOption = $definition->getOption('daemon');
        $this->assertSame('d', $daemonOption->getShortcut());
        $this->assertSame('以守护进程模式运行', $daemonOption->getDescription());
        $this->assertFalse($daemonOption->getDefault());

        $intervalOption = $definition->getOption('interval');
        $this->assertSame('i', $intervalOption->getShortcut());
        $this->assertSame('心跳间隔（毫秒）', $intervalOption->getDescription());
        $this->assertSame(25000, $intervalOption->getDefault());
    }

    public function testExecuteNormalModeSuccess(): void
    {
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('执行单次心跳检查', $output);
        $this->assertStringContainsString('心跳检查完成', $output);
    }

    public function testExecuteNormalMode(): void
    {
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('执行单次心跳检查', $output);
        $this->assertStringContainsString('心跳检查完成', $output);
    }

    public function testExecuteWithIntervalOption(): void
    {
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute(['--interval' => '30000']);

        $this->assertSame(Command::SUCCESS, $exitCode);

        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('执行单次心跳检查', $output);
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

    public function testExecuteCreatesHeartbeatStrategy(): void
    {
        $commandTester = $this->getCommandTester();
        $exitCode = $commandTester->execute(['--interval' => '30000']);

        // 验证命令成功执行
        $this->assertSame(Command::SUCCESS, $exitCode);
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

        $this->assertSame('SocketIoBundle\Command\SocketHeartbeatCommand', $reflection->getName());
        $this->assertTrue($reflection->isSubclassOf(Command::class));
        $this->assertFalse($reflection->isAbstract());
        $this->assertTrue($reflection->isFinal());
    }

    public function testOptionDaemon(): void
    {
        $commandTester = $this->getCommandTester();

        // 测试守护进程选项配置
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('daemon'));

        $daemonOption = $definition->getOption('daemon');
        $this->assertSame('d', $daemonOption->getShortcut());
        $this->assertSame('以守护进程模式运行', $daemonOption->getDescription());
        $this->assertFalse($daemonOption->getDefault());
        $this->assertFalse($daemonOption->isValueRequired());

        // 由于守护进程模式会进入无限循环，我们测试选项解析但不实际执行
        // 验证可以正确创建带有--daemon选项的输入对象
        $input = new ArrayInput([
            '--daemon' => true,
            '--interval' => 5000,
        ], $this->command->getDefinition());

        $this->assertTrue((bool) $input->getOption('daemon'));
        /** @var int|string $interval */
        $interval = $input->getOption('interval');
        $this->assertSame(5000, (int) $interval);
    }

    public function testOptionInterval(): void
    {
        $commandTester = $this->getCommandTester();

        // 测试间隔选项配置
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasOption('interval'));

        $intervalOption = $definition->getOption('interval');
        $this->assertSame('i', $intervalOption->getShortcut());
        $this->assertSame('心跳间隔（毫秒）', $intervalOption->getDescription());
        $this->assertSame(25000, $intervalOption->getDefault());
        $this->assertTrue($intervalOption->isValueOptional());

        // 测试使用不同间隔值
        $input = new ArrayInput([
            '--interval' => 10000,
        ], $this->command->getDefinition());

        /** @var int|string $interval */
        $interval = $input->getOption('interval');
        $this->assertSame(10000, (int) $interval);

        // 测试默认值
        $inputDefault = new ArrayInput([], $this->command->getDefinition());
        /** @var int|string $intervalDefault */
        $intervalDefault = $inputDefault->getOption('interval');
        $this->assertSame(25000, (int) $intervalDefault);
    }
}
