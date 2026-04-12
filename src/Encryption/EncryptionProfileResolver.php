<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

use InvalidArgumentException;
use Kalle\Pdf\Document\Profile;
use Kalle\Pdf\Document\Version;

final class EncryptionProfileResolver
{
    public function resolve(Profile $documentProfile, Encryption $encryption): EncryptionProfile
    {
        if (!$documentProfile->supportsEncryption()) {
            throw new InvalidArgumentException(sprintf(
                'Profile %s does not allow encryption.',
                $documentProfile->name(),
            ));
        }

        return match ($encryption->algorithm) {
            Algorithm::RC4_128 => $this->resolveRc4_128($documentProfile),
            Algorithm::AES_128 => $this->resolveAes128($documentProfile),
            Algorithm::AES_256 => $this->resolveAes256($documentProfile),
        };
    }

    private function resolveRc4_128(Profile $documentProfile): EncryptionProfile
    {
        if ($documentProfile->version() < Version::V1_4) {
            throw new InvalidArgumentException('RC4 128-bit encryption requires PDF 1.4 or newer.');
        }

        return new EncryptionProfile(
            Algorithm::RC4_128,
            128,
            2,
            3,
        );
    }

    private function resolveAes128(Profile $documentProfile): EncryptionProfile
    {
        if ($documentProfile->version() < Version::V1_6) {
            throw new InvalidArgumentException('AES 128-bit encryption requires PDF 1.6 or newer.');
        }

        return new EncryptionProfile(
            Algorithm::AES_128,
            128,
            4,
            4,
        );
    }

    private function resolveAes256(Profile $documentProfile): EncryptionProfile
    {
        if ($documentProfile->version() < Version::V1_7) {
            throw new InvalidArgumentException('AES 256-bit encryption requires PDF 1.7 or newer.');
        }

        return new EncryptionProfile(
            Algorithm::AES_256,
            256,
            5,
            5,
        );
    }
}
