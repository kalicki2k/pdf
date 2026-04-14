<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use Kalle\Pdf\Xml\XmlDocument;
use Kalle\Pdf\Xml\XmlElement;
use Kalle\Pdf\Xml\XmlNode;
use Kalle\Pdf\Xml\XmlSerializer;
use Kalle\Pdf\Xml\XmlStreamWriter;
use Kalle\Pdf\Xml\XmlText;
use Kalle\Pdf\Xml\XmlWriter;

final readonly class Xml
{
    private function __construct()
    {
    }

    /**
     * @param array<string, string> $attributes
     * @param list<XmlNode> $children
     */
    public static function element(string $name, array $attributes = [], array $children = []): XmlElement
    {
        return new XmlElement($name, $attributes, $children);
    }

    public static function text(string $value): XmlText
    {
        return new XmlText($value);
    }

    public static function document(
        XmlElement $root,
        string $version = '1.0',
        string $encoding = 'UTF-8',
        bool $standalone = false,
    ): XmlDocument {
        return new XmlDocument($root, $version, $encoding, $standalone);
    }

    public static function serialize(XmlDocument $document, bool $pretty = true): string
    {
        return new XmlSerializer()->serialize($document, $pretty);
    }

    /**
     * @param resource $stream
     */
    public static function writeToStream(XmlDocument $document, $stream, bool $pretty = true): void
    {
        new XmlWriter()->writeToStream($document, $stream, $pretty);
    }

    public static function writeToFile(XmlDocument $document, string $path, bool $pretty = true): void
    {
        new XmlWriter()->writeToFile($document, $path, $pretty);
    }

    public static function streamWriter(): XmlStreamWriter
    {
        return new XmlStreamWriter();
    }
}
