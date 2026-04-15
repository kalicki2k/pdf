<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document;

use InvalidArgumentException;

final readonly class OutlineDestination
{
    private function __construct(
        public string $type,
        public int $pageNumber,
        public ?string $namedDestination = null,
        public ?string $remoteFile = null,
        public ?float $x = null,
        public ?float $y = null,
        public ?float $left = null,
        public ?float $bottom = null,
        public ?float $right = null,
        public ?float $top = null,
        public bool $useGoToAction = false,
        public bool $newWindow = false,
    ) {
        if ($this->pageNumber < 1) {
            throw new InvalidArgumentException('Outline destination page number must be greater than zero.');
        }

        match ($this->type) {
            'xyz' => $this->assertXyz(),
            'fit' => null,
            'fitH' => $this->assertFitHorizontal(),
            'fitR' => $this->assertFitRectangle(),
            'named' => $this->assertNamedDestination(),
            default => throw new InvalidArgumentException('Unsupported outline destination type.'),
        };

        if ($this->remoteFile !== null && $this->remoteFile === '') {
            throw new InvalidArgumentException('Outline remote file path must not be empty.');
        }
    }

    public static function xyzPage(int $pageNumber): self
    {
        return new self('xyz', $pageNumber);
    }

    public static function xyz(int $pageNumber, float $x, float $y): self
    {
        return new self('xyz', $pageNumber, x: $x, y: $y);
    }

    public static function fit(int $pageNumber): self
    {
        return new self('fit', $pageNumber);
    }

    public static function fitHorizontal(int $pageNumber, float $top): self
    {
        return new self('fitH', $pageNumber, top: $top);
    }

    public static function fitRectangle(
        int $pageNumber,
        float $left,
        float $bottom,
        float $right,
        float $top,
    ): self {
        return new self('fitR', $pageNumber, left: $left, bottom: $bottom, right: $right, top: $top);
    }

    public static function named(string $name, int $pageNumber): self
    {
        return new self('named', $pageNumber, namedDestination: $name);
    }

    public function asGoToAction(): self
    {
        return new self(
            $this->type,
            $this->pageNumber,
            $this->namedDestination,
            $this->remoteFile,
            $this->x,
            $this->y,
            $this->left,
            $this->bottom,
            $this->right,
            $this->top,
            true,
            $this->newWindow,
        );
    }

    public function asRemoteGoTo(string $file, bool $newWindow = false): self
    {
        return new self(
            $this->type,
            $this->pageNumber,
            $this->namedDestination,
            $file,
            $this->x,
            $this->y,
            $this->left,
            $this->bottom,
            $this->right,
            $this->top,
            true,
            $newWindow,
        );
    }

    public function isXyz(): bool
    {
        return $this->type === 'xyz';
    }

    public function isFit(): bool
    {
        return $this->type === 'fit';
    }

    public function isFitHorizontal(): bool
    {
        return $this->type === 'fitH';
    }

    public function isFitRectangle(): bool
    {
        return $this->type === 'fitR';
    }

    public function isNamed(): bool
    {
        return $this->type === 'named';
    }

    public function isRemote(): bool
    {
        return $this->remoteFile !== null;
    }

    public function hasExplicitPosition(): bool
    {
        return $this->isXyz() && $this->x !== null && $this->y !== null;
    }

    private function assertXyz(): void
    {
        if (($this->x === null) !== ($this->y === null)) {
            throw new InvalidArgumentException('Outline XYZ destination coordinates must be provided together.');
        }
    }

    private function assertFitHorizontal(): void
    {
        if ($this->top === null) {
            throw new InvalidArgumentException('Outline FitH destination requires a top value.');
        }
    }

    private function assertFitRectangle(): void
    {
        if (
            $this->left === null
            || $this->bottom === null
            || $this->right === null
            || $this->top === null
        ) {
            throw new InvalidArgumentException('Outline FitR destination requires left, bottom, right and top values.');
        }
    }

    private function assertNamedDestination(): void
    {
        if (($this->namedDestination ?? '') === '') {
            throw new InvalidArgumentException('Outline named destination must not be empty.');
        }
    }
}
