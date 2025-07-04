<?php

namespace SocketIoBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SocketIoBundle\Repository\MessageRepository;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\CreateTimeAware;

#[ORM\Entity(repositoryClass: MessageRepository::class)]
#[ORM\Table(name: 'ims_socket_io_message', options: ['comment' => '消息表'])]
#[ORM\Index(name: 'idx_message_event', columns: ['event'])]
class Message implements \Stringable
{
    use CreateTimeAware;
    use SnowflakeKeyAware;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '事件名称'])]
    private string $event;

    #[ORM\Column(type: Types::JSON, options: ['comment' => '消息数据'])]
    private array $data = [];

    #[ORM\ManyToOne(targetEntity: Socket::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?Socket $sender = null;

    #[ORM\ManyToMany(targetEntity: Room::class, inversedBy: 'messages')]
    #[ORM\JoinTable(name: 'socket_message_room')]
    private Collection $rooms;

    #[ORM\OneToMany(targetEntity: Delivery::class, mappedBy: 'message')]
    private Collection $deliveries;

    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '元数据'])]
    private ?array $metadata = null;

    public function __construct()
    {
        $this->rooms = new ArrayCollection();
        $this->deliveries = new ArrayCollection();
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

    public function __toString(): string
    {
        return sprintf('%s #%s', 'Message', $this->id ?? 'new');
    }
}
