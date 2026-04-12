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
}
