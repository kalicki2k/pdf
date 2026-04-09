<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\DictionaryType;

final class EmbeddedFileStream extends IndirectObject
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
        return $this->id . ' 0 obj' . PHP_EOL
            . $this->dictionary()->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $this->contents->contents() . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function write(PdfOutput $output): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary()->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->contents->writeTo($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    private function dictionary(): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Type' => '/EmbeddedFile',
            'Length' => $this->contents->length(),
            'Params' => new DictionaryType([
                'Size' => $this->contents->length(),
            ]),
        ]);

        if ($this->mimeType !== null) {
            $dictionary->add('Subtype', '/' . str_replace('/', '#2F', $this->mimeType));
        }

        return $dictionary;
    }
}
