<?php

namespace SocketIoBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Service\SnowflakeIdGenerator;
use Tourze\DoctrineTimestampBundle\Attribute\CreateTimeColumn;
use Tourze\DoctrineTimestampBundle\Attribute\UpdateTimeColumn;
use Tourze\EasyAdmin\Attribute\Column\ExportColumn;
use Tourze\EasyAdmin\Attribute\Column\ListColumn;
use Tourze\EasyAdmin\Attribute\Filter\Filterable;

#[ORM\Entity]
#[ORM\Table(name: 'ims_socket_io_connection')]
class Socket
{
    #[ExportColumn]
    #[ListColumn(order: -1, sorter: true)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(SnowflakeIdGenerator::class)]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['comment' => 'ID'])]
    private ?string $id = '0';

    #[IndexColumn]
    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $sessionId;

    #[IndexColumn]
    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $socketId;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $namespace = '/';

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $clientId = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $handshake = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $lastPingTime = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $lastDeliverTime = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $lastActiveTime = null;

    #[ORM\Column(type: 'boolean')]
    private bool $connected = true;

    #[ORM\Column(type: 'integer')]
    private int $pollCount = 0;

    #[ORM\Column(type: 'string', length: 32)]
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

    #[Filterable]
    #[IndexColumn]
    #[ListColumn(order: 98, sorter: true)]
    #[ExportColumn]
    #[CreateTimeColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '创建时间'])]
    private ?\DateTimeInterface $createTime = null;

    #[UpdateTimeColumn]
    #[ListColumn(order: 99, sorter: true)]
    #[Filterable]
    #[ExportColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '更新时间'])]
    private ?\DateTimeInterface $updateTime = null;

    public function __construct(string $sessionId, string $socketId)
    {
        $this->sessionId = $sessionId;
        $this->socketId = $socketId;
        $this->rooms = new ArrayCollection();
        $this->deliveries = new ArrayCollection();
        $this->lastPingTime = new \DateTime();
        $this->lastActiveTime = new \DateTime();
    }

    public function getId(): ?string
    {
        return $this->id;
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

    public function getLastPingTime(): ?\DateTime
    {
        return $this->lastPingTime;
    }

    public function setLastPingTime(?\DateTime $lastPingTime): self
    {
        $this->lastPingTime = $lastPingTime;

        return $this;
    }

    public function updatePingTime(): self
    {
        $this->lastPingTime = new \DateTime();
        $this->updateLastActiveTime();

        return $this;
    }

    public function getLastActiveTime(): ?\DateTime
    {
        return $this->lastActiveTime;
    }

    public function updateLastActiveTime(): self
    {
        $this->lastActiveTime = new \DateTime();

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

    public function getLastDeliverTime(): ?\DateTime
    {
        return $this->lastDeliverTime;
    }

    public function setLastDeliverTime(?\DateTime $lastDeliverTime): self
    {
        $this->lastDeliverTime = $lastDeliverTime;

        return $this;
    }

    public function updateDeliverTime(): self
    {
        $this->lastDeliverTime = new \DateTime();
        $this->updateLastActiveTime();

        return $this;
    }

    public function setCreateTime(?\DateTimeInterface $createdAt): void
    {
        $this->createTime = $createdAt;
    }

    public function getCreateTime(): ?\DateTimeInterface
    {
        return $this->createTime;
    }

    public function setUpdateTime(?\DateTimeInterface $updateTime): void
    {
        $this->updateTime = $updateTime;
    }

    public function getUpdateTime(): ?\DateTimeInterface
    {
        return $this->updateTime;
    }
}
