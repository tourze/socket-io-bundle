<?php

namespace SocketIoBundle\Service;

use Knp\Menu\ItemInterface;
use SocketIoBundle\Entity\Delivery;
use SocketIoBundle\Entity\Message;
use SocketIoBundle\Entity\Room;
use SocketIoBundle\Entity\Socket;
use Tourze\EasyAdminMenuBundle\Service\LinkGeneratorInterface;
use Tourze\EasyAdminMenuBundle\Service\MenuProviderInterface;

class AdminMenu implements MenuProviderInterface
{
    public function __construct(private readonly LinkGeneratorInterface $linkGenerator)
    {
    }

    public function __invoke(ItemInterface $item): void
    {
        if ($item->getChild('实时通信') === null) {
            $item->addChild('实时通信');
        }

        $menuItem = $item->getChild('实时通信');
        
        $menuItem
            ->addChild('Socket连接')
            ->setUri($this->linkGenerator->getCurdListPage(Socket::class))
            ->setAttribute('icon', 'fas fa-plug');
            
        $menuItem
            ->addChild('房间管理')
            ->setUri($this->linkGenerator->getCurdListPage(Room::class))
            ->setAttribute('icon', 'fas fa-door-open');
            
        $menuItem
            ->addChild('消息管理')
            ->setUri($this->linkGenerator->getCurdListPage(Message::class))
            ->setAttribute('icon', 'fas fa-comments');
            
        $menuItem
            ->addChild('投递记录')
            ->setUri($this->linkGenerator->getCurdListPage(Delivery::class))
            ->setAttribute('icon', 'fas fa-paper-plane');
    }
} 