<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use function implode;

use InvalidArgumentException;

use function number_format;
use function rtrim;
use function sprintf;
use function str_replace;
use function str_split;
use function strlen;

final readonly class TextAnnotation implements AppearanceStreamAnnotation, PageAnnotation
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
        public string $contents,
        public ?string $title = null,
        public string $icon = 'Note',
        public bool $open = false,
        public ?int $structParentId = null,
    ) {
        if ($this->width <= 0.0) {
            throw new InvalidArgumentException('Text annotation width must be greater than zero.');
        }

        if ($this->height <= 0.0) {
            throw new InvalidArgumentException('Text annotation height must be greater than zero.');
        }

        if ($this->contents === '') {
            throw new InvalidArgumentException('Text annotation contents must not be empty.');
        }
    }

    public function withStructParent(int $structParentId): self
    {
        return new self(
            x: $this->x,
            y: $this->y,
            width: $this->width,
            height: $this->height,
            contents: $this->contents,
            title: $this->title,
            icon: $this->icon,
            open: $this->open,
            structParentId: $structParentId,
        );
    }

    public function pdfObjectContents(PageAnnotationRenderContext $context): string
    {
        $entries = [
            '/Type /Annot',
            '/Subtype /Text',
            '/Rect [' . $this->formatNumber($this->x) . ' '
            . $this->formatNumber($this->y) . ' '
            . $this->formatNumber($this->x + $this->width) . ' '
            . $this->formatNumber($this->y + $this->height) . ']',
            '/P ' . $context->pageObjectId . ' 0 R',
            '/Contents ' . $this->pdfString($this->contents),
            '/Name /' . $this->pdfName($this->icon),
            '/Open ' . ($this->open ? 'true' : 'false'),
        ];

        $structParentId = $this->structParentId ?? $context->structParentId;

        if ($structParentId !== null) {
            $entries[] = '/StructParent ' . $structParentId;
        }

        if ($context->printable) {
            $entries[] = '/F 4';
        }

        if ($this->title !== null && $this->title !== '') {
            $entries[] = '/T ' . $this->pdfString($this->title);
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
        return "1 g\n0 G\n1 w\n0 0 "
            . $this->formatNumber($this->width) . ' '
            . $this->formatNumber($this->height)
            . " re\nB";
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

            $encoded .= sprintf('#%02X', $ord);
        }

        return $encoded;
    }
}
