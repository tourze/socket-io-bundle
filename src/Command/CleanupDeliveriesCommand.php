<?php

namespace SocketIoBundle\Command;

use SocketIoBundle\Service\CleanupExecutionStrategy;
use SocketIoBundle\Service\DeliveryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[AsCommand(
    name: self::NAME,
    description: '清理过期的消息投递记录'
)]
#[Autoconfigure(public: true)]
class CleanupDeliveriesCommand extends Command
{
    public const NAME = 'socket:cleanup-deliveries';

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
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $daysOption = $input->getOption('days');
        $days = is_numeric($daysOption) ? (int) $daysOption : 7;

        $isDaemon = (bool) $input->getOption('daemon');

        $intervalOption = $input->getOption('interval');
        $interval = is_numeric($intervalOption) ? (int) $intervalOption : 3600;

        $strategy = new CleanupExecutionStrategy(
            $this->deliveryService,
            $days,
            $isDaemon,
            $interval
        );

        return $strategy->execute($output);
    }
}
