<?php

declare(strict_types=1);

namespace Kalle\Pdf\Tests\Xml;

use InvalidArgumentException;
use Kalle\Pdf\Xml\XmlElement;
use Kalle\Pdf\Xml\XmlText;
use PHPUnit\Framework\TestCase;

final class XmlElementTest extends TestCase
{
    public function testItStoresAttributesAndChildren(): void
    {
        $element = new XmlElement('invoice')
            ->withAttribute('xmlns', 'urn:demo')
            ->withText('demo')
            ->withChild(new XmlElement('child'));

        self::assertSame('invoice', $element->name);
        self::assertSame('urn:demo', $element->attributes['xmlns']);
        self::assertCount(2, $element->children);
        self::assertInstanceOf(XmlText::class, $element->children[0]);
        self::assertInstanceOf(XmlElement::class, $element->children[1]);
    }

    public function testItRejectsEmptyElementNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('XML element name must not be empty.');

        new XmlElement('');
    }

    public function testItRejectsEmptyAttributeNames(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('XML attribute name must not be empty.');

        new XmlElement('invoice')->withAttribute('', 'value');
    }
}
