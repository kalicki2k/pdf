<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use Kalle\Pdf\Encryption\StandardObjectEncryptor;
use Kalle\Pdf\Object\EncryptableIndirectObject;
use Kalle\Pdf\Object\IndirectObject;
use Kalle\Pdf\Render\PdfOutput;
use Kalle\Pdf\Types\ArrayType;
use Kalle\Pdf\Types\DictionaryType;
use Kalle\Pdf\Types\NameType;

final class CheckboxAppearanceStream extends IndirectObject implements EncryptableIndirectObject
{
    public function __construct(
        int $id,
        private readonly float $width,
        private readonly float $height,
        private readonly bool $checked,
    ) {
        parent::__construct($id);
    }

    public function render(): string
    {
        $content = $this->content();

        return $this->id . ' 0 obj' . PHP_EOL
            . $this->dictionary(strlen($content))->render() . PHP_EOL
            . 'stream' . PHP_EOL
            . $content . PHP_EOL
            . 'endstream' . PHP_EOL
            . 'endobj' . PHP_EOL;
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $encryptedContent = $objectEncryptor->encryptString($this->id, $this->content());

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary(strlen($encryptedContent))->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $output->write($encryptedContent);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    private function content(): string
    {
        return implode(PHP_EOL, array_filter([
            '1 g',
            '0 G',
            '1 w',
            sprintf('0 0 %s %s re', $this->format($this->width), $this->format($this->height)),
            'B',
            $this->checked
                ? implode(PHP_EOL, [
                    sprintf(
                        '%s %s m',
                        $this->format($this->width * 0.2),
                        $this->format($this->height * 0.55),
                    ),
                    sprintf(
                        '%s %s l',
                        $this->format($this->width * 0.42),
                        $this->format($this->height * 0.25),
                    ),
                    sprintf(
                        '%s %s m',
                        $this->format($this->width * 0.42),
                        $this->format($this->height * 0.25),
                    ),
                    sprintf(
                        '%s %s l',
                        $this->format($this->width * 0.8),
                        $this->format($this->height * 0.8),
                    ),
                ])
                : null,
            $this->checked ? 'S' : null,
        ]));
    }

    private function dictionary(int $length): DictionaryType
    {
        return new DictionaryType([
            'Type' => new NameType('XObject'),
            'Subtype' => new NameType('Form'),
            'FormType' => 1,
            'BBox' => new ArrayType([0, 0, $this->width, $this->height]),
            'Resources' => new DictionaryType([]),
            'Length' => $length,
        ]);
    }

    private function format(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
