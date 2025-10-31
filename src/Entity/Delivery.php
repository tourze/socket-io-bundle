<?php

namespace SocketIoBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use SocketIoBundle\Enum\MessageStatus;
use SocketIoBundle\Repository\DeliveryRepository;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineIndexedBundle\Attribute\IndexColumn;
use Tourze\DoctrineSnowflakeBundle\Traits\SnowflakeKeyAware;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;

#[ORM\Entity(repositoryClass: DeliveryRepository::class)]
#[ORM\Table(name: 'ims_socket_io_delivery', options: ['comment' => 'Socket.IO消息投递记录表'])]
class Delivery implements \Stringable
{
    use TimestampableAware;
    use SnowflakeKeyAware;

    #[ORM\ManyToOne(targetEntity: Socket::class, inversedBy: 'deliveries')]
    #[ORM\JoinColumn(name: 'socket_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Socket $socket;

    #[ORM\ManyToOne(targetEntity: Message::class, inversedBy: 'deliveries')]
    #[ORM\JoinColumn(name: 'message_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Message $message;

    #[ORM\Column(type: Types::INTEGER, enumType: MessageStatus::class, options: ['comment' => '投递状态'])]
    #[Assert\Choice(callback: [MessageStatus::class, 'cases'])]
    #[IndexColumn]
    private MessageStatus $status = MessageStatus::PENDING;

    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '错误信息'])]
    #[Assert\Length(max: 65535)]
    private ?string $error = null;

    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '重试次数'])]
    #[Assert\Range(min: 0, max: 100)]
    private int $retries = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '投递时间'])]
    #[Assert\Type(type: \DateTimeImmutable::class)]
    private ?\DateTimeImmutable $deliveredAt = null;

    public function getSocket(): Socket
    {
        return $this->socket;
    }

    public function setSocket(Socket $socket): void
    {
        $this->socket = $socket;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }

    public function setMessage(Message $message): void
    {
        $this->message = $message;
    }

    public function getStatus(): MessageStatus
    {
        return $this->status;
    }

    public function setStatus(MessageStatus $status): void
    {
        $this->status = $status;
        if (MessageStatus::DELIVERED === $status) {
            $this->deliveredAt = new \DateTimeImmutable();
        }
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function setError(?string $error): void
    {
        $this->error = $error;
    }

    public function getRetries(): int
    {
        return $this->retries;
    }

    public function incrementRetries(): void
    {
        ++$this->retries;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
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

    public function __toString(): string
    {
        return sprintf('Delivery[%s:%s]', $this->id ?? 'new', $this->status->value);
    }
}
