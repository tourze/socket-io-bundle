<?php

namespace SocketIoBundle\Enum;

enum MessageStatus: int
{
    case PENDING = 0;
    case DELIVERED = 1;
    case FAILED = 2;

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::DELIVERED => 'Delivered',
            self::FAILED => 'Failed',
        };
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
