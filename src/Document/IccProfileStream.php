<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;
use RuntimeException;

final class IccProfileStream extends IndirectObject
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

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'N' => $this->colorComponents,
            'Length' => $this->data->length(),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $this->data->contents() . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
