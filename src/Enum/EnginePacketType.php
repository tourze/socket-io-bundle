<?php

namespace SocketIoBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum EnginePacketType: int implements Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case OPEN = 0;     // non-ws
    case CLOSE = 1;    // non-ws
    case PING = 2;     // non-ws
    case PONG = 3;     // non-ws
    case MESSAGE = 4;  // non-ws
    case UPGRADE = 5;  // non-ws
    case NOOP = 6;     // non-ws

    public function getLabel(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::CLOSE => 'Close',
            self::PING => 'Ping',
            self::PONG => 'Pong',
            self::MESSAGE => 'Message',
            self::UPGRADE => 'Upgrade',
            self::NOOP => 'Noop',
        };
    }

    /**
     * @deprecated Use getLabel() instead
     */
    public function label(): string
    {
        return $this->getLabel();
    }
}
