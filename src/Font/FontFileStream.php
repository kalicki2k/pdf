<?php

declare(strict_types=1);

namespace Kalle\Pdf\Font;

use InvalidArgumentException;
use Kalle\Pdf\Document\BinaryData;
use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;
use RuntimeException;

final class FontFileStream extends IndirectObject implements EncryptableIndirectObject
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
        $data = $this->data->contents();

        return $this->id . ' 0 obj' . PHP_EOL
            . $this->dictionary(strlen($data))->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $data . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function write(PdfOutput $output): void
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
        $dictionary = new DictionaryType([
            'Length' => $length,
            'Length1' => $length,
        ]);

        if ($this->subtype !== null) {
            $dictionary->add('Subtype', new NameType($this->subtype));
        }

        return $dictionary;
    }
}
