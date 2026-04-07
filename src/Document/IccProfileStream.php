<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Types\DictionaryType;

final class IccProfileStream extends IndirectObject
{
    public function __construct(
        int $id,
        private readonly string $data,
        private readonly int $colorComponents = 3,
    ) {
        parent::__construct($id);
    }

    public static function fromPath(int $id, string $path, int $colorComponents = 3): self
    {
        $data = @file_get_contents($path);

        if ($data === false) {
            throw new InvalidArgumentException("Unable to read ICC profile '$path'.");
        }

        return new self($id, $data, $colorComponents);
    }

    public function render(): string
    {
        $dictionary = new DictionaryType([
            'N' => $this->colorComponents,
            'Length' => strlen($this->data),
        ]);

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $this->data . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
