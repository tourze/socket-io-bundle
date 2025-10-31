<?php

namespace SocketIoBundle\Tests\Protocol;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Enum\EnginePacketType;
use SocketIoBundle\Protocol\EnginePacket;

/**
 * @internal
 */
#[CoversClass(EnginePacket::class)]
final class EnginePacketTest extends TestCase
{
    public function testConstructorSetsTypeAndData(): void
    {
        $packet = new EnginePacket(EnginePacketType::MESSAGE, 'test data');

        $this->assertSame(EnginePacketType::MESSAGE, $packet->getType());
        $this->assertSame('test data', $packet->getData());
    }

    public function testConstructorWithNullData(): void
    {
        $packet = new EnginePacket(EnginePacketType::PING);

        $this->assertSame(EnginePacketType::PING, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function testEncodeWithData(): void
    {
        $packet = new EnginePacket(EnginePacketType::MESSAGE, 'hello');

        $encoded = $packet->encode();

        $this->assertSame('4hello', $encoded);
    }

    public function testEncodeWithoutData(): void
    {
        $packet = new EnginePacket(EnginePacketType::PING);

        $encoded = $packet->encode();

        $this->assertSame('2', $encoded);
    }

    public function testDecodeWithData(): void
    {
        $packet = EnginePacket::decode('4hello world');

        $this->assertSame(EnginePacketType::MESSAGE, $packet->getType());
        $this->assertSame('hello world', $packet->getData());
    }

    public function testDecodeWithoutData(): void
    {
        $packet = EnginePacket::decode('2');

        $this->assertSame(EnginePacketType::PING, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function testDecodeWithEmptyData(): void
    {
        $packet = EnginePacket::decode('3');

        $this->assertSame(EnginePacketType::PONG, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function testCreateOpenPacket(): void
    {
        $data = ['sid' => 'test-session-id', 'upgrades' => [], 'pingInterval' => 25000];
        $packet = EnginePacket::createOpen($data);

        $this->assertSame(EnginePacketType::OPEN, $packet->getType());
        $this->assertSame(json_encode($data), $packet->getData());
    }

    public function testCreateClosePacket(): void
    {
        $packet = EnginePacket::createClose();

        $this->assertSame(EnginePacketType::CLOSE, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function testCreatePingPacketWithoutData(): void
    {
        $packet = EnginePacket::createPing();

        $this->assertSame(EnginePacketType::PING, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function testCreatePingPacketWithData(): void
    {
        $packet = EnginePacket::createPing('probe');

        $this->assertSame(EnginePacketType::PING, $packet->getType());
        $this->assertSame('probe', $packet->getData());
    }

    public function testCreatePongPacketWithoutData(): void
    {
        $packet = EnginePacket::createPong();

        $this->assertSame(EnginePacketType::PONG, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function testCreatePongPacketWithData(): void
    {
        $packet = EnginePacket::createPong('probe');

        $this->assertSame(EnginePacketType::PONG, $packet->getType());
        $this->assertSame('probe', $packet->getData());
    }

    public function testCreateMessagePacket(): void
    {
        $packet = EnginePacket::createMessage('42["event","data"]');

        $this->assertSame(EnginePacketType::MESSAGE, $packet->getType());
        $this->assertSame('42["event","data"]', $packet->getData());
    }

    public function testCreateUpgradePacket(): void
    {
        $packet = EnginePacket::createUpgrade();

        $this->assertSame(EnginePacketType::UPGRADE, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function testCreateNoopPacket(): void
    {
        $packet = EnginePacket::createNoop();

        $this->assertSame(EnginePacketType::NOOP, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function testEncodeDecodeRoundtrip(): void
    {
        $originalPacket = new EnginePacket(EnginePacketType::MESSAGE, 'test message');
        $encoded = $originalPacket->encode();
        $decodedPacket = EnginePacket::decode($encoded);

        $this->assertEquals($originalPacket->getType(), $decodedPacket->getType());
        $this->assertEquals($originalPacket->getData(), $decodedPacket->getData());
    }

    public function testEncodeDecodeRoundtripWithoutData(): void
    {
        $originalPacket = new EnginePacket(EnginePacketType::PING);
        $encoded = $originalPacket->encode();
        $decodedPacket = EnginePacket::decode($encoded);

        $this->assertEquals($originalPacket->getType(), $decodedPacket->getType());
        $this->assertEquals($originalPacket->getData(), $decodedPacket->getData());
    }

    public function testDecodeAllPacketTypes(): void
    {
        $testCases = [
            ['0', EnginePacketType::OPEN],
            ['1', EnginePacketType::CLOSE],
            ['2', EnginePacketType::PING],
            ['3', EnginePacketType::PONG],
            ['4', EnginePacketType::MESSAGE],
            ['5', EnginePacketType::UPGRADE],
            ['6', EnginePacketType::NOOP],
        ];

        foreach ($testCases as [$encoded, $expectedType]) {
            $packet = EnginePacket::decode($encoded);
            $this->assertSame($expectedType, $packet->getType(), "Failed for packet type {$expectedType->value}");
        }
    }

    public function testEncodeAllPacketTypes(): void
    {
        $testCases = [
            [EnginePacketType::OPEN, '0'],
            [EnginePacketType::CLOSE, '1'],
            [EnginePacketType::PING, '2'],
            [EnginePacketType::PONG, '3'],
            [EnginePacketType::MESSAGE, '4'],
            [EnginePacketType::UPGRADE, '5'],
            [EnginePacketType::NOOP, '6'],
        ];

        foreach ($testCases as [$type, $expectedEncoded]) {
            $packet = new EnginePacket($type);
            $this->assertSame($expectedEncoded, $packet->encode(), "Failed for packet type {$type->value}");
        }
    }

    public function testDecodeWithComplexJsonData(): void
    {
        $jsonData = '{"type":"event","data":["message",{"user":"john","text":"hello"}]}';
        $packet = EnginePacket::decode('4' . $jsonData);

        $this->assertSame(EnginePacketType::MESSAGE, $packet->getType());
        $this->assertSame($jsonData, $packet->getData());
    }

    public function testCreateOpenWithComplexData(): void
    {
        $complexData = [
            'sid' => 'abc123',
            'upgrades' => ['websocket'],
            'pingInterval' => 25000,
            'pingTimeout' => 20000,
            'maxPayload' => 1000000,
        ];

        $packet = EnginePacket::createOpen($complexData);
        $encoded = $packet->encode();
        $decoded = EnginePacket::decode($encoded);

        $this->assertSame(EnginePacketType::OPEN, $decoded->getType());
        $this->assertSame(json_encode($complexData), $decoded->getData());
    }
}
