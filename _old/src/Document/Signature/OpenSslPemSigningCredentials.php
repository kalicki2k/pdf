<?php

declare(strict_types=1);

namespace Kalle\Pdf\Document\Signature;

use InvalidArgumentException;

final readonly class OpenSslPemSigningCredentials
{
    /**
     * @param list<string> $certificateChainPem
     */
    public function __construct(
        public string $certificatePem,
        public string $privateKeyPem,
        public ?string $privateKeyPassphrase = null,
        public array $certificateChainPem = [],
    ) {
        if ($this->certificatePem === '') {
            throw new InvalidArgumentException('Signing certificate PEM must not be empty.');
        }

        if ($this->privateKeyPem === '') {
            throw new InvalidArgumentException('Signing private key PEM must not be empty.');
        }

        foreach ($this->certificateChainPem as $index => $certificatePem) {
            if ($certificatePem !== '') {
                continue;
            }

            throw new InvalidArgumentException(sprintf(
                'Signing certificate chain entry %d must not be empty.',
                $index + 1,
            ));
        }
    }
}
