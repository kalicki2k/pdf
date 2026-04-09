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

final class RadioButtonAppearanceStream extends IndirectObject implements EncryptableIndirectObject
{
    private const KAPPA = 0.5522847498;

    public function __construct(
        int $id,
        private readonly float $size,
        private readonly bool $checked,
    ) {
        parent::__construct($id);
    }

    protected function writeObject(PdfOutput $output): void
    {
        $radius = $this->size / 2;
        $content = $this->content($radius);

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary(strlen($content))->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $output->write($content);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    public function writeEncrypted(PdfOutput $output, StandardObjectEncryptor $objectEncryptor): void
    {
        $encryptedContent = $objectEncryptor->encryptString($this->id, $this->content($this->size / 2));

        $output->write($this->id . ' 0 obj' . PHP_EOL);
        $output->write($this->dictionary(strlen($encryptedContent))->render() . PHP_EOL);
        $output->write('stream' . PHP_EOL);
        $output->write($encryptedContent);
        $output->write(PHP_EOL . 'endstream' . PHP_EOL . 'endobj' . PHP_EOL);
    }

    private function content(float $radius): string
    {
        return implode(PHP_EOL, array_filter([
            '1 g',
            '0 G',
            '1 w',
            $this->buildCirclePath($radius, $radius, $radius - 0.5),
            'B',
            $this->checked ? '0 g' : null,
            $this->checked ? $this->buildCirclePath($radius, $radius, $radius * 0.45) : null,
            $this->checked ? 'f' : null,
        ]));
    }

    private function dictionary(int $length): DictionaryType
    {
        return new DictionaryType([
            'Type' => new NameType('XObject'),
            'Subtype' => new NameType('Form'),
            'FormType' => 1,
            'BBox' => new ArrayType([0, 0, $this->size, $this->size]),
            'Resources' => new DictionaryType([]),
            'Length' => $length,
        ]);
    }

    private function buildCirclePath(float $centerX, float $centerY, float $radius): string
    {
        $kappa = $radius * self::KAPPA;

        return implode(PHP_EOL, [
            sprintf('%s %s m', $this->format($centerX), $this->format($centerY + $radius)),
            sprintf(
                '%s %s %s %s %s %s c',
                $this->format($centerX + $kappa),
                $this->format($centerY + $radius),
                $this->format($centerX + $radius),
                $this->format($centerY + $kappa),
                $this->format($centerX + $radius),
                $this->format($centerY),
            ),
            sprintf(
                '%s %s %s %s %s %s c',
                $this->format($centerX + $radius),
                $this->format($centerY - $kappa),
                $this->format($centerX + $kappa),
                $this->format($centerY - $radius),
                $this->format($centerX),
                $this->format($centerY - $radius),
            ),
            sprintf(
                '%s %s %s %s %s %s c',
                $this->format($centerX - $kappa),
                $this->format($centerY - $radius),
                $this->format($centerX - $radius),
                $this->format($centerY - $kappa),
                $this->format($centerX - $radius),
                $this->format($centerY),
            ),
            sprintf(
                '%s %s %s %s %s %s c',
                $this->format($centerX - $radius),
                $this->format($centerY + $kappa),
                $this->format($centerX - $kappa),
                $this->format($centerY + $radius),
                $this->format($centerX),
                $this->format($centerY + $radius),
            ),
        ]);
    }

    private function format(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
