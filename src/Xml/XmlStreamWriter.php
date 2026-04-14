<?php

declare(strict_types=1);

namespace Kalle\Pdf\Xml;

use function array_pop;
use function count;
use function fwrite;
use function get_resource_type;
use function is_resource;
use function str_repeat;
use function str_replace;

use InvalidArgumentException;
use RuntimeException;

final class XmlStreamWriter
{
    /** @var resource|null */
    private $stream = null;

    /** @var list<array{name: string, hasText: bool, hasElement: bool}> */
    private array $elementStack = [];
    private bool $pretty = true;
    private bool $documentStarted = false;
    private bool $documentFinished = false;
    private bool $lastWriteWasText = false;

    /**
     * @param resource $stream
     */
    public function startDocument(
        $stream,
        string $version = '1.0',
        string $encoding = 'UTF-8',
        bool $standalone = false,
        bool $pretty = true,
    ): void {
        if ($this->documentStarted && !$this->documentFinished) {
            throw new RuntimeException('XML stream document has already been started.');
        }

        if (!is_resource($stream) || get_resource_type($stream) !== 'stream') {
            throw new InvalidArgumentException('XML output stream must be a valid stream resource.');
        }

        $this->stream = $stream;
        $this->pretty = $pretty;
        $this->documentStarted = true;
        $this->documentFinished = false;
        $this->elementStack = [];
        $this->lastWriteWasText = false;

        $this->writeBytes(
            '<?xml version="' . $version . '" encoding="' . $encoding . '"'
            . ($standalone ? ' standalone="yes"' : '')
            . '?>',
        );

        if ($this->pretty) {
            $this->writeBytes("\n");
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    public function startElement(string $name, array $attributes = []): void
    {
        $this->assertDocumentOpen();

        if ($name === '') {
            throw new InvalidArgumentException('XML element name must not be empty.');
        }

        if ($this->pretty && !$this->lastWriteWasText && $this->elementStack !== []) {
            $this->writeBytes(str_repeat('  ', count($this->elementStack)));
        }

        if ($this->pretty && $this->currentElementHasText()) {
            $this->markCurrentElementHasElement();
            $this->writeBytes('<' . $name . $this->serializeAttributes($attributes) . '>');
            $this->elementStack[] = ['name' => $name, 'hasText' => false, 'hasElement' => false];
            $this->lastWriteWasText = false;

            return;
        }

        $this->writeBytes('<' . $name . $this->serializeAttributes($attributes) . '>');
        $this->markCurrentElementHasElement();
        $this->elementStack[] = ['name' => $name, 'hasText' => false, 'hasElement' => false];
        $this->lastWriteWasText = false;

        if ($this->pretty) {
            $this->writeBytes("\n");
        }
    }

    public function writeText(string $text): void
    {
        $this->assertDocumentOpen();

        if ($this->elementStack === []) {
            throw new RuntimeException('XML text cannot be written outside of an element.');
        }

        if ($this->pretty && $this->currentElementHasElement()) {
            throw new RuntimeException('Pretty XML streaming does not support text after child elements in the same parent.');
        }

        if ($this->pretty && !$this->lastWriteWasText) {
            $this->writeBytes(str_repeat('  ', count($this->elementStack)));
        }

        $this->writeBytes($this->escape($text, false));
        $this->markCurrentElementHasText();
        $this->lastWriteWasText = true;

        if ($this->pretty) {
            $this->writeBytes("\n");
        }
    }

    /**
     * @param array<string, string> $attributes
     */
    public function writeElement(string $name, string $text, array $attributes = []): void
    {
        $this->assertDocumentOpen();

        if ($name === '') {
            throw new InvalidArgumentException('XML element name must not be empty.');
        }

        if ($this->pretty && !$this->lastWriteWasText && $this->elementStack !== []) {
            $this->writeBytes(str_repeat('  ', count($this->elementStack)));
        }

        $this->markCurrentElementHasElement();
        $this->writeBytes(
            '<' . $name . $this->serializeAttributes($attributes) . '>'
            . $this->escape($text, false)
            . '</' . $name . '>',
        );
        $this->lastWriteWasText = false;

        if ($this->pretty) {
            $this->writeBytes("\n");
        }
    }

    public function endElement(): void
    {
        $this->assertDocumentOpen();

        $frame = array_pop($this->elementStack);

        if ($frame === null) {
            throw new RuntimeException('No XML element is currently open.');
        }

        if ($this->pretty && !$frame['hasText'] && $frame['hasElement']) {
            $this->writeBytes(str_repeat('  ', count($this->elementStack)));
        }

        $this->writeBytes('</' . $frame['name'] . '>');
        $this->lastWriteWasText = false;

        if ($this->pretty && $this->elementStack !== []) {
            $this->writeBytes("\n");
        }
    }

    public function finishDocument(): void
    {
        $this->assertDocumentOpen();

        if ($this->elementStack !== []) {
            throw new RuntimeException('Cannot finish XML document with open elements remaining.');
        }

        $this->documentFinished = true;
        $this->stream = null;
    }

    /**
     * @param array<string, string> $attributes
     */
    private function serializeAttributes(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        $pairs = [];

        foreach ($attributes as $name => $value) {
            if ($name === '') {
                throw new InvalidArgumentException('XML attribute name must not be empty.');
            }

            $pairs[] = $name . '="' . $this->escape($value, true) . '"';
        }

        return ' ' . implode(' ', $pairs);
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

    private function assertDocumentOpen(): void
    {
        if (!$this->documentStarted || $this->documentFinished || !is_resource($this->stream)) {
            throw new RuntimeException('XML stream document has not been started or is already finished.');
        }
    }

    private function currentElementHasText(): bool
    {
        if ($this->elementStack === []) {
            return false;
        }

        return array_last($this->elementStack)['hasText'];
    }

    private function currentElementHasElement(): bool
    {
        if ($this->elementStack === []) {
            return false;
        }

        return array_last($this->elementStack)['hasElement'];
    }

    private function markCurrentElementHasText(): void
    {
        if ($this->elementStack === []) {
            return;
        }

        $index = array_key_last($this->elementStack);
        $this->elementStack[$index]['hasText'] = true;
    }

    private function markCurrentElementHasElement(): void
    {
        if ($this->elementStack === []) {
            return;
        }

        $index = array_key_last($this->elementStack);
        $this->elementStack[$index]['hasElement'] = true;
    }

    private function writeBytes(string $bytes): void
    {
        $stream = $this->stream;

        if (!is_resource($stream)) {
            throw new RuntimeException('XML output stream is not available.');
        }

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
