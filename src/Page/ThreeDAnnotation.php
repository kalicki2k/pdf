<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function implode;
use function strlen;

use InvalidArgumentException;
use Kalle\Pdf\Writer\IndirectObject;

final readonly class ThreeDAnnotation implements AppearanceStreamAnnotation, PageAnnotation, RelatedObjectsPageAnnotation
{
    use FormatsPdfAnnotationValues;

    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $data,
        public ThreeDAssetType $assetType = ThreeDAssetType::U3D,
        public ?string $contents = null,
        public ?int $structParentId = null,
        public ThreeDViewPreset $viewPreset = ThreeDViewPreset::DEFAULT,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('3D annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('3D annotation height must be greater than zero.');
        }

        if ($this->data === '') {
            throw new InvalidArgumentException('3D annotation data must not be empty.');
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            x: $this->x,
            y: $this->y,
            width: $this->width,
            height: $this->height,
            data: $this->data,
            assetType: $this->assetType,
            contents: $this->contents,
            structParentId: $structParentId,
            viewPreset: $this->viewPreset,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /3D',
            '/Rect ' . $this->rect($this->x, $this->y, $this->width, $this->height),
            '/P ' . $context->pageObjectId . ' 0 R',
            '/3DD ' . $context->relatedObjectId(0) . ' 0 R',
            '/3DV /' . $this->viewPreset->value,
        ];

        $structParentId = $this->structParentId ?? $context->structParentId;

        if ($structParentId !== null) {
            $entries[] = '/StructParent ' . $structParentId;
        }

        if ($context->printable) {
            $entries[] = '/F 4';
        }

        if ($this->contents !== null && $this->contents !== '') {
            $entries[] = '/Contents ' . $this->pdfString($this->contents);
        }

        if ($context->appearanceObjectId !== null) {
            $entries[] = '/AP << /N ' . $context->appearanceObjectId . ' 0 R >>';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    public function markedContentId(): ?int
    {
        return null;
    }

    public function appearanceStreamDictionaryContents(?AnnotationAppearanceRenderContext $context = null): string
    {
        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($this->width) . ' '
            . $this->formatNumber($this->height)
            . '] /Resources << >> /Length '
            . strlen($this->appearanceStreamContents())
            . ' >>';
    }

    public function appearanceStreamContents(?AnnotationAppearanceRenderContext $context = null): string
    {
        return implode("\n", [
            '0.9 g',
            '0 G',
            '1 w',
            '0 0 ' . $this->formatNumber($this->width) . ' ' . $this->formatNumber($this->height) . ' re',
            'B',
        ]);
    }

    public function relatedObjectCount(): int
    {
        return 1;
    }

    public function relatedObjects(PageAnnotationRenderContext $context): array
    {
        return [
            IndirectObject::stream(
                $context->relatedObjectId(0),
                '<< /Type /3D /Subtype /' . $this->assetType->value . ' /Length ' . strlen($this->data) . ' >>',
                $this->data,
            ),
        ];
    }
}
