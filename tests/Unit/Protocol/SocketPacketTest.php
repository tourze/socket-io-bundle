<?php

namespace SocketIoBundle\Tests\Unit\Protocol;

use PHPUnit\Framework\TestCase;
use SocketIoBundle\Enum\SocketPacketType;
use SocketIoBundle\Protocol\SocketPacket;

class SocketPacketTest extends TestCase
{
    public function test_constructor_sets_all_properties(): void
    {
        $packet = new SocketPacket(
            SocketPacketType::EVENT,
            '/test',
            123,
            'test data',
            true
        );
        
        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertSame('/test', $packet->getNamespace());
        $this->assertSame(123, $packet->getId());
        $this->assertSame('test data', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function test_constructor_with_minimal_parameters(): void
    {
        $packet = new SocketPacket(SocketPacketType::CONNECT);
        
        $this->assertSame(SocketPacketType::CONNECT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertNull($packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function test_encode_basic_packet(): void
    {
        $packet = new SocketPacket(SocketPacketType::CONNECT);
        
        $encoded = $packet->encode();
        
        $this->assertSame('0', $encoded);
    }

    public function test_encode_packet_with_data(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, null, null, '["test","data"]');
        
        $encoded = $packet->encode();
        
        $this->assertSame('2["test","data"]', $encoded);
    }

    public function test_encode_packet_with_namespace(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, '/admin', null, '["test"]');
        
        $encoded = $packet->encode();
        
        $this->assertSame('2/admin,["test"]', $encoded);
    }

    public function test_encode_packet_with_id(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, null, 25, '["test"]');
        
        $encoded = $packet->encode();
        
        $this->assertSame('225["test"]', $encoded);
    }

    public function test_encode_packet_with_namespace_and_id(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, '/admin', 25, '["test"]');
        
        $encoded = $packet->encode();
        
        $this->assertSame('2/admin,25["test"]', $encoded);
    }

    public function test_encode_binary_packet(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, null, null, '["test"]', true);
        
        $encoded = $packet->encode();
        
        $this->assertSame('5["test"]', $encoded);
    }

    public function test_encode_binary_ack_packet(): void
    {
        $packet = new SocketPacket(SocketPacketType::ACK, null, 15, '["response"]', true);
        
        $encoded = $packet->encode();
        
        $this->assertSame('615["response"]', $encoded);
    }

    public function test_encode_packet_with_root_namespace(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, '/', null, '["test"]');
        
        $encoded = $packet->encode();
        
        $this->assertSame('2["test"]', $encoded);
    }

    public function test_decode_basic_packet(): void
    {
        $packet = SocketPacket::decode('0');
        
        $this->assertSame(SocketPacketType::CONNECT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertNull($packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function test_decode_packet_with_data(): void
    {
        $packet = SocketPacket::decode('2["hello","world"]');
        
        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["hello","world"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function test_decode_packet_with_namespace(): void
    {
        $packet = SocketPacket::decode('2/admin,["test"]');
        
        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["test"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function test_decode_packet_with_id(): void
    {
        $packet = SocketPacket::decode('325["response"]');
        
        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertSame(25, $packet->getId());
        $this->assertSame('["response"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function test_decode_packet_with_namespace_and_id(): void
    {
        $packet = SocketPacket::decode('2/admin,25["test"]');
        
        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertSame(25, $packet->getId());
        $this->assertSame('["test"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function test_decode_binary_packet(): void
    {
        $packet = SocketPacket::decode('5["binary","data"]');
        
        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["binary","data"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function test_decode_binary_ack_packet(): void
    {
        $packet = SocketPacket::decode('615["ack","data"]');
        
        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertSame(15, $packet->getId());
        $this->assertSame('["ack","data"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function test_create_connect_packet_without_data(): void
    {
        $packet = SocketPacket::createConnect();
        
        $this->assertSame(SocketPacketType::CONNECT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertNull($packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function test_create_connect_packet_with_namespace(): void
    {
        $packet = SocketPacket::createConnect('/admin');
        
        $this->assertSame(SocketPacketType::CONNECT, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertNull($packet->getData());
    }

    public function test_create_connect_packet_with_data(): void
    {
        $data = ['auth' => 'token123'];
        $packet = SocketPacket::createConnect(null, $data);
        
        $this->assertSame(SocketPacketType::CONNECT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertSame(json_encode($data), $packet->getData());
    }

    public function test_create_disconnect_packet(): void
    {
        $packet = SocketPacket::createDisconnect();
        
        $this->assertSame(SocketPacketType::DISCONNECT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertNull($packet->getData());
    }

    public function test_create_disconnect_packet_with_namespace(): void
    {
        $packet = SocketPacket::createDisconnect('/admin');
        
        $this->assertSame(SocketPacketType::DISCONNECT, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
    }

    public function test_create_event_packet(): void
    {
        $packet = SocketPacket::createEvent('["message","hello"]');
        
        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["message","hello"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function test_create_event_packet_with_all_parameters(): void
    {
        $packet = SocketPacket::createEvent('["test"]', '/admin', 123, true);
        
        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertSame(123, $packet->getId());
        $this->assertSame('["test"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function test_create_ack_packet(): void
    {
        $packet = SocketPacket::createAck('["response"]');
        
        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["response"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function test_create_ack_packet_with_all_parameters(): void
    {
        $packet = SocketPacket::createAck('["ok"]', '/admin', 456, true);
        
        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertSame(456, $packet->getId());
        $this->assertSame('["ok"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function test_create_error_packet(): void
    {
        $packet = SocketPacket::createError('["error","message"]');
        
        $this->assertSame(SocketPacketType::ERROR, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["error","message"]', $packet->getData());
    }

    public function test_create_error_packet_with_namespace(): void
    {
        $packet = SocketPacket::createError('["error"]', '/admin');
        
        $this->assertSame(SocketPacketType::ERROR, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertSame('["error"]', $packet->getData());
    }

    public function test_create_binary_event_packet(): void
    {
        $packet = SocketPacket::createBinaryEvent('["binary","data"]');
        
        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["binary","data"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function test_create_binary_event_packet_with_all_parameters(): void
    {
        $packet = SocketPacket::createBinaryEvent('["data"]', '/files', 789);
        
        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertSame('/files', $packet->getNamespace());
        $this->assertSame(789, $packet->getId());
        $this->assertSame('["data"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function test_create_binary_ack_packet(): void
    {
        $packet = SocketPacket::createBinaryAck('["ack","binary"]');
        
        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["ack","binary"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function test_create_binary_ack_packet_with_all_parameters(): void
    {
        $packet = SocketPacket::createBinaryAck('["ok"]', '/uploads', 101);
        
        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertSame('/uploads', $packet->getNamespace());
        $this->assertSame(101, $packet->getId());
        $this->assertSame('["ok"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function test_encode_decode_roundtrip(): void
    {
        $originalPacket = new SocketPacket(
            SocketPacketType::EVENT,
            '/test',
            42,
            '["hello","world"]',
            false
        );
        
        $encoded = $originalPacket->encode();
        $decodedPacket = SocketPacket::decode($encoded);
        
        $this->assertEquals($originalPacket->getType(), $decodedPacket->getType());
        $this->assertEquals($originalPacket->getNamespace(), $decodedPacket->getNamespace());
        $this->assertEquals($originalPacket->getId(), $decodedPacket->getId());
        $this->assertEquals($originalPacket->getData(), $decodedPacket->getData());
        $this->assertEquals($originalPacket->isBinary(), $decodedPacket->isBinary());
    }

    public function test_encode_decode_roundtrip_binary(): void
    {
        $originalPacket = new SocketPacket(
            SocketPacketType::ACK,
            '/files',
            123,
            '["binary","response"]',
            true
        );
        
        $encoded = $originalPacket->encode();
        $decodedPacket = SocketPacket::decode($encoded);
        
        $this->assertEquals($originalPacket->getType(), $decodedPacket->getType());
        $this->assertEquals($originalPacket->getNamespace(), $decodedPacket->getNamespace());
        $this->assertEquals($originalPacket->getId(), $decodedPacket->getId());
        $this->assertEquals($originalPacket->getData(), $decodedPacket->getData());
        $this->assertEquals($originalPacket->isBinary(), $decodedPacket->isBinary());
    }

    public function test_decode_with_multi_digit_id(): void
    {
        $packet = SocketPacket::decode('2123456["test"]');
        
        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertSame(123456, $packet->getId());
        $this->assertSame('["test"]', $packet->getData());
    }

    public function test_decode_all_packet_types(): void
    {
        $testCases = [
            ['0', SocketPacketType::CONNECT],
            ['1', SocketPacketType::DISCONNECT],
            ['2', SocketPacketType::EVENT],
            ['3', SocketPacketType::ACK],
        ];
        
        foreach ($testCases as [$encoded, $expectedType]) {
            $packet = SocketPacket::decode($encoded);
            $this->assertSame($expectedType, $packet->getType(), "Failed for packet type {$expectedType->value}");
            $this->assertFalse($packet->isBinary(), "Regular packet should not be binary");
        }
    }

    public function test_decode_all_binary_packet_types(): void
    {
        $testCases = [
            ['4', SocketPacketType::DISCONNECT], // BINARY_DISCONNECT (1 + 3)
            ['5', SocketPacketType::EVENT],      // BINARY_EVENT (2 + 3)
            ['6', SocketPacketType::ACK],        // BINARY_ACK (3 + 3)
        ];
        
        foreach ($testCases as [$encoded, $expectedType]) {
            $packet = SocketPacket::decode($encoded);
            $this->assertSame($expectedType, $packet->getType(), "Failed for binary packet type {$expectedType->value}");
            $this->assertTrue($packet->isBinary(), "Packet should be marked as binary");
        }
    }

    public function test_error_packet_is_created_with_static_method(): void
    {
        // ERROR类型只能通过静态方法创建，不能通过decode
        $packet = SocketPacket::createError('["error","message"]');
        
        $this->assertSame(SocketPacketType::ERROR, $packet->getType());
        $this->assertSame('["error","message"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }
} 