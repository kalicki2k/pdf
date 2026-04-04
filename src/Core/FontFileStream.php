<?php

declare(strict_types=1);

namespace Kalle\Pdf\Core;

use InvalidArgumentException;
use Kalle\Pdf\Types\Dictionary;
use Kalle\Pdf\Types\Name;

final class FontFileStream extends IndirectObject
{
    public function __construct(
        int $id,
        public readonly string $data,
        private readonly string $streamType = 'FontFile2',
        private readonly ?string $subtype = null,
    ) {
        parent::__construct($id);
    }

    public static function fromPath(int $id, string $path): self
    {
        $data = file_get_contents($path);

        if ($data === false) {
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

    public function render(): string
    {
        $dictionary = new Dictionary([
            'Length' => strlen($this->data),
            'Length1' => strlen($this->data),
        ]);

        if ($this->subtype !== null) {
            $dictionary->add('Subtype', new Name($this->subtype));
        }

        return $this->id . ' 0 obj' . PHP_EOL
            . $dictionary->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $this->data . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }
}
