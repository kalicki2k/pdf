<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\DictionaryType;
use RuntimeException;

final class IccProfileStream extends IndirectObject implements EncryptableIndirectObject
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

    protected function writeObject(PdfOutput $output): void
    {
        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary($this->data->length())->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $this->data->writeTo($output);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $encryptedData = $objectEncryptor->encryptString($this->id, $this->data->contents());

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary(strlen($encryptedData))->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $output->write($encryptedData);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    private function dictionary(int $length): DictionaryType
    {
        return new DictionaryType([
            'N' => $this->colorComponents,
            'Length' => $length,
        ]);
    }
}
