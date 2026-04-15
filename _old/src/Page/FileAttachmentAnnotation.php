<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function implode;

use InvalidArgumentException;

final readonly class FileAttachmentAnnotation implements PageAnnotation, PdfUaTaggedPageAnnotation
{
    use FormatsPdfAnnotationValues;

    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $attachmentFilename,
        public string $icon = 'PushPin',
        public ?string $contents = null,
        public ?int $structParentId = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('File attachment annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('File attachment annotation height must be greater than zero.');
        }

        if ($this->attachmentFilename === '') {
            throw new InvalidArgumentException('File attachment annotation filename must not be empty.');
        }

        if ($this->icon === '') {
            throw new InvalidArgumentException('File attachment annotation icon must not be empty.');
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            x: $this->x,
            y: $this->y,
            width: $this->width,
            height: $this->height,
            attachmentFilename: $this->attachmentFilename,
            icon: $this->icon,
            contents: $this->contents,
            structParentId: $structParentId,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /FileAttachment',
            '/Rect ' . $this->rect($this->x, $this->y, $this->width, $this->height),
            '/P ' . $context->pageObjectId . ' 0 R',
            '/FS ' . $context->attachmentObjectId($this->attachmentFilename) . ' 0 R',
            '/Name /' . $this->pdfName($this->icon),
        ];

        $structParentId = $this->structParentId ?? $context->structParentId;

        if ($structParentId !== null) {
            $entries[] = '/StructParent ' . $structParentId;
        }

        if ($this->contents !== null && $this->contents !== '') {
            $entries[] = '/Contents ' . $this->pdfString($this->contents);
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    public function markedContentId(): ?int
    {
        return null;
    }

    public function taggedAnnotationAltText(): ?string
    {
        return $this->contents;
    }

    public function taggedAnnotationStructureTag(): string
    {
        return 'Annot';
    }
}
