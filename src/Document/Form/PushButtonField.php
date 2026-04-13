<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Form;

use function count;
use function implode;
use function max;

use InvalidArgumentException;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentValidationException;
use Kalle\Pdf\Writer\IndirectObject;
use Override;

final readonly class PushButtonField extends WidgetFormField
{
    public function __construct(
        string $name,
        int $pageNumber,
        float $x,
        float $y,
        float $width,
        float $height,
        public string $label,
        ?string $alternativeName = null,
        public ?string $url = null,
        public float $fontSize = 12.0,
    ) {
        parent::__construct($name, $pageNumber, $x, $y, $width, $height, $alternativeName);

        if ($this->label === '') {
            throw new InvalidArgumentException('Push button label must not be empty.');
        }

        if ($this->fontSize <= 0.0) {
            throw new InvalidArgumentException('Push button font size must be greater than zero.');
        }

        if ($this->url === '') {
            throw new InvalidArgumentException('Push button URL must not be empty.');
        }
    }

    public function pdfObjectContents(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): string {
        if (count($relatedObjectIds) !== 1) {
            throw new DocumentValidationException(
                DocumentBuildError::BUILD_STATE_INVALID,
                'Push buttons require one appearance object ID.',
            );
        }

        $entries = [
            ...$this->widgetDictionaryEntries($context, $fieldObjectId),
            '/FT /Btn',
            '/Ff 65536',
            '/DA ' . $this->pdfString(
                $context->defaultTextFont !== null
                    ? $this->pdfAFieldDa($context, $this->fontSize)
                    : '/Helv ' . $this->formatNumber($this->fontSize) . ' Tf 0 g',
            ),
            '/AP << /N ' . $relatedObjectIds[0] . ' 0 R >>',
            '/MK << /CA ' . $this->pdfString($this->label) . ' >>',
        ];

        if ($this->url !== null) {
            $entries[] = '/A << /S /URI /URI ' . $this->pdfString($this->url) . ' >>';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    #[Override]
    public function relatedObjectCount(): int
    {
        return 1;
    }

    #[Override]
    public function relatedObjects(
        FormFieldRenderContext $context,
        int $fieldObjectId,
        array $relatedObjectIds = [],
    ): array {
        if (count($relatedObjectIds) !== 1) {
            throw new DocumentValidationException(
                DocumentBuildError::BUILD_STATE_INVALID,
                'Push buttons require one appearance object ID.',
            );
        }

        return [
            IndirectObject::stream(
                $relatedObjectIds[0],
                $this->appearanceStreamDictionaryContents($context),
                $this->appearanceStreamContents($context),
            ),
        ];
    }

    #[Override]
    public function needsDefaultTextResources(): bool
    {
        return true;
    }

    private function appearanceStreamDictionaryContents(FormFieldRenderContext $context): string
    {
        if ($context->defaultTextFont !== null) {
            return $this->renderPdfAAppearanceDictionary($context, $this->width, $this->height);
        }

        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($this->width)
            . ' '
            . $this->formatNumber($this->height)
            . '] /Resources << >> /Length 0 >>';
    }

    private function appearanceStreamContents(FormFieldRenderContext $context): string
    {
        if ($context->defaultTextFont !== null) {
            return implode("\n", [
                '0.95 g',
                '0 G',
                '1 w',
                '0 0 ' . $this->formatNumber($this->width) . ' ' . $this->formatNumber($this->height) . ' re',
                'B',
                'BT',
                '/' . $context->requiresDefaultTextFontAlias() . ' ' . $this->formatNumber($this->fontSize) . ' Tf',
                '0 g',
                '4 ' . $this->formatNumber(max(2.0, ($this->height - $this->fontSize) / 2)) . ' Td',
                '<' . $this->pdfAEncodedTextHex($context, $this->label) . '> Tj',
                'ET',
            ]);
        }

        return implode("\n", [
            '1 g',
            '0 G',
            '1 w',
            '0 0 ' . $this->formatNumber($this->width) . ' ' . $this->formatNumber($this->height) . ' re',
            'B',
        ]);
    }
}
