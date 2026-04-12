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
        public ?int $markedContentId = null,
        public ?int $structParentId = null,
        public ?string $taggedGroupKey = null,
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
            markedContentId: $this->markedContentId,
            structParentId: $structParentId,
            taggedGroupKey: $this->taggedGroupKey,
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
        } elseif ($this->target->isNamedDestination()) {
            $entries[] = '/Dest /' . $this->pdfName($this->target->namedDestinationValue());
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

        if ($context->appearanceObjectId !== null) {
            $entries[] = '/AP << /N ' . $context->appearanceObjectId . ' 0 R >>';
        }

        return '<< ' . implode(' ', $entries) . ' >>';
    }

    public function markedContentId(): ?int
    {
        return $this->markedContentId;
    }

    public function appearanceStreamDictionaryContents(): string
    {
        return '<< /Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 '
            . $this->formatNumber($this->width) . ' '
            . $this->formatNumber($this->height)
            . '] /Resources << >> /Length 0 >>';
    }

    public function appearanceStreamContents(): string
    {
        return '';
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

    private function pdfName(string $value): string
    {
        $encoded = '';

        foreach (str_split($value) as $character) {
            $ord = ord($character);

            if (
                ($ord >= 48 && $ord <= 57)
                || ($ord >= 65 && $ord <= 90)
                || ($ord >= 97 && $ord <= 122)
                || $character === '-'
                || $character === '_'
                || $character === '.'
            ) {
                $encoded .= $character;

                continue;
            }

            $encoded .= '#' . strtoupper(str_pad(dechex($ord), 2, '0', STR_PAD_LEFT));
        }

        return $encoded;
    }
}
