<?php

namespace SocketIoBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use SocketIoBundle\Exception\InvalidPayloadException;
use SocketIoBundle\Service\PayloadProcessor;

/**
 * @internal
 */
#[CoversClass(PayloadProcessor::class)]
final class PayloadProcessorTest extends TestCase
{
    public function testEncodeTextPacket(): void
    {
        $processor = new PayloadProcessor(false, false, null);
        $result = $processor->encodePacket('test message');

        $this->assertSame('test message', $result);
    }

    public function testEncodeBinaryPacketWithSupport(): void
    {
        $processor = new PayloadProcessor(true, false, null);
        $binaryData = "\x00\x01\x02";

        $result = $processor->encodePacket($binaryData);

        $this->assertSame($binaryData, $result);
    }

    public function testEncodeBinaryPacketWithoutSupport(): void
    {
        $processor = new PayloadProcessor(false, false, null);
        $binaryData = "\x00\x01\x02";

        $result = $processor->encodePacket($binaryData);

        $this->assertSame('b' . base64_encode($binaryData), $result);
    }

    public function testEncodeTextPacketWithJsonp(): void
    {
        $processor = new PayloadProcessor(false, true, '5');
        $result = $processor->encodePacket('test');

        $this->assertSame("___eio[5]('test');", $result);
    }

    public function testDecodeEmptyPayload(): void
    {
        $processor = new PayloadProcessor();
        $result = $processor->decodePayload('');

        $this->assertSame([], $result);
    }

    public function testDecodePayloadWithMultiplePackets(): void
    {
        $processor = new PayloadProcessor();
        $payload = "packet1\x1epacket2\x1epacket3";

        $result = $processor->decodePayload($payload);

        $this->assertSame(['packet1', 'packet2', 'packet3'], $result);
    }

    public function testDecodePayloadWithBinaryChunk(): void
    {
        $processor = new PayloadProcessor();
        $binaryData = "\x00\x01\x02";
        $payload = 'b' . base64_encode($binaryData);

        $result = $processor->decodePayload($payload);

        $this->assertSame([$binaryData], $result);
    }

    public function testDecodePayloadWithInvalidBase64(): void
    {
        $processor = new PayloadProcessor();
        $payload = 'b!!!invalid-base64!!!';

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Invalid base64 payload');

        $processor->decodePayload($payload);
    }

    public function testBuildPayload(): void
    {
        $processor = new PayloadProcessor();
        $packets = ['packet1', 'packet2', 'packet3'];

        $result = $processor->buildPayload($packets);

        $this->assertSame("packet1\x1epacket2\x1epacket3", $result);
    }

    public function testDecodeJsonpPayloadSuccess(): void
    {
        $processor = new PayloadProcessor();
        $content = 'd=hello%20world';

        $result = $processor->decodeJsonpPayload($content);

        $this->assertSame('hello world', $result);
    }

    public function testDecodeJsonpPayloadInvalid(): void
    {
        $processor = new PayloadProcessor();
        $content = 'invalid content';

        $this->expectException(InvalidPayloadException::class);
        $this->expectExceptionMessage('Invalid JSONP payload');

        $processor->decodeJsonpPayload($content);
    }

    public function testEncodePacketMultipleFormats(): void
    {
        // Test text packet without JSONP
        $processor = new PayloadProcessor(false, false, null);
        $result = $processor->encodePacket('hello world');
        $this->assertSame('hello world', $result);

        // Test text packet with JSONP
        $processor = new PayloadProcessor(false, true, '123');
        $result = $processor->encodePacket('hello world');
        $this->assertSame("___eio[123]('hello world');", $result);

        // Test binary packet with support
        $processor = new PayloadProcessor(true, false, null);
        $binaryData = "\x00\x01\x02";
        $result = $processor->encodePacket($binaryData);
        $this->assertSame($binaryData, $result);

        // Test binary packet without support
        $processor = new PayloadProcessor(false, false, null);
        $binaryData = "\x00\x01\x02";
        $result = $processor->encodePacket($binaryData);
        $this->assertSame('b' . base64_encode($binaryData), $result);
    }
}
