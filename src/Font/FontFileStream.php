<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;
use Kalle\Pdf\Document\BinaryData;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use RuntimeException;

final class FontFileStream extends IndirectObject
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

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'Length' => $this->data->length(),
            'Length1' => $this->data->length(),
        ]);

        if ($this->subtype !== null) {
            $dictionary->add('Subtype', new NameType($this->subtype));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $this->data->contents() . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
