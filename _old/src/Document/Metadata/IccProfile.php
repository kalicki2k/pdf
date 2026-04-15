<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Metadata;

use function dirname;
use function file_get_contents;
use function is_string;
use function sprintf;
use function str_contains;
use function strlen;
use function substr;

use InvalidArgumentException;
use Kalle\Pdf\Document\DocumentBuildError;
use Kalle\Pdf\Document\DocumentValidationException;

final readonly class IccProfile
{
    public function __construct(
        private string $data,
        private int $colorComponents = 3,
    ) {
        if ($this->colorComponents < 1) {
            throw new InvalidArgumentException('ICC profiles require at least one color component.');
        }
    }

    public static function defaultSrgbPath(): string
    {
        return dirname(__DIR__, 3) . '/assets/color/icc/sRGB.icc';
    }

    public static function defaultCmykPath(): string
    {
        return dirname(__DIR__, 3) . '/assets/color/icc/default_cmyk.icc';
    }

    public static function fromPath(string $path, int $colorComponents = 3): self
    {
        $data = @file_get_contents($path);

        if (!is_string($data)) {
            throw new DocumentValidationException(
                DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID,
                sprintf("Unable to read ICC profile '%s'.", $path),
            );
        }

        return new self($data, $colorComponents);
    }

    public function objectContents(): string
    {
        return $this->streamDictionaryContents() . "\nstream\n"
            . $this->streamContents()
            . "\nendstream";
    }

    public function streamDictionaryContents(): string
    {
        return '<< /N ' . $this->colorComponents . ' /Length ' . strlen($this->data) . ' >>';
    }

    public function streamContents(): string
    {
        return $this->data;
    }

    public function assertPdfA1Compatible(PdfAOutputIntent $outputIntent): void
    {
        if (strlen($this->data) < 132) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID, sprintf(
                'ICC profile "%s" is too short to be a valid PDF/A output intent profile.',
                $outputIntent->iccProfilePath,
            ));
        }

        $declaredLength = unpack('N', substr($this->data, 0, 4))[1] ?? 0;

        if ($declaredLength < 132 || $declaredLength > strlen($this->data)) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID, sprintf(
                'ICC profile "%s" declares an invalid profile length.',
                $outputIntent->iccProfilePath,
            ));
        }

        if (substr($this->data, 36, 4) !== 'acsp') {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID, sprintf(
                'ICC profile "%s" is missing the ICC signature.',
                $outputIntent->iccProfilePath,
            ));
        }

        if (!in_array($outputIntent->colorComponents, [1, 3, 4], true)) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID, sprintf(
                'ICC profile "%s" uses unsupported PDF/A component count %d.',
                $outputIntent->iccProfilePath,
                $outputIntent->colorComponents,
            ));
        }

        $deviceClass = substr($this->data, 12, 4);
        $colorSpace = substr($this->data, 16, 4);

        if (!in_array($deviceClass, ['mntr', 'prtr', 'spac'], true)) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID, sprintf(
                'ICC profile "%s" uses unsupported device class "%s" for PDF/A-1.',
                $outputIntent->iccProfilePath,
                $deviceClass,
            ));
        }

        $expectedColorSpace = match ($outputIntent->colorComponents) {
            1 => 'GRAY',
            3 => 'RGB ',
            4 => 'CMYK',
        };

        if ($colorSpace !== $expectedColorSpace) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID, sprintf(
                'ICC profile "%s" color space "%s" does not match the PDF/A output intent component count %d.',
                $outputIntent->iccProfilePath,
                trim($colorSpace),
                $outputIntent->colorComponents,
            ));
        }

        if (
            $outputIntent->colorComponents === 3
            && !str_contains(strtolower($outputIntent->outputConditionIdentifier), 'rgb')
            && !str_contains(strtolower($outputIntent->outputConditionIdentifier), 'srgb')
        ) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID, sprintf(
                'PDF/A output intent "%s" is not plausible for an RGB ICC profile.',
                $outputIntent->outputConditionIdentifier,
            ));
        }

        if (
            $outputIntent->colorComponents === 4
            && !str_contains(strtolower($outputIntent->outputConditionIdentifier), 'cmyk')
        ) {
            throw new DocumentValidationException(DocumentBuildError::PDFA_OUTPUT_INTENT_INVALID, sprintf(
                'PDF/A output intent "%s" is not plausible for a CMYK ICC profile.',
                $outputIntent->outputConditionIdentifier,
            ));
        }
    }
}
