<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Xml;

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
}
