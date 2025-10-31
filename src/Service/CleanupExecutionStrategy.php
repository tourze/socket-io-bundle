<?php

namespace SocketIoBundle\Service;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupExecutionStrategy extends CommandExecutionStrategy
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
        private readonly int $days,
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
            '<info>清理守护进程已启动 (间隔: %d 秒, 保留 %d 天)</info>',
            $this->interval,
            $this->days
        ));

        $this->setupSignalHandlers();

        while (!$this->shouldStop) {
            $this->runCleanup($output);
            $this->waitWithGracefulStop($this->interval);
        }

        return Command::SUCCESS;
    }

    private function runCleanup(OutputInterface $output): void
    {
        try {
            $startTime = microtime(true);

            // 执行清理
            $count = $this->deliveryService->cleanupDeliveries($this->days);

            $duration = round((microtime(true) - $startTime) * 1000);
            $output->writeln(sprintf(
                '[%s] 清理完成，共删除 %d 条记录 (%d ms)',
                date('Y-m-d H:i:s'),
                $count,
                $duration
            ));
        } catch (\Throwable $e) {
            $output->writeln(sprintf(
                '<error>[%s] 清理失败: %s</error>',
                date('Y-m-d H:i:s'),
                $e->getMessage()
            ));
        }
    }

    private function executeSingleRun(OutputInterface $output): int
    {
        $output->writeln(sprintf('<info>开始清理 %d 天前的消息投递记录</info>', $this->days));
        $this->runCleanup($output);

        return Command::SUCCESS;
    }
}
