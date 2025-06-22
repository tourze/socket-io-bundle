<?php

namespace SocketIoBundle\Enum;
use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum MessageStatus: int implements Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;
    case PENDING = 0;
    case DELIVERED = 1;
    case FAILED = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Failed',
        };
    }
    
    public function label(): string
    {
        return $this->getLabel();
    }

    public function isDelivered(): bool
    {
        return self::DELIVERED === $this;
    }

    public function isFailed(): bool
    {
        return self::FAILED === $this;
    }

    public function isPending(): bool
    {
        return self::PENDING === $this;
    }
}
