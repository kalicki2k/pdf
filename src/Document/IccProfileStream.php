<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
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
        return $this->id . ' 0 obj' . PHP_EOL
            . $this->dictionary()->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $this->data->contents() . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function write(PdfOutput $output): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary()->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->data->writeTo($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    private function dictionary(): DictionaryType
    {
        return new DictionaryType([
            'N' => $this->colorComponents,
            'Length' => $this->data->length(),
        ]);
    }
}
