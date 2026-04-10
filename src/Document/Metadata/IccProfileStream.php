<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Metadata;

use InvalidArgumentException;
use Kalle\Pdf\Binary\BinaryData;
use Kalle\Pdf\Object\KnownLengthStreamIndirectObject;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\Render\PdfOutput;
use RuntimeException;

class IccProfileStream extends KnownLengthStreamIndirectObject
{
    public function __construct(
        int $id,
        string | BinaryData $data,
        private readonly int $colorComponents = 3,
    ) {
        parent::__construct($id);
        $this->data = is_string($data) ? BinaryData::fromString($data) : $data;
    }

    private readonly BinaryData $data;

    public static function fromPath(int $id, string $path, int $colorComponents = 3): self
    {
        try {
            $data = BinaryData::fromFile($path);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException("Unable to read ICC profile '$path'.");
        }

        return new self($id, $data, $colorComponents);
    }

    protected function streamDictionary(int | ReferenceType $length): DictionaryType
    {
        return new DictionaryType([
            'N' => $this->colorComponents,
            'Length' => $length,
        ]);
    }

    protected function writeStreamContents(PdfOutput $output): void
    {
        $this->data->writeTo($output);
    }

    protected function knownStreamLength(): int
    {
        return $this->data->length();
    }
}
