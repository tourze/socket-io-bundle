<?php

namespace SocketIoBundle\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Command\SocketHeartbeatCommand;
use SocketIoBundle\Entity\Socket;
use SocketIoBundle\Exception\StatusException;
use SocketIoBundle\Repository\MessageRepository;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Service\MessageService;
use SocketIoBundle\Service\SocketService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SocketHeartbeatCommandTest extends TestCase
{
    private SocketHeartbeatCommand $command;
    private SocketRepository $socketRepository;
    private MessageRepository $messageRepository;
    private SocketService $socketService;
    private MessageService $messageService;
    private DeliveryService $deliveryService;

    protected function setUp(): void
    {
        $this->socketRepository = $this->createMock(SocketRepository::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->socketService = $this->createMock(SocketService::class);
        $this->messageService = $this->createMock(MessageService::class);
        $this->deliveryService = $this->createMock(DeliveryService::class);
        
        $this->command = new SocketHeartbeatCommand(
            $this->socketRepository,
            $this->messageRepository,
            $this->socketService,
            $this->messageService,
            $this->deliveryService
        );
    }

    public function test_extends_command(): void
    {
        $this->assertInstanceOf(Command::class, $this->command);
    }

    public function test_constructor_sets_dependencies(): void
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

    public function test_command_configuration(): void
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

    public function test_execute_normal_mode_success(): void
    {
        $this->setupMocksForSuccessfulHeartbeat();
        
        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);
        
        $this->assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('执行单次心跳检查', $output);
        $this->assertStringContainsString('心跳检查完成', $output);
    }

    public function test_execute_normal_mode_with_expired_sockets(): void
    {
        $socket1 = $this->createMock(Socket::class);
        $socket2 = $this->createMock(Socket::class);
        
        $socket1->expects($this->once())->method('getSessionId')->willReturn('session-1');
        // socket2不会被调用getSessionId，因为它不会抛出异常
        
        $this->socketRepository->expects($this->once())
            ->method('findActiveConnections')
            ->willReturn([$socket1, $socket2]);
        
        // socket1 过期，socket2 正常
        $this->socketService->expects($this->exactly(2))
            ->method('checkActive')
            ->willReturnCallback(function ($socket) use ($socket1) {
                if ($socket === $socket1) {
                    throw new StatusException('Socket expired');
                }
            });
        
        $this->socketService->expects($this->once())
            ->method('disconnect')
            ->with($socket1);
        
        $this->deliveryService->expects($this->once())
            ->method('cleanupDeliveries')
            ->willReturn(5);
        
        $this->messageRepository->expects($this->once())
            ->method('cleanupOldMessages')
            ->willReturn(10);
        
        $this->deliveryService->expects($this->once())
            ->method('cleanupQueues');
        
        $this->messageService->expects($this->exactly(2))
            ->method('broadcast')
            ->willReturn(3);
        
        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);
        
        $this->assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('session-1: SocketIoBundle\Exception\StatusException Socket expired', $output);
        $this->assertStringContainsString('断开过期连接: 1', $output);
        $this->assertStringContainsString('清理过期投递记录: 5', $output);
        $this->assertStringContainsString('清理过期消息: 10', $output);
        $this->assertStringContainsString('发送alive事件到活跃连接: 3', $output);
    }

    public function test_execute_with_exception(): void
    {
        $this->socketRepository->expects($this->once())
            ->method('findActiveConnections')
            ->willThrowException(new \RuntimeException('Database error'));
        
        $commandTester = new CommandTester($this->command);
        $exitCode = $commandTester->execute([]);
        
        $this->assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('执行单次心跳检查', $output);
        $this->assertStringContainsString('心跳检查失败: Database error', $output);
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

    public function test_run_heartbeat_method_exists_and_is_private(): void
    {
        $reflection = new \ReflectionClass($this->command);
        
        $this->assertTrue($reflection->hasMethod('runHeartbeat'));
        $this->assertTrue($reflection->getMethod('runHeartbeat')->isPrivate());
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
        
        $this->assertSame('SocketIoBundle\Command\SocketHeartbeatCommand', $reflection->getName());
        $this->assertTrue($reflection->isSubclassOf(Command::class));
        $this->assertFalse($reflection->isAbstract());
        $this->assertFalse($reflection->isFinal());
    }

    private function setupMocksForSuccessfulHeartbeat(): void
    {
        $this->socketRepository->expects($this->once())
            ->method('findActiveConnections')
            ->willReturn([]);
        
        $this->deliveryService->expects($this->once())
            ->method('cleanupDeliveries')
            ->willReturn(0);
        
        $this->messageRepository->expects($this->once())
            ->method('cleanupOldMessages')
            ->willReturn(0);
        
        $this->deliveryService->expects($this->once())
            ->method('cleanupQueues');
        
        $this->messageService->expects($this->exactly(2))
            ->method('broadcast')
            ->willReturn(0);
    }
} 