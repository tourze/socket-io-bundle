<?php

namespace SocketIoBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SocketIoBundle\Repository\RoomRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\Table(name: 'ims_socket_io_room', options: ['comment' => '房间表'])]
class Room implements \Stringable
{
    use TimestampableAware;
    use SnowflakeKeyAware;

    #[ORM\Column(type: Types::STRING, length: 255, options: ['comment' => '房间名称'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[IndexColumn]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: false, options: ['comment' => '命名空间'])]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private string $namespace = '/';

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true, options: ['comment' => '元数据'])]
    #[Assert\Type(type: 'array')]
    private ?array $metadata = null;

    /**
     * @var Collection<int, Socket>
     */
    #[ORM\ManyToMany(targetEntity: Socket::class, mappedBy: 'rooms')]
    private Collection $sockets;

    /**
     * @var Collection<int, Message>
     */
    #[ORM\ManyToMany(targetEntity: Message::class, mappedBy: 'rooms')]
    private Collection $messages;

    public function __construct()
    {
        $this->sockets = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function setNamespace(string $namespace): void
    {
        $this->namespace = $namespace;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed>|null $metadata
     */
    public function setMetadata(?array $metadata): void
    {
        $this->metadata = $metadata;
    }

    /**
     * @return Collection<int, Socket>
     */
    public function getSockets(): Collection
    {
        return $this->sockets;
    }

    public function addSocket(Socket $socket): void
    {
        if (!$this->sockets->contains($socket)) {
            $this->sockets->add($socket);
        }
    }

    public function removeSocket(Socket $socket): void
    {
        if ($this->sockets->contains($socket)) {
            $this->sockets->removeElement($socket);
        }
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): void
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->addRoom($this);
        }
    }

    public function removeMessage(Message $message): void
    {
        if ($this->messages->contains($message)) {
            $this->messages->removeElement($message);
            $message->removeRoom($this);
        }
    }

    public function __toString(): string
    {
        return sprintf('%s #%s', 'Room', $this->id ?? 'new');
    }
}
