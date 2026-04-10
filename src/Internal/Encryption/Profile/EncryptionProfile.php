<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Encryption\Profile;

use Kalle\Pdf\Internal\Security\EncryptionAlgorithm;

final readonly class EncryptionProfile
{
    public function __construct(
        public EncryptionAlgorithm $algorithm,
        public int $keyLengthInBits,
        public int $dictionaryVersion,
        public int $revision,
    ) {
    }
}
