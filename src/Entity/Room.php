<?php

namespace SocketIoBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SocketIoBundle\Repository\RoomRepository;
use Tourze\DoctrineSnowflakeBundle\Service\SnowflakeIdGenerator;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: RoomRepository::class)]
#[ORM\Table(name: 'ims_socket_io_room')]
#[ORM\Index(name: 'idx_room_name', columns: ['name'])]
class Room
{
    use TimestampableAware;
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(SnowflakeIdGenerator::class)]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['comment' => 'ID'])]
    private ?string $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255, nullable: false)]
    private string $namespace = '/';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\ManyToMany(targetEntity: Socket::class, mappedBy: 'rooms')]
    private Collection $sockets;

    #[ORM\ManyToMany(targetEntity: Message::class, mappedBy: 'rooms')]
    private Collection $messages;

    public function __construct(string $name = '', string $namespace = '/')
    {
        $this->sockets = new ArrayCollection();
        $this->messages = new ArrayCollection();
        $this->name = $name;
        $this->namespace = $namespace;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
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

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * @return Collection<Socket>
     */
    public function getSockets(): Collection
    {
        return $this->sockets;
    }

    public function addSocket(Socket $socket): self
    {
        if (!$this->sockets->contains($socket)) {
            $this->sockets->add($socket);
        }

        return $this;
    }

    public function removeSocket(Socket $socket): self
    {
        if ($this->sockets->contains($socket)) {
            $this->sockets->removeElement($socket);
        }

        return $this;
    }

    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->addRoom($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): self
    {
        if ($this->messages->contains($message)) {
            $this->messages->removeElement($message);
            $message->removeRoom($this);
        }

        return $this;
    }}
