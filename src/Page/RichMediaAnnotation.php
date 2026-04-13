<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function implode;
use function strlen;

use InvalidArgumentException;
use Kalle\Pdf\Document\Attachment\EmbeddedFile;
use Kalle\Pdf\Writer\IndirectObject;

final readonly class RichMediaAnnotation implements AppearanceStreamAnnotation, PageAnnotation, RelatedObjectsPageAnnotation
{
    use FormatsPdfAnnotationValues;

    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $filename,
        public EmbeddedFile $embeddedFile,
        public RichMediaAssetType $assetType = RichMediaAssetType::VIDEO,
        public ?string $contents = null,
        public ?int $structParentId = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('RichMedia annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('RichMedia annotation height must be greater than zero.');
        }

        if ($this->filename === '') {
            throw new InvalidArgumentException('RichMedia annotation filename must not be empty.');
        }

        if ($this->embeddedFile->mimeType === null) {
            throw new InvalidArgumentException('RichMedia annotation embedded file MIME type must be configured.');
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            x: $this->x,
            y: $this->y,
            width: $this->width,
            height: $this->height,
            filename: $this->filename,
            embeddedFile: $this->embeddedFile,
            assetType: $this->assetType,
            contents: $this->contents,
            structParentId: $structParentId,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /RichMedia',
            '/Rect ' . $this->rect($this->x, $this->y, $this->width, $this->height),
            '/P ' . $context->pageObjectId . ' 0 R',
            '/RichMediaContent ' . $context->relatedObjectId(2) . ' 0 R',
            '/RichMediaSettings ' . $context->relatedObjectId(3) . ' 0 R',
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
            '0.95 g',
            '0 G',
            '1 w',
            '0 0 ' . $this->formatNumber($this->width) . ' ' . $this->formatNumber($this->height) . ' re',
            'B',
        ]);
    }

    public function relatedObjectCount(): int
    {
        return 4;
    }

    public function relatedObjects(PageAnnotationRenderContext $context): array
    {
        $embeddedFileObjectId = $context->relatedObjectId(0);
        $fileSpecObjectId = $context->relatedObjectId(1);
        $richMediaContentObjectId = $context->relatedObjectId(2);
        $richMediaSettingsObjectId = $context->relatedObjectId(3);

        return [
            IndirectObject::stream(
                $embeddedFileObjectId,
                $this->embeddedFileStreamDictionary(),
                $this->embeddedFile->contents,
            ),
            IndirectObject::plain(
                $fileSpecObjectId,
                $this->fileSpecDictionary($embeddedFileObjectId),
            ),
            IndirectObject::plain(
                $richMediaContentObjectId,
                $this->richMediaContentDictionary($fileSpecObjectId),
            ),
            IndirectObject::plain(
                $richMediaSettingsObjectId,
                $this->richMediaSettingsDictionary(),
            ),
        ];
    }

    private function embeddedFileStreamDictionary(): string
    {
        $size = $this->embeddedFile->size();

        return '<< /Type /EmbeddedFile /Length ' . $size
            . ' /Subtype /' . $this->pdfName($this->embeddedFile->mimeType ?? 'application/octet-stream')
            . ' /Params << /Size ' . $size . ' >> >>';
    }

    private function fileSpecDictionary(int $embeddedFileObjectId): string
    {
        $entries = [
            '/Type /Filespec',
            '/F ' . $this->pdfString($this->filename),
            '/UF ' . $this->pdfString($this->filename),
            '/EF << /F ' . $embeddedFileObjectId . ' 0 R /UF ' . $embeddedFileObjectId . ' 0 R >>',
        ];

        if ($this->contents !== null && $this->contents !== '') {
            $entries[] = '/Desc ' . $this->pdfString($this->contents);
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private function richMediaContentDictionary(int $fileSpecObjectId): string
    {
        return '<< /Assets << /Names [' . $this->pdfString($this->filename) . ' ' . $fileSpecObjectId . ' 0 R] >>'
            . ' /Configurations [<< /Type /RichMediaConfiguration /Subtype /' . $this->assetType->value
            . ' /Instances [<< /Type /RichMediaInstance /Asset ' . $fileSpecObjectId . ' 0 R >>] >>] >>';
    }

    private function richMediaSettingsDictionary(): string
    {
        return '<< /Activation << /Condition /XA /Presentation << /Style /Embedded >> >>'
            . ' /Deactivation << /Condition /XD >> >>';
    }
}
