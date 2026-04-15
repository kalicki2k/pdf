<?php

declare(strict_types=1);

namespace Kalle\Pdf\Page;

use InvalidArgumentException;

final readonly class LinkTarget
{
    private function __construct(
        private ?string $externalUrl = null,
        private ?string $namedDestination = null,
        private ?int $pageNumber = null,
        private ?float $x = null,
        private ?float $y = null,
    ) {
    }

    public static function externalUrl(string $url): self
    {
        if ($url === '') {
            throw new InvalidArgumentException('Link target URL must not be empty.');
        }

        return new self(externalUrl: $url);
    }

    public static function namedDestination(string $name): self
    {
        if ($name === '') {
            throw new InvalidArgumentException('Link target destination name must not be empty.');
        }

        return new self(namedDestination: $name);
    }

    public static function page(int $pageNumber): self
    {
        if ($pageNumber < 1) {
            throw new InvalidArgumentException('Link target page number must be greater than zero.');
        }

        return new self(pageNumber: $pageNumber);
    }

    public static function position(int $pageNumber, float $x, float $y): self
    {
        if ($pageNumber < 1) {
            throw new InvalidArgumentException('Link target page number must be greater than zero.');
        }

        return new self(pageNumber: $pageNumber, x: $x, y: $y);
    }

    public function isExternalUrl(): bool
    {
        return $this->externalUrl !== null;
    }

    public function isPage(): bool
    {
        return $this->pageNumber !== null && $this->x === null && $this->y === null;
    }

    public function isNamedDestination(): bool
    {
        return $this->namedDestination !== null;
    }

    public function isPosition(): bool
    {
        return $this->pageNumber !== null && $this->x !== null && $this->y !== null;
    }

    public function externalUrlValue(): string
    {
        if ($this->externalUrl === null) {
            throw new InvalidArgumentException('Link target does not contain an external URL.');
        }

        return $this->externalUrl;
    }

    public function namedDestinationValue(): string
    {
        if ($this->namedDestination === null) {
            throw new InvalidArgumentException('Link target does not contain a named destination.');
        }

        return $this->namedDestination;
    }

    public function pageNumberValue(): int
    {
        if ($this->pageNumber === null) {
            throw new InvalidArgumentException('Link target does not contain a page target.');
        }

        return $this->pageNumber;
    }

    public function xValue(): float
    {
        if ($this->x === null) {
            throw new InvalidArgumentException('Link target does not contain an x coordinate.');
        }

        return $this->x;
    }

    public function yValue(): float
    {
        if ($this->y === null) {
            throw new InvalidArgumentException('Link target does not contain a y coordinate.');
        }

        return $this->y;
    }
}
