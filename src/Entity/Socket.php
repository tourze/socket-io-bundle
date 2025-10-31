<?php

namespace SocketIoBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SocketIoBundle\Repository\SocketRepository;
use Symfony\Component\Validator\Constraints as Assert;
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
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private ?string $sessionId = null;

    #[IndexColumn]
    #[ORM\Column(type: Types::STRING, length: 64, unique: true, options: ['comment' => 'Socket ID'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    private ?string $socketId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false, options: ['comment' => '命名空间'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $namespace = '/';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true, options: ['comment' => '客户端 ID'])]
    #[Assert\Length(max: 255)]
    private ?string $clientId = null;

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '握手数据'])]
    #[Assert\Type(type: 'array')]
    private ?array $handshake = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后 Ping 时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $lastPingTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后消息时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $lastDeliverTime = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '最后活跃时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $lastActiveTime = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['comment' => '是否连接'])]
    #[Assert\Type(type: 'bool')]
    private bool $connected = true;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '轮询次数'])]
    #[Assert\Range(min: 0)]
    private int $pollCount = 0;

    #[ORM\Column(type: Types::STRING, length: 32, options: ['comment' => '传输方式'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 32)]
    #[Assert\Choice(choices: ['polling', 'websocket'])]
    private string $transport = 'polling';

    /**
     * @var Collection<int, Room>
     */
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

    /**
     * @var Collection<int, Delivery>
     */
    #[ORM\OneToMany(targetEntity: Delivery::class, mappedBy: 'socket')]
    private Collection $deliveries;

    public function __construct()
    {
        $this->rooms = new ArrayCollection();
        $this->deliveries = new ArrayCollection();
        $this->lastPingTime = new \DateTimeImmutable();
        $this->lastActiveTime = new \DateTimeImmutable();
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }

    public function getSocketId(): ?string
    {
        return $this->socketId;
    }

    public function setSocketId(string $socketId): void
    {
        $this->socketId = $socketId;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(?string $clientId): void
    {
        $this->clientId = $clientId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getHandshake(): ?array
    {
        return $this->handshake;
    }

    /**
     * @param array<string, mixed>|null $handshake
     */
    public function setHandshake(?array $handshake): void
    {
        $this->handshake = $handshake;
    }

    public function getLastPingTime(): ?\DateTimeImmutable
    {
        return $this->lastPingTime;
    }

    public function setLastPingTime(?\DateTimeImmutable $lastPingTime): void
    {
        $this->lastPingTime = $lastPingTime;
    }

    public function updatePingTime(): void
    {
        $this->lastPingTime = new \DateTimeImmutable();
        $this->updateLastActiveTime();
    }

    public function getLastActiveTime(): ?\DateTimeImmutable
    {
        return $this->lastActiveTime;
    }

    public function updateLastActiveTime(): void
    {
        $this->lastActiveTime = new \DateTimeImmutable();
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function setConnected(bool $connected): void
    {
        $this->connected = $connected;
    }

    public function getTransport(): string
    {
        return $this->transport;
    }

    public function setTransport(string $transport): void
    {
        $this->transport = $transport;
    }

    /**
     * @return Collection<int, Room>
     */
    public function getRooms(): Collection
    {
        return $this->rooms;
    }

    public function joinRoom(Room $room): void
    {
        if (!$this->rooms->contains($room)) {
            $this->rooms->add($room);
            $room->addSocket($this);
            $this->updateLastActiveTime();
        }
    }

    public function leaveRoom(Room $room): void
    {
        if ($this->rooms->contains($room)) {
            $this->rooms->removeElement($room);
            $room->removeSocket($this);
            $this->updateLastActiveTime();
        }
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

    public function leaveAllRooms(): void
    {
        foreach ($this->rooms as $room) {
            $this->leaveRoom($room);
        }
    }

    /**
     * @return Collection<int, Delivery>
     */
    public function getDeliveries(): Collection
    {
        return $this->deliveries;
    }

    public function addDelivery(Delivery $delivery): void
    {
        if (!$this->deliveries->contains($delivery)) {
            $this->deliveries->add($delivery);
            $delivery->setSocket($this);
        }
    }

    public function getPollCount(): int
    {
        return $this->pollCount;
    }

    public function incrementPollCount(): void
    {
        ++$this->pollCount;
    }

    public function resetPollCount(): void
    {
        $this->pollCount = 0;
    }

    public function getLastDeliverTime(): ?\DateTimeImmutable
    {
        return $this->lastDeliverTime;
    }

    public function setLastDeliverTime(?\DateTimeImmutable $lastDeliverTime): void
    {
        $this->lastDeliverTime = $lastDeliverTime;
    }

    public function updateDeliverTime(): void
    {
        $this->lastDeliverTime = new \DateTimeImmutable();
        $this->updateLastActiveTime();
    }

    public function __toString(): string
    {
        return sprintf('%s #%s', 'Socket', $this->id ?? 'new');
    }
}
