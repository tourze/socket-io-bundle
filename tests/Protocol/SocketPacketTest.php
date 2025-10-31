<?php

namespace SocketIoBundle\Tests\Protocol;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Enum\SocketPacketType;
use SocketIoBundle\Protocol\SocketPacket;

/**
 * @internal
 */
#[CoversClass(SocketPacket::class)]
final class SocketPacketTest extends TestCase
{
    public function testConstructorSetsAllProperties(): void
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

    public function testConstructorWithMinimalParameters(): void
    {
        $packet = new SocketPacket(SocketPacketType::CONNECT);

        $this->assertSame(SocketPacketType::CONNECT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertNull($packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function testEncodeBasicPacket(): void
    {
        $packet = new SocketPacket(SocketPacketType::CONNECT);

        $encoded = $packet->encode();

        $this->assertSame('0', $encoded);
    }

    public function testEncodePacketWithData(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, null, null, '["test","data"]');

        $encoded = $packet->encode();

        $this->assertSame('2["test","data"]', $encoded);
    }

    public function testEncodePacketWithNamespace(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, '/admin', null, '["test"]');

        $encoded = $packet->encode();

        $this->assertSame('2/admin,["test"]', $encoded);
    }

    public function testEncodePacketWithId(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, null, 25, '["test"]');

        $encoded = $packet->encode();

        $this->assertSame('225["test"]', $encoded);
    }

    public function testEncodePacketWithNamespaceAndId(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, '/admin', 25, '["test"]');

        $encoded = $packet->encode();

        $this->assertSame('2/admin,25["test"]', $encoded);
    }

    public function testEncodeBinaryPacket(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, null, null, '["test"]', true);

        $encoded = $packet->encode();

        $this->assertSame('5["test"]', $encoded);
    }

    public function testEncodeBinaryAckPacket(): void
    {
        $packet = new SocketPacket(SocketPacketType::ACK, null, 15, '["response"]', true);

        $encoded = $packet->encode();

        $this->assertSame('615["response"]', $encoded);
    }

    public function testEncodePacketWithRootNamespace(): void
    {
        $packet = new SocketPacket(SocketPacketType::EVENT, '/', null, '["test"]');

        $encoded = $packet->encode();

        $this->assertSame('2["test"]', $encoded);
    }

    public function testDecodeBasicPacket(): void
    {
        $packet = SocketPacket::decode('0');

        $this->assertSame(SocketPacketType::CONNECT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertNull($packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function testDecodePacketWithData(): void
    {
        $packet = SocketPacket::decode('2["hello","world"]');

        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["hello","world"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function testDecodePacketWithNamespace(): void
    {
        $packet = SocketPacket::decode('2/admin,["test"]');

        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["test"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function testDecodePacketWithId(): void
    {
        $packet = SocketPacket::decode('325["response"]');

        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertSame(25, $packet->getId());
        $this->assertSame('["response"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function testDecodePacketWithNamespaceAndId(): void
    {
        $packet = SocketPacket::decode('2/admin,25["test"]');

        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertSame(25, $packet->getId());
        $this->assertSame('["test"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function testDecodeBinaryPacket(): void
    {
        $packet = SocketPacket::decode('5["binary","data"]');

        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["binary","data"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function testDecodeBinaryAckPacket(): void
    {
        $packet = SocketPacket::decode('615["ack","data"]');

        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertSame(15, $packet->getId());
        $this->assertSame('["ack","data"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function testCreateConnectPacketWithoutData(): void
    {
        $packet = SocketPacket::createConnect();

        $this->assertSame(SocketPacketType::CONNECT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertNull($packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function testCreateConnectPacketWithNamespace(): void
    {
        $packet = SocketPacket::createConnect('/admin');

        $this->assertSame(SocketPacketType::CONNECT, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertNull($packet->getData());
    }

    public function testCreateConnectPacketWithData(): void
    {
        $data = ['auth' => 'token123'];
        $packet = SocketPacket::createConnect(null, $data);

        $this->assertSame(SocketPacketType::CONNECT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertSame(json_encode($data), $packet->getData());
    }

    public function testCreateDisconnectPacket(): void
    {
        $packet = SocketPacket::createDisconnect();

        $this->assertSame(SocketPacketType::DISCONNECT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertNull($packet->getData());
    }

    public function testCreateDisconnectPacketWithNamespace(): void
    {
        $packet = SocketPacket::createDisconnect('/admin');

        $this->assertSame(SocketPacketType::DISCONNECT, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
    }

    public function testCreateEventPacket(): void
    {
        $packet = SocketPacket::createEvent('["message","hello"]');

        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["message","hello"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function testCreateEventPacketWithAllParameters(): void
    {
        $packet = SocketPacket::createEvent('["test"]', '/admin', 123, true);

        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertSame(123, $packet->getId());
        $this->assertSame('["test"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function testCreateAckPacket(): void
    {
        $packet = SocketPacket::createAck('["response"]');

        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["response"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }

    public function testCreateAckPacketWithAllParameters(): void
    {
        $packet = SocketPacket::createAck('["ok"]', '/admin', 456, true);

        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertSame(456, $packet->getId());
        $this->assertSame('["ok"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function testCreateErrorPacket(): void
    {
        $packet = SocketPacket::createError('["error","message"]');

        $this->assertSame(SocketPacketType::ERROR, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["error","message"]', $packet->getData());
    }

    public function testCreateErrorPacketWithNamespace(): void
    {
        $packet = SocketPacket::createError('["error"]', '/admin');

        $this->assertSame(SocketPacketType::ERROR, $packet->getType());
        $this->assertSame('/admin', $packet->getNamespace());
        $this->assertSame('["error"]', $packet->getData());
    }

    public function testCreateBinaryEventPacket(): void
    {
        $packet = SocketPacket::createBinaryEvent('["binary","data"]');

        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["binary","data"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function testCreateBinaryEventPacketWithAllParameters(): void
    {
        $packet = SocketPacket::createBinaryEvent('["data"]', '/files', 789);

        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertSame('/files', $packet->getNamespace());
        $this->assertSame(789, $packet->getId());
        $this->assertSame('["data"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function testCreateBinaryAckPacket(): void
    {
        $packet = SocketPacket::createBinaryAck('["ack","binary"]');

        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertNull($packet->getNamespace());
        $this->assertNull($packet->getId());
        $this->assertSame('["ack","binary"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function testCreateBinaryAckPacketWithAllParameters(): void
    {
        $packet = SocketPacket::createBinaryAck('["ok"]', '/uploads', 101);

        $this->assertSame(SocketPacketType::ACK, $packet->getType());
        $this->assertSame('/uploads', $packet->getNamespace());
        $this->assertSame(101, $packet->getId());
        $this->assertSame('["ok"]', $packet->getData());
        $this->assertTrue($packet->isBinary());
    }

    public function testEncodeDecodeRoundtrip(): void
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

    public function testEncodeDecodeRoundtripBinary(): void
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

    public function testDecodeWithMultiDigitId(): void
    {
        $packet = SocketPacket::decode('2123456["test"]');

        $this->assertSame(SocketPacketType::EVENT, $packet->getType());
        $this->assertSame(123456, $packet->getId());
        $this->assertSame('["test"]', $packet->getData());
    }

    public function testDecodeAllPacketTypes(): void
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
            $this->assertFalse($packet->isBinary(), 'Regular packet should not be binary');
        }
    }

    public function testDecodeAllBinaryPacketTypes(): void
    {
        $testCases = [
            ['4', SocketPacketType::DISCONNECT], // BINARY_DISCONNECT (1 + 3)
            ['5', SocketPacketType::EVENT],      // BINARY_EVENT (2 + 3)
            ['6', SocketPacketType::ACK],        // BINARY_ACK (3 + 3)
        ];

        foreach ($testCases as [$encoded, $expectedType]) {
            $packet = SocketPacket::decode($encoded);
            $this->assertSame($expectedType, $packet->getType(), "Failed for binary packet type {$expectedType->value}");
            $this->assertTrue($packet->isBinary(), 'Packet should be marked as binary');
        }
    }

    public function testErrorPacketIsCreatedWithStaticMethod(): void
    {
        // ERROR类型只能通过静态方法创建，不能通过decode
        $packet = SocketPacket::createError('["error","message"]');

        $this->assertSame(SocketPacketType::ERROR, $packet->getType());
        $this->assertSame('["error","message"]', $packet->getData());
        $this->assertFalse($packet->isBinary());
    }
}
