<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Xml;

use function file_get_contents;
use function rewind;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;

use Kalle\Pdf\Xml;
use PHPUnit\Framework\TestCase;

final class XmlFacadeTest extends TestCase
{
    public function testItBuildsAndSerializesXmlViaFacade(): void
    {
        $document = Xml::document(
            Xml::element('invoice', ['xmlns:rsm' => 'urn:demo'], [
                Xml::element('rsm:ID')->withText('RE-2026-0415'),
            ]),
        );

        $xml = Xml::serialize($document, pretty: false);

        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?><invoice xmlns:rsm="urn:demo"><rsm:ID>RE-2026-0415</rsm:ID></invoice>',
            $xml,
        );
    }

    public function testItWritesXmlToAStreamViaFacade(): void
    {
        $document = Xml::document(
            Xml::element('invoice', children: [
                Xml::element('id')->withText('RE-2026-0415'),
            ]),
        );
        $stream = fopen('php://temp', 'w+b');

        if ($stream === false) {
            self::fail('Unable to open a temporary stream for XML output.');
        }

        Xml::writeToStream($document, $stream, pretty: false);

        rewind($stream);
        $contents = stream_get_contents($stream);

        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?><invoice><id>RE-2026-0415</id></invoice>',
            $contents,
        );
    }

    public function testItWritesXmlToAFileViaFacade(): void
    {
        $document = Xml::document(
            Xml::element('invoice', children: [
                Xml::element('id')->withText('RE-2026-0415'),
            ]),
        );
        $path = tempnam(sys_get_temp_dir(), 'pdf2-xml-facade-');

        if ($path === false) {
            self::fail('Unable to allocate a temporary path for XML output.');
        }

        try {
            Xml::writeToFile($document, $path, pretty: false);

            $contents = file_get_contents($path);

            self::assertIsString($contents);
            self::assertSame(
                '<?xml version="1.0" encoding="UTF-8"?><invoice><id>RE-2026-0415</id></invoice>',
                $contents,
            );
        } finally {
            unlink($path);
        }
    }
}
