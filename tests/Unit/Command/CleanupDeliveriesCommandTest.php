<?php

namespace SocketIoBundle\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Command\CleanupDeliveriesCommand;
use SocketIoBundle\Service\DeliveryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupDeliveriesCommandTest extends TestCase
{
    private CleanupDeliveriesCommand $command;
    private DeliveryService $deliveryService;

    protected function setUp(): void
    {
        $this->deliveryService = $this->createMock(DeliveryService::class);
        $this->command = new CleanupDeliveriesCommand($this->deliveryService);
    }

    public function test_extends_command(): void
    {
        $this->assertInstanceOf(Command::class, $this->command);
    }

    public function test_constructor_sets_delivery_service(): void
    {
        $reflection = new \ReflectionClass($this->command);
        $property = $reflection->getProperty('deliveryService');
        $property->setAccessible(true);
        
        $this->assertSame($this->deliveryService, $property->getValue($this->command));
    }

    public function test_command_configuration(): void
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

    public function test_execute_normal_mode_success(): void
    {
        $this->deliveryService->expects($this->once())
            ->method('cleanupDeliveries')
            ->with(7)
            ->willReturn(42);
        
        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);
        
        $this->assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('开始清理 7 天前的消息投递记录', $output);
        $this->assertStringContainsString('清理完成，共删除 42 条记录', $output);
    }

    public function test_execute_normal_mode_with_custom_days(): void
    {
        $this->deliveryService->expects($this->once())
            ->method('cleanupDeliveries')
            ->with(14)
            ->willReturn(25);
        
        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute(['--days' => '14']);
        
        $this->assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('开始清理 14 天前的消息投递记录', $output);
        $this->assertStringContainsString('清理完成，共删除 25 条记录', $output);
    }

    public function test_execute_normal_mode_with_exception(): void
    {
        $exception = new \RuntimeException('Database error');
        $this->deliveryService->expects($this->once())
            ->method('cleanupDeliveries')
            ->with(7)
            ->willThrowException($exception);
        
        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);
        
        $this->assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('开始清理 7 天前的消息投递记录', $output);
        $this->assertStringContainsString('清理失败: Database error', $output);
    }

    public function test_execute_method_exists_and_is_protected(): void
    {
        $reflection = new \ReflectionClass($this->command);
        
        $this->assertTrue($reflection->hasMethod('execute'));
        $this->assertTrue($reflection->getMethod('execute')->isProtected());
    }

    public function test_configure_method_exists_and_is_protected(): void
    {
        $reflection = new \ReflectionClass($this->command);
        
        $this->assertTrue($reflection->hasMethod('configure'));
        $this->assertTrue($reflection->getMethod('configure')->isProtected());
    }

    public function test_run_cleanup_method_exists_and_is_private(): void
    {
        $reflection = new \ReflectionClass($this->command);
        
        $this->assertTrue($reflection->hasMethod('runCleanup'));
        $this->assertTrue($reflection->getMethod('runCleanup')->isPrivate());
    }

    public function test_run_cleanup_method_with_success(): void
    {
        $this->deliveryService->expects($this->once())
            ->method('cleanupDeliveries')
            ->with(5)
            ->willReturn(10);
        
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with($this->stringContains('清理完成，共删除 10 条记录'));
        
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('runCleanup');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $output, 5);
    }

    public function test_run_cleanup_method_with_exception(): void
    {
        $exception = new \RuntimeException('Test error');
        $this->deliveryService->expects($this->once())
            ->method('cleanupDeliveries')
            ->with(3)
            ->willThrowException($exception);
        
        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with($this->stringContains('清理失败: Test error'));
        
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('runCleanup');
        $method->setAccessible(true);
        
        $method->invoke($this->command, $output, 3);
    }

    public function test_execute_method_signature(): void
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
        $this->assertSame('int', $returnType->getName());
    }

    public function test_command_class_structure(): void
    {
        $reflection = new \ReflectionClass($this->command);
        
        $this->assertSame('SocketIoBundle\Command\CleanupDeliveriesCommand', $reflection->getName());
        $this->assertTrue($reflection->isSubclassOf(Command::class));
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
    }
} 