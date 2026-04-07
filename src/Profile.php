<?php

declare(strict_types=1);

namespace Kalle\Pdf;

use InvalidArgumentException;

final readonly class Profile
{
    private function __construct(
        private string $name,
        private float $version,
    ) {
    }

    public static function standard(float $version = 1.4): self
    {
        self::assertSupportedStandardVersion($version);

        return new self('standard', $version);
    }

    public static function pdfA1a(): self
    {
        return new self('PDF/A-1a', 1.4); // PDF/A-1 is based on PDF 1.4.
    }

    public static function pdfA1b(): self
    {
        return new self('PDF/A-1b', 1.4); // PDF/A-1 is based on PDF 1.4.
    }

    public static function pdfA2a(): self
    {
        return new self('PDF/A-2a', 1.7); // PDF/A-2 is based on PDF 1.7.
    }

    public static function pdfA2b(): self
    {
        return new self('PDF/A-2b', 1.7); // PDF/A-2 is based on PDF 1.7.
    }

    public static function pdfA2u(): self
    {
        return new self('PDF/A-2u', 1.7); // PDF/A-2 is based on PDF 1.7.
    }

    public static function pdfA3a(): self
    {
        return new self('PDF/A-3a', 1.7); // PDF/A-3 is based on PDF 1.7.
    }

    public static function pdfA3b(): self
    {
        return new self('PDF/A-3b', 1.7); // PDF/A-3 is based on PDF 1.7.
    }

    public static function pdfA3u(): self
    {
        return new self('PDF/A-3u', 1.7); // PDF/A-3 is based on PDF 1.7.
    }

    public static function pdfA4(): self
    {
        return new self('PDF/A-4', 2.0); // PDF/A-4 is based on PDF 2.0.
    }

    public static function pdfA4e(): self
    {
        return new self('PDF/A-4e', 2.0); // PDF/A-4e is based on PDF 2.0.
    }

    public static function pdfA4f(): self
    {
        return new self('PDF/A-4f', 2.0); // PDF/A-4f is based on PDF 2.0.
    }

    public function name(): string
    {
        return $this->name;
    }

    public function version(): float
    {
        return $this->version;
    }

    public function isStandard(): bool
    {
        return $this->name === 'standard';
    }

    public function isPdfA(): bool
    {
        return str_starts_with($this->name, 'PDF/A-');
    }

    public function isPdfA2(): bool
    {
        return $this->pdfaPart() === 2;
    }

    public function isPdfA2u(): bool
    {
        return $this->name === 'PDF/A-2u';
    }

    public function pdfaPart(): ?int
    {
        return match ($this->name) {
            'PDF/A-1a', 'PDF/A-1b' => 1,
            'PDF/A-2a', 'PDF/A-2b', 'PDF/A-2u' => 2,
            'PDF/A-3a', 'PDF/A-3b', 'PDF/A-3u' => 3,
            'PDF/A-4', 'PDF/A-4e', 'PDF/A-4f' => 4,
            default => null,
        };
    }

    public function pdfaConformance(): ?string
    {
        return match ($this->name) {
            'PDF/A-1a', 'PDF/A-2a', 'PDF/A-3a' => 'A',
            'PDF/A-1b', 'PDF/A-2b', 'PDF/A-3b' => 'B',
            'PDF/A-2u', 'PDF/A-3u' => 'U',
            'PDF/A-4e' => 'E',
            'PDF/A-4f' => 'F',
            'PDF/A-4' => null,
            default => null,
        };
    }

    private static function assertSupportedStandardVersion(float $version): void
    {
        $supportedVersions = [1.0, 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.7, 2.0];

        if (!in_array($version, $supportedVersions, true)) {
            throw new InvalidArgumentException('Unsupported PDF version. Supported versions are 1.0 to 1.7 and 2.0.');
        }
    }
}
