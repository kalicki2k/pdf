<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption\Profile;

use InvalidArgumentException;
use Kalle\Pdf\PdfVersion;
use Kalle\Pdf\Security\EncryptionAlgorithm;

final class EncryptionVersionResolver
{
    public function resolve(float $pdfVersion, EncryptionAlgorithm $algorithm = EncryptionAlgorithm::AUTO): EncryptionProfile
    {
        return match ($algorithm) {
            EncryptionAlgorithm::AUTO => $this->resolveAuto($pdfVersion),
            EncryptionAlgorithm::RC4_40 => $this->resolveRc4_40($pdfVersion),
            EncryptionAlgorithm::RC4_128 => $this->resolveRc4_128($pdfVersion),
            EncryptionAlgorithm::AES_128 => $this->resolveAes128($pdfVersion),
            EncryptionAlgorithm::AES_256 => $this->resolveAes256($pdfVersion),
        };
    }

    private function resolveAuto(float $pdfVersion): EncryptionProfile
    {
        if ($pdfVersion >= PdfVersion::V1_6) {
            return $this->resolveAes128($pdfVersion);
        }

        if ($pdfVersion >= PdfVersion::V1_4) {
            return $this->resolveRc4_128($pdfVersion);
        }

        return $this->resolveRc4_40($pdfVersion);
    }

    private function resolveRc4_40(float $pdfVersion): EncryptionProfile
    {
        if ($pdfVersion < PdfVersion::V1_3) {
            throw new InvalidArgumentException('RC4 40-bit encryption requires PDF 1.3 or newer.');
        }

        return new EncryptionProfile(
            EncryptionAlgorithm::RC4_40,
            40,
            1,
            2,
        );
    }

    private function resolveRc4_128(float $pdfVersion): EncryptionProfile
    {
        if ($pdfVersion < PdfVersion::V1_4) {
            throw new InvalidArgumentException('RC4 128-bit encryption requires PDF 1.4 or newer.');
        }

        return new EncryptionProfile(
            EncryptionAlgorithm::RC4_128,
            128,
            2,
            3,
        );
    }

    private function resolveAes128(float $pdfVersion): EncryptionProfile
    {
        if ($pdfVersion < PdfVersion::V1_6) {
            throw new InvalidArgumentException('AES 128-bit encryption requires PDF 1.6 or newer.');
        }

        return new EncryptionProfile(
            EncryptionAlgorithm::AES_128,
            128,
            4,
            4,
        );
    }

    private function resolveAes256(float $pdfVersion): EncryptionProfile
    {
        if ($pdfVersion < PdfVersion::V1_7) {
            throw new InvalidArgumentException('AES 256-bit encryption requires PDF 1.7 or newer.');
        }

        return new EncryptionProfile(
            EncryptionAlgorithm::AES_256,
            256,
            5,
            5,
        );
    }
}
