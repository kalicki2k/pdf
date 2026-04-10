<?php

declare(strict_types=1);

namespace Kalle\Pdf\Model\Document;

use Kalle\Pdf\Binary\BinaryData;
use Kalle\Pdf\Object\StreamIndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\DictionaryType;

class EmbeddedFileStream extends StreamIndirectObject
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

    protected function streamDictionary(int $length): DictionaryType
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

    protected function writeStreamContents(PdfOutput $output): void
    {
        $this->contents->writeTo($output);
    }

    protected function streamLength(): int
    {
        return $this->contents->length();
    }
}
