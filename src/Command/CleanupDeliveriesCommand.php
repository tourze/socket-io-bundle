<?php

namespace SocketIoBundle\Command;

use SocketIoBundle\Service\DeliveryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: self::NAME,
    description: '清理过期的消息投递记录'
)]
class CleanupDeliveriesCommand extends Command
{
    public const NAME = 'socket:cleanup-deliveries';
    private bool $shouldStop = false;
    
    public function __construct(
        private readonly DeliveryService $deliveryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
                'days',
                'd',
                InputOption::VALUE_OPTIONAL,
                '保留天数',
                7
            )
            ->addOption(
                'daemon',
                null,
                InputOption::VALUE_NONE,
                '以守护进程模式运行'
            )
            ->addOption(
                'interval',
                'i',
                InputOption::VALUE_OPTIONAL,
                '清理间隔（秒）',
                3600
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = (int) $input->getOption('days');
        $isDaemon = $input->getOption('daemon');
        $interval = (int) $input->getOption('interval');

        if ((bool) $isDaemon) {
            $output->writeln(sprintf(
                '<info>清理守护进程已启动 (间隔: %d 秒, 保留 %d 天)</info>',
                $interval,
                $days
            ));

            // 添加信号处理器以支持优雅停止
            if (\function_exists('pcntl_signal')) {
                pcntl_signal(SIGTERM, fn() => $this->shouldStop = true);
                pcntl_signal(SIGINT, fn() => $this->shouldStop = true);
            }
            
            while (!$this->shouldStop) {
                $this->runCleanup($output, $days);
                
                // 使用更短的睡眠间隔以便能更快地响应停止信号
                $elapsed = 0;
                while ($elapsed < $interval && !$this->shouldStop) {
                    sleep(1);
                    $elapsed++;
                    
                    if (\function_exists('pcntl_signal_dispatch')) {
                        pcntl_signal_dispatch();
                    }
                }
            }
        } else {
            $output->writeln(sprintf('<info>开始清理 %d 天前的消息投递记录</info>', $days));
            $this->runCleanup($output, $days);
        }

        return Command::SUCCESS;
    }

    private function runCleanup(OutputInterface $output, int $days): void
    {
        try {
            $startTime = microtime(true);

            // 执行清理
            $count = $this->deliveryService->cleanupDeliveries($days);

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
}
