<?php

namespace SocketIoBundle\Command;

use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use SocketIoBundle\DataFixtures\DeliveryFixtures;
use SocketIoBundle\DataFixtures\MessageFixtures;
use SocketIoBundle\DataFixtures\RoomFixtures;
use SocketIoBundle\DataFixtures\SocketFixtures;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'socket-io:load-fixtures',
    description: '加载Socket.IO演示数据',
)]
class LoadFixturesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('purge', 'p', InputOption::VALUE_NONE, '清空原有数据')
            ->setHelp('此命令将加载SocketIO的演示数据，方便在后台查看和测试');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('SocketIO数据加载');

        $loader = new Loader();
        
        // 加载Fixture类
        $loader->addFixture(new SocketFixtures());
        $loader->addFixture(new RoomFixtures());
        $loader->addFixture(new MessageFixtures());
        $loader->addFixture(new DeliveryFixtures());
        
        $purger = new ORMPurger($this->entityManager);
        $executor = new ORMExecutor($this->entityManager, $purger);
        
        // 是否清空原有数据
        $purge = $input->getOption('purge');
        if ($purge) {
            $io->warning('即将清空所有Socket.IO数据，并重新加载示例数据');
            if (!$io->confirm('确定要继续吗?', false)) {
                $io->error('操作已取消');
                return Command::FAILURE;
            }
            $purgeMode = ORMPurger::PURGE_MODE_TRUNCATE;
        } else {
            $purgeMode = ORMPurger::PURGE_MODE_DELETE;
            $io->note('数据将被追加，原有数据不会被清除');
        }
        
        $purger->setPurgeMode($purgeMode);
        
        $io->section('开始加载数据...');
        
        try {
            $executor->execute($loader->getFixtures(), $input->getOption('purge'));
            $io->success('数据加载完成!');
            $io->text([
                sprintf('- 已加载 %d 个Socket连接', SocketFixtures::SOCKET_COUNT),
                sprintf('- 已加载 %d 个房间', RoomFixtures::ROOM_COUNT + 5),
                sprintf('- 已加载 %d 条消息', MessageFixtures::MESSAGE_COUNT + 10),
                '- 已加载对应的投递记录'
            ]);
            $io->newLine();
            $io->text('现在可以通过后台查看这些数据了');
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('数据加载失败: ' . $e->getMessage());
            $io->text($e->getTraceAsString());
            
            return Command::FAILURE;
        }
    }
}
