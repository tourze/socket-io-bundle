<?php

namespace SocketIoBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SocketIoBundle\Repository\MessageRepository;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Service\SnowflakeIdGenerator;
use Tourze\DoctrineTimestampBundle\Attribute\CreateTimeColumn;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'ims_socket_io_message')]
#[ORM\Index(name: 'idx_message_event', columns: ['event'])]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(SnowflakeIdGenerator::class)]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['comment' => 'ID'])]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $event;

    #[ORM\Column(type: 'json')]
    private array $data = [];

    #[ORM\ManyToOne(targetEntity: Socket::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Socket $sender = null;

    #[ORM\ManyToMany(targetEntity: Room::class, inversedBy: 'messages')]
    #[ORM\JoinTable(name: 'socket_message_room')]
    private Collection $rooms;

    #[ORM\OneToMany(targetEntity: Delivery::class, mappedBy: 'message')]
    private Collection $deliveries;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[IndexColumn]
    #[CreateTimeColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '创建时间'])]
    private ?\DateTimeInterface $createTime = null;

    public function __construct()
    {
        $this->rooms = new ArrayCollection();
        $this->deliveries = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setCreateTime(?\DateTimeInterface $createdAt): self
    {
        $this->createTime = $createdAt;

        return $this;
    }

    public function getCreateTime(): ?\DateTimeInterface
    {
        return $this->createTime;
    }

    public function getEvent(): string
    {
        return $this->event;
    }

    public function setEvent(string $event): self
    {
        $this->event = $event;

        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function getSender(): ?Socket
    {
        return $this->sender;
    }

    public function setSender(?Socket $sender): self
    {
        $this->sender = $sender;

        return $this;
    }

    /**
     * @return Collection<Room>
     */
    public function getRooms(): Collection
    {
        return $this->rooms;
    }

    public function addRoom(Room $room): self
    {
        if (!$this->rooms->contains($room)) {
            $this->rooms->add($room);
            $room->addMessage($this);
        }

        return $this;
    }

    public function removeRoom(Room $room): self
    {
        if ($this->rooms->contains($room)) {
            $this->rooms->removeElement($room);
            $room->removeMessage($this);
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
            $delivery->setMessage($this);
        }

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }
}
