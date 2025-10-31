<?php

namespace SocketIoBundle\Tests\Service;

use SocketIoBundle\Service\CommandExecutionStrategy;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
class ConcreteExecutionStrategy extends CommandExecutionStrategy
{
    public function execute(OutputInterface $output): int
    {
        return 0;
    }

    public function getShouldStop(): bool
    {
        return $this->shouldStop;
    }

    public function setShouldStop(bool $shouldStop): void
    {
        $this->shouldStop = $shouldStop;
    }

    public function callSetupSignalHandlers(): void
    {
        $this->setupSignalHandlers();
    }
}
