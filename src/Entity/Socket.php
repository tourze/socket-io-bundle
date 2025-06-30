<?php

namespace SocketIoBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SocketIoBundle\Repository\SocketRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: SocketRepository::class)]
#[ORM\Table(name: 'ims_socket_io_connection', options: ['comment' => 'Socket连接表'])]
class Socket implements \Stringable
{
    use TimestampableAware;
    use SnowflakeKeyAware;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 64, unique: true, options: ['comment' => '会话 ID'])]
    private string $sessionId;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 64, unique: true, options: ['comment' => 'Socket ID'])]
    private string $socketId;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false, options: ['comment' => '命名空间'])]
    private string $namespace = '/';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '客户端 ID'])]
    private ?string $clientId = null;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '握手数据'])]
    private ?array $handshake = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后 Ping 时间'])]
    private ?\DateTimeImmutable $lastPingTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后消息时间'])]
    private ?\DateTimeImmutable $lastDeliverTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后活跃时间'])]
    private ?\DateTimeImmutable $lastActiveTime = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否连接'])]
    private bool $connected = true;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '轮询次数'])]
    private int $pollCount = 0;

    #[ORM\Column(type: Types::STRING, length: 32, options: ['comment' => '传输方式'])]
    private string $transport = 'polling';

    #[ORM\ManyToMany(targetEntity: Room::class, inversedBy: 'sockets')]
    #[ORM\JoinTable(
        name: 'ims_socket_io_room_membership',
        joinColumns: [
            new ORM\JoinColumn(name: 'socket_id', referencedColumnName: 'id', onDelete: 'CASCADE'),
        ],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: 'room_id', referencedColumnName: 'id', onDelete: 'CASCADE'),
        ]
    )]
    private Collection $rooms;

    #[ORM\OneToMany(targetEntity: Delivery::class, mappedBy: 'socket')]
    private Collection $deliveries;

    public function __construct(string $sessionId, string $socketId)
    {
        $this->sessionId = $sessionId;
        $this->socketId = $socketId;
        $this->rooms = new ArrayCollection();
        $this->deliveries = new ArrayCollection();
        $this->lastPingTime = new \DateTimeImmutable();
        $this->lastActiveTime = new \DateTimeImmutable();
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function getSocketId(): string
    {
        return $this->socketId;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(?string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getHandshake(): ?array
    {
        return $this->handshake;
    }

    public function setHandshake(?array $handshake): self
    {
        $this->handshake = $handshake;

        return $this;
    }

    public function getLastPingTime(): ?\DateTimeImmutable
    {
        return $this->lastPingTime;
    }

    public function setLastPingTime(?\DateTimeImmutable $lastPingTime): self
    {
        $this->lastPingTime = $lastPingTime;

        return $this;
    }

    public function updatePingTime(): self
    {
        $this->lastPingTime = new \DateTimeImmutable();
        $this->updateLastActiveTime();

        return $this;
    }

    public function getLastActiveTime(): ?\DateTimeImmutable
    {
        return $this->lastActiveTime;
    }

    public function updateLastActiveTime(): self
    {
        $this->lastActiveTime = new \DateTimeImmutable();

        return $this;
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function setConnected(bool $connected): self
    {
        $this->connected = $connected;

        return $this;
    }

    public function getTransport(): string
    {
        return $this->transport;
    }

    public function setTransport(string $transport): self
    {
        $this->transport = $transport;

        return $this;
    }

    public function getRooms(): Collection
    {
        return $this->rooms;
    }

    public function joinRoom(Room $room): self
    {
        if (!$this->rooms->contains($room)) {
            $this->rooms->add($room);
            $room->addSocket($this);
            $this->updateLastActiveTime();
        }

        return $this;
    }

    public function leaveRoom(Room $room): self
    {
        if ($this->rooms->contains($room)) {
            $this->rooms->removeElement($room);
            $room->removeSocket($this);
            $this->updateLastActiveTime();
        }

        return $this;
    }

    public function isInRoom(Room $room): bool
    {
        return $this->rooms->contains($room);
    }

    public function isInRoomByName(string $roomName, string $namespace = '/'): bool
    {
        return $this->rooms->exists(fn (int $key, Room $room) => $room->getName() === $roomName && $room->getNamespace() === $namespace
        );
    }

    public function leaveAllRooms(): self
    {
        foreach ($this->rooms as $room) {
            $this->leaveRoom($room);
        }

        return $this;
    }

    public function getDeliveries(): Collection
    {
        return $this->deliveries;
    }

    public function addDelivery(Delivery $delivery): self
    {
        if (!$this->deliveries->contains($delivery)) {
            $this->deliveries->add($delivery);
            $delivery->setSocket($this);
        }

        return $this;
    }

    public function getPollCount(): int
    {
        return $this->pollCount;
    }

    public function incrementPollCount(): self
    {
        ++$this->pollCount;

        return $this;
    }

    public function resetPollCount(): self
    {
        $this->pollCount = 0;

        return $this;
    }

    public function getLastDeliverTime(): ?\DateTimeImmutable
    {
        return $this->lastDeliverTime;
    }

    public function setLastDeliverTime(?\DateTimeImmutable $lastDeliverTime): self
    {
        $this->lastDeliverTime = $lastDeliverTime;

        return $this;
    }

    public function updateDeliverTime(): self
    {
        $this->lastDeliverTime = new \DateTimeImmutable();
        $this->updateLastActiveTime();

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s #%s', 'Socket', $this->id ?? 'new');
    }
}
