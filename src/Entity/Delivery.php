<?php

namespace SocketIoBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Repository\DeliveryRepository;
use Tourze\DoctrineSnowflakeBundle\Service\SnowflakeIdGenerator;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\EasyAdmin\Attribute\Column\ExportColumn;
use Tourze\EasyAdmin\Attribute\Column\ListColumn;

#[ORM\Entity(repositoryClass: DeliveryRepository::class)]
#[ORM\Table(name: 'ims_socket_io_delivery')]
#[ORM\Index(name: 'idx_delivery_status', columns: ['status'])]
class Delivery
{
    use TimestampableAware;
    #[ExportColumn]
    #[ListColumn(order: -1, sorter: true)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(SnowflakeIdGenerator::class)]
    #[ORM\Column(type: Types::BIGINT, nullable: false, options: ['comment' => 'ID'])]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Socket::class, inversedBy: 'deliveries')]
    #[ORM\JoinColumn(name: 'socket_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Socket $socket;

    #[ORM\ManyToOne(targetEntity: Message::class, inversedBy: 'deliveries')]
    #[ORM\JoinColumn(name: 'message_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Message $message;

    #[ORM\Column(type: 'integer', enumType: MessageStatus::class)]
    private MessageStatus $status = MessageStatus::PENDING;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: 'integer')]
    private int $retries = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $deliveredAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getSocket(): Socket
    {
        return $this->socket;
    }

    public function setSocket(Socket $socket): self
    {
        $this->socket = $socket;

        return $this;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function setMessage(Message $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getStatus(): MessageStatus
    {
        return $this->status;
    }

    public function setStatus(MessageStatus $status): self
    {
        $this->status = $status;
        if (MessageStatus::DELIVERED === $status) {
            $this->deliveredAt = new \DateTime();
        }

        return $this;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): self
    {
        $this->error = $error;

        return $this;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }

    public function incrementRetries(): self
    {
        ++$this->retries;

        return $this;
    }public function getDeliveredAt(): ?\DateTime
    {
        return $this->deliveredAt;
    }

    public function isDelivered(): bool
    {
        return MessageStatus::DELIVERED === $this->status;
    }

    public function isFailed(): bool
    {
        return MessageStatus::FAILED === $this->status;
    }

    public function isPending(): bool
    {
        return MessageStatus::PENDING === $this->status;
    }
}
