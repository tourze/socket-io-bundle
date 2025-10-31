<?php

namespace SocketIoBundle\Tests\Service;

use Knp\Menu\ItemInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use SocketIoBundle\Service\AdminMenu;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;
use Tourze\PHPUnitSymfonyWebTest\AbstractEasyAdminMenuTestCase;

/**
 * @internal
 */
#[CoversClass(AdminMenu::class)]
#[RunTestsInSeparateProcesses]
final class AdminMenuTest extends AbstractEasyAdminMenuTestCase
{
    protected function onSetUp(): void
    {
    }

    private function getAdminMenu(): AdminMenu
    {
        return self::getService(AdminMenu::class);
    }

    public function testClassExists(): void
    {
        $adminMenu = $this->getAdminMenu();
        $this->assertInstanceOf(AdminMenu::class, $adminMenu);
    }

    public function testImplementsMenuProviderInterface(): void
    {
        $adminMenu = $this->getAdminMenu();
        $this->assertInstanceOf(MenuProviderInterface::class, $adminMenu);
    }

    public function testInvokeCreatesMenuItems(): void
    {
        $adminMenu = $this->getAdminMenu();
        $item = $this->createMock(ItemInterface::class);
        $menuItem = $this->createMock(ItemInterface::class);

        // 配置 item mock 返回菜单项
        $item->expects($this->any())
            ->method('getChild')
            ->with('实时通信')
            ->willReturn($menuItem)
        ;

        // 配置子菜单项
        $childItem = $this->createMock(ItemInterface::class);

        $menuItem->expects($this->exactly(4))
            ->method('addChild')
            ->willReturn($childItem)
        ;

        // 执行测试
        $adminMenu($item);

        // Mock 对象的 expects() 自动验证调用次数，无需额外断言
    }
}
