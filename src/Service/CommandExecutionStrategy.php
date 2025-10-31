<?php

namespace SocketIoBundle\Service;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
abstract class CommandExecutionStrategy
{
    protected bool $shouldStop = false;

    abstract public function execute(OutputInterface $output): int;

    protected function setupSignalHandlers(): void
    {
        if (\function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, fn () => $this->shouldStop = true);
            pcntl_signal(SIGINT, fn () => $this->shouldStop = true);
        }
    }

    protected function waitWithGracefulStop(int $interval): void
    {
        $elapsed = 0;
        while ($elapsed < $interval && !$this->shouldStop) {
            sleep(1);
            ++$elapsed;

            if (\function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }

    protected function waitWithGracefulStopMilliseconds(int $intervalMs): void
    {
        $elapsed = 0;
        while ($elapsed < ($intervalMs / 1000) && !$this->shouldStop) {
            usleep(100000); // 100ms
            $elapsed += 0.1;

            if (\function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }
        }
    }
}
