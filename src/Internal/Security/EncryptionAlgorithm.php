<?php

declare(strict_types=1);

namespace Kalle\Pdf\Internal\Security;

enum EncryptionAlgorithm
{
    case AUTO;
    case RC4_40;
    case RC4_128;
    case AES_128;
    case AES_256;
}
