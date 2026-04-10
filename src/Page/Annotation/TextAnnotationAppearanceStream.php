<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page\Annotation;

use Kalle\Pdf\Object\DeferredLengthStreamIndirectObject;
use Kalle\Pdf\PdfType\ArrayType;
use Kalle\Pdf\PdfType\DictionaryType;
use Kalle\Pdf\PdfType\NameType;
use Kalle\Pdf\PdfType\ReferenceType;
use Kalle\Pdf\Render\PdfOutput;

final class TextAnnotationAppearanceStream extends DeferredLengthStreamIndirectObject
{
    public function __construct(
        int $id,
        private readonly float $width,
        private readonly float $height,
    ) {
        parent::__construct($id);
    }

    protected function streamDictionary(int | ReferenceType $length): DictionaryType
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

    protected function writeStreamContents(PdfOutput $output): void
    {
        $this->writeLines($output, [
            '1 g',
            '0 G',
            '1 w',
            sprintf('0 0 %s %s re', $this->format($this->width), $this->format($this->height)),
            'B',
        ]);
    }

    private function format(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
