<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

use function implode;
use function sprintf;
use function str_replace;

final readonly class LinkAnnotation implements PageAnnotation
{
    public function __construct(
        public LinkTarget $target,
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public ?string $contents = null,
        public ?int $structParentId = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Link annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('Link annotation height must be greater than zero.');
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            target: $this->target,
            x: $this->x,
            y: $this->y,
            width: $this->width,
            height: $this->height,
            contents: $this->contents,
            structParentId: $structParentId,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /Link',
            '/Rect [' . $this->formatNumber($this->x) . ' '
            . $this->formatNumber($this->y) . ' '
            . $this->formatNumber($this->x + $this->width) . ' '
            . $this->formatNumber($this->y + $this->height) . ']',
            '/Border [0 0 0]',
            '/P ' . $context->pageObjectId . ' 0 R',
        ];

        $structParentId = $this->structParentId ?? $context->structParentId;

        if ($structParentId !== null) {
            $entries[] = '/StructParent ' . $structParentId;
        }

        if ($this->target->isExternalUrl()) {
            $entries[] = '/A << /S /URI /URI ' . $this->pdfString($this->target->externalUrlValue()) . ' >>';
        } elseif ($this->target->isPage()) {
            $entries[] = '/Dest [' . $context->targetPageObjectId($this->target->pageNumberValue()) . ' 0 R /Fit]';
        } elseif ($this->target->isPosition()) {
            $entries[] = '/Dest [' . $context->targetPageObjectId($this->target->pageNumberValue()) . ' 0 R /XYZ '
                . $this->formatNumber($this->target->xValue()) . ' '
                . $this->formatNumber($this->target->yValue()) . ' null]';
        } else {
            throw new InvalidArgumentException(sprintf('Unsupported link annotation target.'));
        }

        if ($context->printable) {
            $entries[] = '/F 4';
        }

        if ($this->contents !== null && $this->contents !== '') {
            $entries[] = '/Contents ' . $this->pdfString($this->contents);
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    private function formatNumber(float $value): string
    {
        $formatted = number_format($value, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private function pdfString(string $value): string
    {
        return '(' . str_replace(
            ['\\', '(', ')'],
            ['\\\\', '\(', '\)'],
            $value,
        ) . ')';
    }
}
