<?php

namespace SocketIoBundle\Command;

use SocketIoBundle\Service\DeliveryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupDeliveriesCommand extends Command
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('socket:cleanup-deliveries')
            ->setDescription('清理过期的消息投递记录')
            ->addOption(
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

        if ($isDaemon) {
            $output->writeln(sprintf(
                '<info>清理守护进程已启动 (间隔: %d 秒, 保留 %d 天)</info>',
                $interval,
                $days
            ));

            while (true) {
                $this->runCleanup($output, $days);
                sleep($interval);
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
        } catch (\Exception $e) {
            $output->writeln(sprintf(
                '<error>[%s] 清理失败: %s</error>',
                date('Y-m-d H:i:s'),
                $e->getMessage()
            ));
        }
    }
}
