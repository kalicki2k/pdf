<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Xml;

use Kalle\Pdf\Xml\XmlDocument;
use Kalle\Pdf\Xml\XmlElement;
use Kalle\Pdf\Xml\XmlSerializer;
use Kalle\Pdf\Xml\XmlText;
use PHPUnit\Framework\TestCase;

final class XmlSerializerTest extends TestCase
{
    public function testItSerializesPrettyPrintedXml(): void
    {
        $document = new XmlDocument(
            new XmlElement('invoice', ['xmlns' => 'urn:demo'], [
                new XmlElement('id')->withText('RE-2026-0415'),
                new XmlElement('amount', ['currencyID' => 'EUR'], [
                    new XmlText('12.34'),
                ]),
            ]),
        );

        $xml = new XmlSerializer()->serialize($document);

        self::assertSame(
            <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<invoice xmlns="urn:demo">
  <id>RE-2026-0415</id>
  <amount currencyID="EUR">12.34</amount>
</invoice>
XML
            . "\n",
            $xml,
        );
    }

    public function testItEscapesTextAndAttributes(): void
    {
        $document = new XmlDocument(
            new XmlElement('invoice', ['note' => '"A&B"'], [
                new XmlElement('text')->withText('<gross & net>'),
            ]),
        );

        $xml = new XmlSerializer()->serialize($document, pretty: false);

        self::assertSame(
            '<?xml version="1.0" encoding="UTF-8"?><invoice note="&quot;A&amp;B&quot;"><text>&lt;gross &amp; net&gt;</text></invoice>',
            $xml,
        );
    }
}
