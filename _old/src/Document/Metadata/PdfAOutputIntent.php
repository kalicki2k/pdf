<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Metadata;

use InvalidArgumentException;

final readonly class PdfAOutputIntent
{
    public function __construct(
        public string $iccProfilePath,
        public string $outputConditionIdentifier = 'sRGB IEC61966-2.1',
        public ?string $info = 'sRGB IEC61966-2.1',
        public int $colorComponents = 3,
    ) {
        if ($this->iccProfilePath === '') {
            throw new InvalidArgumentException('PDF/A output intents require an ICC profile path.');
        }

        if ($this->outputConditionIdentifier === '') {
            throw new InvalidArgumentException('PDF/A output intents require an output condition identifier.');
        }

        if ($this->colorComponents < 1) {
            throw new InvalidArgumentException('PDF/A output intents require at least one color component.');
        }
    }

    public static function defaultSrgb(): self
    {
        return new self(IccProfile::defaultSrgbPath());
    }

    public static function defaultCmyk(): self
    {
        return new self(
            IccProfile::defaultCmykPath(),
            'Artifex CMYK SWOP Profile',
            'Artifex CMYK SWOP Profile',
            4,
        );
    }
}
