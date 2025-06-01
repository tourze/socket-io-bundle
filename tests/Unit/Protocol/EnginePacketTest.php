<?php

namespace SocketIoBundle\Tests\Unit\Protocol;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Enum\EnginePacketType;
use SocketIoBundle\Protocol\EnginePacket;

class EnginePacketTest extends TestCase
{
    public function test_constructor_sets_type_and_data(): void
    {
        $packet = new EnginePacket(EnginePacketType::MESSAGE, 'test data');
        
        $this->assertSame(EnginePacketType::MESSAGE, $packet->getType());
        $this->assertSame('test data', $packet->getData());
    }

    public function test_constructor_with_null_data(): void
    {
        $packet = new EnginePacket(EnginePacketType::PING);
        
        $this->assertSame(EnginePacketType::PING, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function test_encode_with_data(): void
    {
        $packet = new EnginePacket(EnginePacketType::MESSAGE, 'hello');
        
        $encoded = $packet->encode();
        
        $this->assertSame('4hello', $encoded);
    }

    public function test_encode_without_data(): void
    {
        $packet = new EnginePacket(EnginePacketType::PING);
        
        $encoded = $packet->encode();
        
        $this->assertSame('2', $encoded);
    }

    public function test_decode_with_data(): void
    {
        $packet = EnginePacket::decode('4hello world');
        
        $this->assertSame(EnginePacketType::MESSAGE, $packet->getType());
        $this->assertSame('hello world', $packet->getData());
    }

    public function test_decode_without_data(): void
    {
        $packet = EnginePacket::decode('2');
        
        $this->assertSame(EnginePacketType::PING, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function test_decode_with_empty_data(): void
    {
        $packet = EnginePacket::decode('3');
        
        $this->assertSame(EnginePacketType::PONG, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function test_create_open_packet(): void
    {
        $data = ['sid' => 'test-session-id', 'upgrades' => [], 'pingInterval' => 25000];
        $packet = EnginePacket::createOpen($data);
        
        $this->assertSame(EnginePacketType::OPEN, $packet->getType());
        $this->assertSame(json_encode($data), $packet->getData());
    }

    public function test_create_close_packet(): void
    {
        $packet = EnginePacket::createClose();
        
        $this->assertSame(EnginePacketType::CLOSE, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function test_create_ping_packet_without_data(): void
    {
        $packet = EnginePacket::createPing();
        
        $this->assertSame(EnginePacketType::PING, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function test_create_ping_packet_with_data(): void
    {
        $packet = EnginePacket::createPing('probe');
        
        $this->assertSame(EnginePacketType::PING, $packet->getType());
        $this->assertSame('probe', $packet->getData());
    }

    public function test_create_pong_packet_without_data(): void
    {
        $packet = EnginePacket::createPong();
        
        $this->assertSame(EnginePacketType::PONG, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function test_create_pong_packet_with_data(): void
    {
        $packet = EnginePacket::createPong('probe');
        
        $this->assertSame(EnginePacketType::PONG, $packet->getType());
        $this->assertSame('probe', $packet->getData());
    }

    public function test_create_message_packet(): void
    {
        $packet = EnginePacket::createMessage('42["event","data"]');
        
        $this->assertSame(EnginePacketType::MESSAGE, $packet->getType());
        $this->assertSame('42["event","data"]', $packet->getData());
    }

    public function test_create_upgrade_packet(): void
    {
        $packet = EnginePacket::createUpgrade();
        
        $this->assertSame(EnginePacketType::UPGRADE, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function test_create_noop_packet(): void
    {
        $packet = EnginePacket::createNoop();
        
        $this->assertSame(EnginePacketType::NOOP, $packet->getType());
        $this->assertNull($packet->getData());
    }

    public function test_encode_decode_roundtrip(): void
    {
        $originalPacket = new EnginePacket(EnginePacketType::MESSAGE, 'test message');
        $encoded = $originalPacket->encode();
        $decodedPacket = EnginePacket::decode($encoded);
        
        $this->assertEquals($originalPacket->getType(), $decodedPacket->getType());
        $this->assertEquals($originalPacket->getData(), $decodedPacket->getData());
    }

    public function test_encode_decode_roundtrip_without_data(): void
    {
        $originalPacket = new EnginePacket(EnginePacketType::PING);
        $encoded = $originalPacket->encode();
        $decodedPacket = EnginePacket::decode($encoded);
        
        $this->assertEquals($originalPacket->getType(), $decodedPacket->getType());
        $this->assertEquals($originalPacket->getData(), $decodedPacket->getData());
    }

    public function test_decode_all_packet_types(): void
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

    public function test_encode_all_packet_types(): void
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

    public function test_decode_with_complex_json_data(): void
    {
        $jsonData = '{"type":"event","data":["message",{"user":"john","text":"hello"}]}';
        $packet = EnginePacket::decode('4' . $jsonData);
        
        $this->assertSame(EnginePacketType::MESSAGE, $packet->getType());
        $this->assertSame($jsonData, $packet->getData());
    }

    public function test_create_open_with_complex_data(): void
    {
        $complexData = [
            'sid' => 'abc123',
            'upgrades' => ['websocket'],
            'pingInterval' => 25000,
            'pingTimeout' => 20000,
            'maxPayload' => 1000000
        ];
        
        $packet = EnginePacket::createOpen($complexData);
        $encoded = $packet->encode();
        $decoded = EnginePacket::decode($encoded);
        
        $this->assertSame(EnginePacketType::OPEN, $decoded->getType());
        $this->assertSame(json_encode($complexData), $decoded->getData());
    }
} 