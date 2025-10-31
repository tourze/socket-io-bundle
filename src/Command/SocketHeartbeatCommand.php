<?php

namespace SocketIoBundle\Command;

use SocketIoBundle\Repository\MessageRepository;
use SocketIoBundle\Repository\SocketRepository;
use SocketIoBundle\Service\DeliveryService;
use SocketIoBundle\Service\HeartbeatExecutionStrategy;
use SocketIoBundle\Service\MessageService;
use SocketIoBundle\Service\SocketService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

#[AsCommand(
    name: self::NAME,
    description: '执行Socket.IO心跳检查和资源清理'
)]
#[Autoconfigure(public: true)]
class SocketHeartbeatCommand extends Command
{
    public const NAME = 'socket-io:heartbeat';

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
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $isDaemon = (bool) $input->getOption('daemon');

        $intervalOption = $input->getOption('interval');
        $interval = is_numeric($intervalOption) ? (int) $intervalOption : 25000;

        $strategy = new HeartbeatExecutionStrategy(
            $this->socketRepository,
            $this->messageRepository,
            $this->socketService,
            $this->messageService,
            $this->deliveryService,
            $isDaemon,
            $interval
        );

        return $strategy->execute($output);
    }
}
