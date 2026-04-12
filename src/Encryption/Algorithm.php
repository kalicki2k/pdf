<?php

declare(strict_types=1);

namespace Kalle\Pdf\Encryption;

enum Algorithm
{
    case RC4_128;
    case AES_128;
}
