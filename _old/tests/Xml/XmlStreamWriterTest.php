<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Xml;

use function rewind;
use function stream_get_contents;

use Kalle\Pdf\Xml;
use Kalle\Pdf\Xml\XmlStreamWriter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class XmlStreamWriterTest extends TestCase
{
    public function testItStreamsCompactXmlIncrementally(): void
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            self::fail('Unable to open a temporary stream for XML output.');
        }

        $writer = new XmlStreamWriter();
        $writer->startDocument($stream, standalone: true, pretty: false);
        $writer->startElement('invoice', ['xmlns' => 'urn:demo']);
        $writer->writeElement('id', 'RE-2026-0415');
        $writer->startElement('lines');
        $writer->startElement('line');
        $writer->writeElement('position', '1');
        $writer->writeElement('description', 'Service & Support');
        $writer->endElement();
        $writer->endElement();
        $writer->endElement();
        $writer->finishDocument();

        rewind($stream);
        $contents = stream_get_contents($stream);

        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><invoice xmlns="urn:demo"><id>RE-2026-0415</id><lines><line><position>1</position><description>Service &amp; Support</description></line></lines></invoice>',
            $contents,
        );
    }

    public function testFacadeCanCreateAStreamWriter(): void
    {
        self::assertInstanceOf(XmlStreamWriter::class, Xml::streamWriter());
    }

    public function testItRejectsFinishingWithOpenElements(): void
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            self::fail('Unable to open a temporary stream for XML output.');
        }

        $writer = new XmlStreamWriter();
        $writer->startDocument($stream, pretty: false);
        $writer->startElement('invoice');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot finish XML document with open elements remaining.');

        $writer->finishDocument();
    }

    public function testItRejectsPrettyMixedContentAfterChildElements(): void
    {
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            self::fail('Unable to open a temporary stream for XML output.');
        }

        $writer = new XmlStreamWriter();
        $writer->startDocument($stream, pretty: true);
        $writer->startElement('p');
        $writer->writeElement('strong', 'world');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pretty XML streaming does not support text after child elements in the same parent.');

        $writer->writeText('!');
    }
}
