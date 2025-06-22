<?php

namespace SocketIoBundle\Command;

use SocketIoBundle\Exception\StatusException;
use SocketIoBundle\Repository\MessageRepository;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Service\MessageService;
use SocketIoBundle\Service\SocketService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: '执行Socket.IO心跳检查和资源清理'
)]
class SocketHeartbeatCommand extends Command
{
    public const NAME = 'socket-io:heartbeat';
    private bool $shouldStop = false;
    
    public function __construct(
        private readonly SocketRepository $socketRepository,
        private readonly MessageRepository $messageRepository,
        private readonly SocketService $socketService,
        private readonly MessageService $messageService,
        private readonly DeliveryService $deliveryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
                'daemon',
                'd',
                InputOption::VALUE_NONE,
                '以守护进程模式运行'
            )
            ->addOption(
                'interval',
                'i',
                InputOption::VALUE_OPTIONAL,
                '心跳间隔（毫秒）',
                25000
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDaemon = $input->getOption('daemon');
        $interval = (int) $input->getOption('interval');

        if ((bool) $isDaemon) {
            $output->writeln(sprintf(
                '<info>心跳检查守护进程已启动 (间隔: %d ms)</info>',
                $interval
            ));

            // 添加信号处理器以支持优雅停止
            if (\function_exists('pcntl_signal')) {
                pcntl_signal(SIGTERM, fn() => $this->shouldStop = true);
                pcntl_signal(SIGINT, fn() => $this->shouldStop = true);
            }
            
            while (!$this->shouldStop) {
                $this->runHeartbeat($output);
                
                // 使用更短的睡眠间隔以便能更快地响应停止信号
                $elapsed = 0;
                while ($elapsed < ($interval / 1000) && !$this->shouldStop) {
                    usleep(100000); // 100ms
                    $elapsed += 0.1;
                    
                    if (\function_exists('pcntl_signal_dispatch')) {
                        pcntl_signal_dispatch();
                    }
                }
            }
        } else {
            $output->writeln('<info>执行单次心跳检查</info>');
            $this->runHeartbeat($output);
        }

        return Command::SUCCESS;
    }

    private function runHeartbeat(OutputInterface $output): void
    {
        try {
            $startTime = microtime(true);

            // 1. 清理过期的socket连接
            $sockets = $this->socketRepository->findActiveConnections();
            $expiredCount = 0;
            foreach ($sockets as $socket) {
                try {
                    $this->socketService->checkActive($socket);
                } catch (StatusException $e) {
                    $output->writeln(sprintf(
                        '<comment>%s: %s %s</comment>',
                        $socket->getSessionId(),
                        $e::class,
                        $e->getMessage(),
                    ));
                    $this->socketService->disconnect($socket);
                    ++$expiredCount;
                }
            }

            // 2. 清理过期的消息投递记录
            $deliveryCleanupCount = $this->deliveryService->cleanupDeliveries();

            // 3. 清理过期的消息记录
            $messageCleanupCount = $this->messageRepository->cleanupOldMessages();

            // 4. 清理消息队列中的过期消息
            $this->deliveryService->cleanupQueues();

            // 5. 为所有活跃连接发送测试事件

            // 下发时间戳
            $this->messageService->broadcast('timestamp', [
                time(),
            ]);

            // 下发随机数
            $activeSockets = $this->messageService->broadcast('random', [
                bin2hex(random_bytes(8)),
                bin2hex(random_bytes(8)),
            ]);

            $duration = round((microtime(true) - $startTime) * 1000);
            $output->writeln(sprintf(
                '[%s] 心跳检查完成 (%d ms):' . PHP_EOL .
                '- 断开过期连接: %d' . PHP_EOL .
                '- 清理过期投递记录: %d' . PHP_EOL .
                '- 清理过期消息: %d' . PHP_EOL .
                '- 发送alive事件到活跃连接: %d',
                date('Y-m-d H:i:s'),
                $duration,
                $expiredCount,
                $deliveryCleanupCount,
                $messageCleanupCount,
                $activeSockets,
            ));
        } catch (\Throwable $e) {
            $output->writeln(sprintf(
                '<error>[%s] 心跳检查失败: %s</error>',
                date('Y-m-d H:i:s'),
                $e->getMessage()
            ));
        }
    }
}
