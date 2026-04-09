<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Element\Element;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\DictionaryType;
use RuntimeException;

final class Contents extends IndirectObject implements EncryptableIndirectObject
{
    private const int READ_CHUNK_BYTES = 8192;

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
        $contents = $this->readContents();

        return $this->id . ' 0 obj' . PHP_EOL
            . $this->dictionary(strlen($contents))->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $contents . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function write(PdfOutput $output): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary($this->length)->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->writeContentsTo($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $encryptedContents = $objectEncryptor->encryptString($this->id, $this->readContents());

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary(strlen($encryptedContents))->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $output->write($encryptedContents);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
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

    private function writeContentsTo(PdfOutput $output): void
    {
        if (!$this->hasElements) {
            return;
        }

        $stream = $this->stream();

        if (rewind($stream) === false) {
            throw new RuntimeException('Unable to rewind PDF content stream buffer.');
        }

        while (!feof($stream)) {
            $chunk = fread($stream, self::READ_CHUNK_BYTES);

            if ($chunk === false) {
                throw new RuntimeException('Unable to read PDF content stream buffer.');
            }

            if ($chunk === '') {
                continue;
            }

            $output->write($chunk);
        }

        if (fseek($stream, 0, SEEK_END) !== 0) {
            throw new RuntimeException('Unable to seek PDF content stream buffer.');
        }
    }

    private function dictionary(int $length): DictionaryType
    {
        return new DictionaryType([
            'Length' => $length,
        ]);
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
