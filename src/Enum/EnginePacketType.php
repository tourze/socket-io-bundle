<?php

namespace SocketIoBundle\Enum;

enum EnginePacketType: int
{
    case OPEN = 0;     // non-ws
    case CLOSE = 1;    // non-ws
    case PING = 2;     // non-ws
    case PONG = 3;     // non-ws
    case MESSAGE = 4;  // non-ws
    case UPGRADE = 5;  // non-ws
    case NOOP = 6;     // non-ws

    public function label(): string
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
}
