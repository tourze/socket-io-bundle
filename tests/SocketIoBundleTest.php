<?php

declare(strict_types=1);

namespace SocketIoBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\SocketIoBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(SocketIoBundle::class)]
#[RunTestsInSeparateProcesses]
final class SocketIoBundleTest extends AbstractBundleTestCase
{
}
