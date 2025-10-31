<?php

namespace SocketIoBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum SocketPacketType: int implements Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case CONNECT = 0;
    case DISCONNECT = 1;
    case EVENT = 2;
    case ACK = 3;
    case ERROR = 4;
    case BINARY_EVENT = 5;
    case BINARY_ACK = 6;

    public function getLabel(): string
    {
        return match ($this) {
            self::CONNECT => 'Connect',
            self::DISCONNECT => 'Disconnect',
            self::EVENT => 'Event',
            self::ACK => 'Acknowledgement',
            self::ERROR => 'Error',
            self::BINARY_EVENT => 'Binary Event',
            self::BINARY_ACK => 'Binary Acknowledgement',
        };
    }

    /**
     * @deprecated Use getLabel() instead
     */
    public function label(): string
    {
        return $this->getLabel();
    }

    public function isBinary(): bool
    {
        return self::BINARY_EVENT === $this || self::BINARY_ACK === $this;
    }
}
