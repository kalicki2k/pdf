<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Xml;

use Kalle\Pdf\Xml\XmlDocument;
use Kalle\Pdf\Xml\XmlElement;
use PHPUnit\Framework\TestCase;

final class XmlDocumentTest extends TestCase
{
    public function testItCarriesDocumentConfiguration(): void
    {
        $document = new XmlDocument(
            root: new XmlElement('invoice'),
            version: '1.1',
            encoding: 'ISO-8859-1',
            standalone: true,
        );

        self::assertSame('invoice', $document->root->name);
        self::assertSame('1.1', $document->version);
        self::assertSame('ISO-8859-1', $document->encoding);
        self::assertTrue($document->standalone);
    }
}
