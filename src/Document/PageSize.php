<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

/**
 * Immutable page size value object used by Document::addPage().
 *
 * All dimensions are expressed in PDF points / user units as used by the rest
 * of the document API.
 */
final readonly class PageSize
{
    /**
     * @throws InvalidArgumentException If width or height is not greater than zero.
     */
    private function __construct(
        private float $width,
        private float $height,
    ) {
        if ($this->width <= 0 || $this->height <= 0) {
            throw new InvalidArgumentException('Page width and height must be greater than zero.');
        }
    }

    /**
     * ISO 216 A0 in portrait orientation.
     */
    public static function A0(): self
    {
        return new self(841.0, 1189.0);
    }

    /**
     * Supplemental ISO 216 A00 in portrait orientation.
     */
    public static function A00(): self
    {
        return new self(1189.0, 1682.0);
    }

    /**
     * ISO 216 A1 in portrait orientation.
     */
    public static function A1(): self
    {
        return new self(594.0, 841.0);
    }

    /**
     * ISO 216 A2 in portrait orientation.
     */
    public static function A2(): self
    {
        return new self(420.0, 594.0);
    }

    /**
     * ISO 216 A3 in portrait orientation.
     */
    public static function A3(): self
    {
        return new self(297.0, 420.0);
    }

    /**
     * ISO 216 A4 in portrait orientation.
     */
    public static function A4(): self
    {
        return new self(210.0, 297.0);
    }

    /**
     * ISO 216 A5 in portrait orientation.
     */
    public static function A5(): self
    {
        return new self(148.0, 210.0);
    }

    /**
     * ISO 216 A6 in portrait orientation.
     */
    public static function A6(): self
    {
        return new self(105.0, 148.0);
    }

    /**
     * ISO 216 A7 in portrait orientation.
     */
    public static function A7(): self
    {
        return new self(74.0, 105.0);
    }

    /**
     * ISO 216 A8 in portrait orientation.
     */
    public static function A8(): self
    {
        return new self(52.0, 74.0);
    }

    /**
     * ISO 216 A9 in portrait orientation.
     */
    public static function A9(): self
    {
        return new self(37.0, 52.0);
    }

    /**
     * ISO 216 B0 in portrait orientation.
     */
    public static function B0(): self
    {
        return new self(1000.0, 1414.0);
    }

    /**
     * ISO 216 B1 in portrait orientation.
     */
    public static function B1(): self
    {
        return new self(707.0, 1000.0);
    }

    /**
     * ISO 216 B2 in portrait orientation.
     */
    public static function B2(): self
    {
        return new self(500.0, 707.0);
    }

    /**
     * ISO 216 B3 in portrait orientation.
     */
    public static function B3(): self
    {
        return new self(353.0, 500.0);
    }

    /**
     * ISO 216 B4 in portrait orientation.
     */
    public static function B4(): self
    {
        return new self(250.0, 353.0);
    }

    /**
     * ISO 216 B5 in portrait orientation.
     */
    public static function B5(): self
    {
        return new self(176.0, 250.0);
    }

    /**
     * ISO 216 B6 in portrait orientation.
     */
    public static function B6(): self
    {
        return new self(125.0, 176.0);
    }

    /**
     * ISO 216 B7 in portrait orientation.
     */
    public static function B7(): self
    {
        return new self(88.0, 125.0);
    }

    /**
     * ISO 216 B8 in portrait orientation.
     */
    public static function B8(): self
    {
        return new self(62.0, 88.0);
    }

    /**
     * ISO 216 B9 in portrait orientation.
     */
    public static function B9(): self
    {
        return new self(44.0, 62.0);
    }

    /**
     * ISO 216 B10 in portrait orientation.
     */
    public static function B10(): self
    {
        return new self(31.0, 44.0);
    }

    /**
     * ISO 269 C0 in portrait orientation.
     */
    public static function C0(): self
    {
        return new self(917.0, 1297.0);
    }

    /**
     * ISO 269 C1 in portrait orientation.
     */
    public static function C1(): self
    {
        return new self(648.0, 917.0);
    }

    /**
     * ISO 269 C2 in portrait orientation.
     */
    public static function C2(): self
    {
        return new self(458.0, 648.0);
    }

    /**
     * ISO 269 C3 in portrait orientation.
     */
    public static function C3(): self
    {
        return new self(324.0, 458.0);
    }

    /**
     * ISO 269 C4 in portrait orientation.
     */
    public static function C4(): self
    {
        return new self(229.0, 324.0);
    }

    /**
     * ISO 269 C5 in portrait orientation.
     */
    public static function C5(): self
    {
        return new self(162.0, 229.0);
    }

    /**
     * ISO 269 C6 in portrait orientation.
     */
    public static function C6(): self
    {
        return new self(114.0, 162.0);
    }

    /**
     * ISO 269 C7 in portrait orientation.
     */
    public static function C7(): self
    {
        return new self(81.0, 114.0);
    }

    /**
     * ISO 269 C8 in portrait orientation.
     */
    public static function C8(): self
    {
        return new self(57.0, 81.0);
    }

    /**
     * ISO 269 C9 in portrait orientation.
     */
    public static function C9(): self
    {
        return new self(40.0, 57.0);
    }

    /**
     * ISO 269 C10 in portrait orientation.
     */
    public static function C10(): self
    {
        return new self(28.0, 40.0);
    }

    /**
     * Creates a custom page size.
     *
     * @throws InvalidArgumentException If width or height is not greater than zero.
     */
    public static function custom(float $width, float $height): self
    {
        return new self($width, $height);
    }

    /**
     * Returns the page width.
     */
    public function width(): float
    {
        return $this->width;
    }

    /**
     * Returns the page height.
     */
    public function height(): float
    {
        return $this->height;
    }

    /**
     * Returns the same size in landscape orientation.
     */
    public function landscape(): self
    {
        if ($this->width >= $this->height) {
            return $this;
        }

        return new self($this->height, $this->width);
    }

    /**
     * Returns the same size in portrait orientation.
     */
    public function portrait(): self
    {
        if ($this->height >= $this->width) {
            return $this;
        }

        return new self($this->height, $this->width);
    }
}
