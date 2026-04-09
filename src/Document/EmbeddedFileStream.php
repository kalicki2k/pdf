<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\DictionaryType;

final class EmbeddedFileStream extends IndirectObject implements EncryptableIndirectObject
{
    public function __construct(
        int $id,
        string | BinaryData $contents,
        private readonly ?string $mimeType = null,
    ) {
        parent::__construct($id);
        $this->contents = is_string($contents) ? BinaryData::fromString($contents) : $contents;
    }

    private readonly BinaryData $contents;

    public function render(): string
    {
        $contents = $this->contents->contents();

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
        $output->write($this->dictionary($this->contents->length())->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->contents->writeTo($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $encryptedContents = $objectEncryptor->encryptString($this->id, $this->contents->contents());

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary(strlen($encryptedContents))->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $output->write($encryptedContents);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    private function dictionary(int $length): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Type' => '/EmbeddedFile',
            'Length' => $length,
            'Params' => new DictionaryType([
                'Size' => $length,
            ]),
        ]);

        if ($this->mimeType !== null) {
            $dictionary->add('Subtype', '/' . str_replace('/', '#2F', $this->mimeType));
        }

        return $dictionary;
    }
}
