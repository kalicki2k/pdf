<?php

declare(strict_types=1);

namespace Kalle\Pdf\Text;

use Kalle\Pdf\Page\PageContent;

/**
 * Immutable positioned text content for a page.
 */
final readonly class Text implements PageContent
{
    /**
     * Creates a positioned text value object.
     */
    public static function make(
        string $value,
        float $x,
        float $y,
    ): self {
        return new self(
            value: $value,
            x: $x,
            y: $y,
        );
    }

    /**
     * @param string $value Raw text value before PDF serialization.
     * @param float $x Horizontal page position in PDF points.
     * @param float $y Vertical page position in PDF points.
     */
    private function __construct(
        public string $value,
        public float $x,
        public float $y,
    ) {
    }
}
