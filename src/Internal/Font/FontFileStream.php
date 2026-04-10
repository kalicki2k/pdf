<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Font;

use InvalidArgumentException;
use Kalle\Pdf\Binary\BinaryData;
use Kalle\Pdf\Object\StreamIndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use RuntimeException;

final class FontFileStream extends StreamIndirectObject
{
    private readonly BinaryData $data;
    private ?OpenTypeFontParser $parser = null;

    public function __construct(
        int $id,
        string | BinaryData $data,
        private readonly string $streamType = 'FontFile2',
        private readonly ?string $subtype = null,
    ) {
        parent::__construct($id);
        $this->data = is_string($data) ? BinaryData::fromString($data) : $data;
    }

    public static function fromPath(int $id, string $path): self
    {
        try {
            $data = BinaryData::fromFile($path);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException("Unable to read font file '$path'.");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'ttf', 'ttc' => new self($id, $data, 'FontFile2'),
            'otf' => new self($id, $data, 'FontFile3', 'OpenType'),
            default => throw new InvalidArgumentException("Unsupported font file extension '$extension'."),
        };
    }

    public function getStreamType(): string
    {
        return $this->streamType;
    }

    public function contents(): string
    {
        return $this->data->contents();
    }

    public function parser(): OpenTypeFontParser
    {
        return $this->parser ??= new OpenTypeFontParser($this->contents());
    }

    protected function streamDictionary(int $length): DictionaryType
    {
        $dictionary = new DictionaryType([
            'Length' => $length,
            'Length1' => $length,
        ]);

        if ($this->subtype !== null) {
            $dictionary->add('Subtype', new NameType($this->subtype));
        }

        return $dictionary;
    }

    protected function writeStreamContents(PdfOutput $output): void
    {
        $this->data->writeTo($output);
    }

    protected function streamLength(): int
    {
        return $this->data->length();
    }
}
