<?php

namespace SocketIoBundle\Service;

use SocketIoBundle\Repository\MessageRepository;
use SocketIoBundle\Repository\SocketRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class HeartbeatExecutionStrategy extends CommandExecutionStrategy
{
    public function __construct(
        private readonly SocketRepository $socketRepository,
        private readonly MessageRepository $messageRepository,
        private readonly SocketService $socketService,
        private readonly MessageService $messageService,
        private readonly DeliveryService $deliveryService,
        private readonly bool $isDaemon,
        private readonly int $interval,
    ) {
    }

    public function execute(OutputInterface $output): int
    {
        if ($this->isDaemon) {
            return $this->executeDaemonMode($output);
        }

        return $this->executeSingleRun($output);
    }

    private function executeDaemonMode(OutputInterface $output): int
    {
        $output->writeln(sprintf(
            '<info>心跳检查守护进程已启动 (间隔: %d ms)</info>',
            $this->interval
        ));

        $this->setupSignalHandlers();

        while (!$this->shouldStop) {
            $this->runHeartbeat($output);
            $this->waitWithGracefulStopMilliseconds($this->interval);
        }

        return Command::SUCCESS;
    }

    private function runHeartbeat(OutputInterface $output): void
    {
        try {
            $startTime = microtime(true);

            $expiredCount = $this->cleanupExpiredConnections($output);
            $deliveryCleanupCount = $this->deliveryService->cleanupDeliveries();
            $messageCleanupCount = $this->messageRepository->cleanupOldMessages();
            $this->deliveryService->cleanupQueues();
            $activeSockets = $this->sendHeartbeatEvents();

            $this->outputHeartbeatResult($output, $startTime, $expiredCount, $deliveryCleanupCount, $messageCleanupCount, $activeSockets);
        } catch (\Throwable $e) {
            $output->writeln(sprintf(
                '<error>[%s] 心跳检查失败: %s</error>',
                date('Y-m-d H:i:s'),
                $e->getMessage()
            ));
        }
    }

    private function cleanupExpiredConnections(OutputInterface $output): int
    {
        $sockets = $this->socketRepository->findActiveConnections();
        $expiredCount = 0;

        foreach ($sockets as $socket) {
            try {
                $this->socketService->checkActive($socket);
            } catch (\RuntimeException $e) {
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

        return $expiredCount;
    }

    private function sendHeartbeatEvents(): int
    {
        // 下发时间戳
        $this->messageService->broadcast('timestamp', ['timestamp' => time()]);

        // 下发随机数并返回活跃连接数
        return $this->messageService->broadcast('random', [
            'random1' => bin2hex(random_bytes(8)),
            'random2' => bin2hex(random_bytes(8)),
        ]);
    }

    private function outputHeartbeatResult(
        OutputInterface $output,
        float $startTime,
        int $expiredCount,
        int $deliveryCleanupCount,
        int $messageCleanupCount,
        int $activeSockets,
    ): void {
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
    }

    private function executeSingleRun(OutputInterface $output): int
    {
        $output->writeln('<info>执行单次心跳检查</info>');
        $this->runHeartbeat($output);

        return Command::SUCCESS;
    }
}
