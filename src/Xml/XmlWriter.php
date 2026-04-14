<?php

declare(strict_types=1);

namespace Kalle\Pdf\Xml;

use function fclose;
use function fflush;
use function fopen;
use function fwrite;
use function get_resource_type;
use function is_resource;
use function str_repeat;

use InvalidArgumentException;
use RuntimeException;

final readonly class XmlWriter
{
    /**
     * @param resource $stream
     */
    public function writeToStream(XmlDocument $document, $stream, bool $pretty = true): void
    {
        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('XML output stream must be a valid stream resource.');
        }

        $this->writeBytes(
            $stream,
            '<?xml version="' . $document->version . '" encoding="' . $document->encoding . '"'
            . ($document->standalone ? ' standalone="yes"' : '')
            . '?>',
        );

        if ($pretty) {
            $this->writeBytes($stream, "\n");
            $this->writeElementPretty($stream, $document->root);
            $this->writeBytes($stream, "\n");

            return;
        }

        $this->writeElementCompact($stream, $document->root);
    }

    public function writeToFile(XmlDocument $document, string $path, bool $pretty = true): void
    {
        if ($path === '') {
            throw new InvalidArgumentException('XML output path must not be empty.');
        }

        $stream = @fopen($path, 'wb');

        if ($stream === false) {
            throw new RuntimeException("Unable to open XML output file '$path' for writing.");
        }

        try {
            $this->writeToStream($document, $stream, $pretty);

            if (!fflush($stream)) {
                throw new RuntimeException("Unable to flush XML output file '$path'.");
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @param resource $stream
     */
    private function writeElementCompact($stream, XmlElement $element): void
    {
        $this->writeBytes($stream, '<' . $element->name . $this->serializeAttributes($element));

        if ($element->children === []) {
            $this->writeBytes($stream, '/>');

            return;
        }

        $this->writeBytes($stream, '>');

        foreach ($element->children as $child) {
            $this->writeCompactChild($stream, $child);
        }

        $this->writeBytes($stream, '</' . $element->name . '>');
    }

    /**
     * @param resource $stream
     */
    private function writeElementPretty($stream, XmlElement $element, int $depth = 0): void
    {
        $indent = str_repeat('  ', $depth);
        $this->writeBytes($stream, $indent . '<' . $element->name . $this->serializeAttributes($element));

        if ($element->children === []) {
            $this->writeBytes($stream, '/>');

            return;
        }

        if ($this->hasTextChildren($element)) {
            $this->writeBytes($stream, '>');

            foreach ($element->children as $child) {
                $this->writeCompactChild($stream, $child);
            }

            $this->writeBytes($stream, '</' . $element->name . '>');

            return;
        }

        $this->writeBytes($stream, '>' . "\n");

        $lastIndex = array_key_last($element->children);

        foreach ($element->children as $index => $child) {
            $this->writePrettyChild($stream, $child, $depth + 1);

            if ($index !== $lastIndex) {
                $this->writeBytes($stream, "\n");
            }
        }

        $this->writeBytes($stream, "\n" . $indent . '</' . $element->name . '>');
    }

    /**
     * @param resource $stream
     */
    private function writeCompactChild($stream, XmlNode $node): void
    {
        match (true) {
            $node instanceof XmlElement => $this->writeElementCompact($stream, $node),
            $node instanceof XmlText => $this->writeBytes($stream, $this->escapeText($node->value)),
            default => throw new InvalidArgumentException('Unsupported XML node type.'),
        };
    }

    /**
     * @param resource $stream
     */
    private function writePrettyChild($stream, XmlNode $node, int $depth): void
    {
        match (true) {
            $node instanceof XmlElement => $this->writeElementPretty($stream, $node, $depth),
            $node instanceof XmlText => $this->writeBytes($stream, str_repeat('  ', $depth) . $this->escapeText($node->value)),
            default => throw new InvalidArgumentException('Unsupported XML node type.'),
        };
    }

    private function serializeAttributes(XmlElement $element): string
    {
        if ($element->attributes === []) {
            return '';
        }

        $pairs = [];

        foreach ($element->attributes as $name => $value) {
            $pairs[] = $name . '="' . $this->escapeAttribute($value) . '"';
        }

        return ' ' . implode(' ', $pairs);
    }

    private function hasTextChildren(XmlElement $element): bool
    {
        foreach ($element->children as $child) {
            if ($child instanceof XmlText) {
                return true;
            }
        }

        return false;
    }

    private function escapeText(string $value): string
    {
        return $this->escape($value, false);
    }

    private function escapeAttribute(string $value): string
    {
        return $this->escape($value, true);
    }

    private function escape(string $value, bool $attribute): string
    {
        $escaped = str_replace(
            ['&', '<', '>'],
            ['&amp;', '&lt;', '&gt;'],
            $value,
        );

        if (!$attribute) {
            return $escaped;
        }

        return str_replace(
            ['"', "'"],
            ['&quot;', '&apos;'],
            $escaped,
        );
    }

    /**
     * @param resource $stream
     */
    private function writeBytes($stream, string $bytes): void
    {
        $remainingBytes = $bytes;

        while ($remainingBytes !== '') {
            $writtenBytes = fwrite($stream, $remainingBytes);

            if ($writtenBytes === false || $writtenBytes === 0) {
                throw new RuntimeException('Unable to write XML bytes to output stream.');
            }

            $remainingBytes = substr($remainingBytes, $writtenBytes);
        }
    }
}
