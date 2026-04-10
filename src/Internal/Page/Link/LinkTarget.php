<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Page\Link;

use InvalidArgumentException;
use Kalle\Pdf\Internal\Page\Page;

final readonly class LinkTarget
{
    private function __construct(
        private ?string $externalUrl = null,
        private ?string $namedDestination = null,
        private ?Page $page = null,
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
            throw new InvalidArgumentException('Link target destination must not be empty.');
        }

        return new self(namedDestination: $name);
    }

    public static function page(Page $page): self
    {
        return new self(page: $page);
    }

    public static function position(Page $page, float $x, float $y): self
    {
        return new self(page: $page, x: $x, y: $y);
    }

    public function isExternalUrl(): bool
    {
        return $this->externalUrl !== null;
    }

    public function isNamedDestination(): bool
    {
        return $this->namedDestination !== null;
    }

    public function isPage(): bool
    {
        return $this->page !== null && $this->x === null && $this->y === null;
    }

    public function isPosition(): bool
    {
        return $this->page !== null && $this->x !== null && $this->y !== null;
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

    public function pageValue(): Page
    {
        if ($this->page === null) {
            throw new InvalidArgumentException('Link target does not contain a page destination.');
        }

        return $this->page;
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

    public function equals(self $other): bool
    {
        return $this->externalUrl === $other->externalUrl
            && $this->namedDestination === $other->namedDestination
            && $this->page === $other->page
            && $this->x === $other->x
            && $this->y === $other->y;
    }
}
