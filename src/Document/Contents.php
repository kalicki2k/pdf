<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Element\Element;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use RuntimeException;

final class Contents extends IndirectObject
{
    /** @var resource|null */
    private $stream = null;

    private int $length = 0;
    private bool $hasElements = false;

    public function addElement(Element $element): self
    {
        if ($this->hasElements) {
            $this->appendBytes(PHP_EOL);
        }

        $this->appendBytes($element->render());
        $this->hasElements = true;

        return $this;
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Length' => $this->length,
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $this->readContents() . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    private function appendBytes(string $bytes): void
    {
        if ($bytes === '') {
            return;
        }

        $stream = $this->stream();
        $remainingBytes = $bytes;

        while ($remainingBytes !== '') {
            $writtenBytes = fwrite($stream, $remainingBytes);

            if ($writtenBytes === false || $writtenBytes === 0) {
                throw new RuntimeException('Unable to append PDF content stream bytes.');
            }

            $this->length += $writtenBytes;
            $remainingBytes = substr($remainingBytes, $writtenBytes);
        }
    }

    private function readContents(): string
    {
        if (!$this->hasElements) {
            return '';
        }

        $stream = $this->stream();

        if (rewind($stream) === false) {
            throw new RuntimeException('Unable to rewind PDF content stream buffer.');
        }

        $contents = stream_get_contents($stream);

        if ($contents === false) {
            throw new RuntimeException('Unable to read PDF content stream buffer.');
        }

        if (fseek($stream, 0, SEEK_END) !== 0) {
            throw new RuntimeException('Unable to seek PDF content stream buffer.');
        }

        return $contents;
    }

    /**
     * @return resource
     */
    private function stream()
    {
        if ($this->stream === null) {
            $stream = fopen('php://temp', 'w+b');

            if ($stream === false) {
                throw new RuntimeException('Unable to open PDF content stream buffer.');
            }

            $this->stream = $stream;
        }

        return $this->stream;
    }
}
